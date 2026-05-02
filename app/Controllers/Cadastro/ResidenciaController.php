<?php

declare(strict_types=1);

namespace App\Controllers\Cadastro;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\CoassinaturaRepository;
use App\Repositories\DocumentoAnexoRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\LogRepository;
use App\Repositories\ResidenciaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;
use App\Services\ProtocoloService;
use App\Services\UploadService;
use RuntimeException;

final class ResidenciaController extends Controller
{
    private const IMOVEL_OPTIONS = ['proprio', 'alugado', 'cedido'];
    private const CONDICAO_RESIDENCIA_OPTIONS = ['perda_total', 'perda_parcial', 'nao_atingida'];
    private const MAX_FOTOS_RESIDENCIA_EXTRAS = 3;
    private const INDEX_PER_PAGE = 10;

    public function __construct(
        private readonly ResidenciaRepository $residencias = new ResidenciaRepository(),
        private readonly FamiliaRepository $familias = new FamiliaRepository(),
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository(),
        private readonly DocumentoAnexoRepository $documentos = new DocumentoAnexoRepository()
    ) {
    }

    public function index(): void
    {
        $filters = $this->indexFilters();
        $queryFilters = $this->scopedFilters($filters);
        $ownedUserId = $this->ownedRecordsUserId();
        $total = $this->residencias->countSearch($ownedUserId, $queryFilters);
        $totalPages = max(1, (int) ceil($total / self::INDEX_PER_PAGE));
        $page = min($this->requestedPage(), $totalPages);

        $this->view('cadastro.residencias.index', [
            'title' => 'Cadastros de residencias',
            'residencias' => $this->residencias->search(
                $ownedUserId,
                $queryFilters,
                self::INDEX_PER_PAGE,
                ($page - 1) * self::INDEX_PER_PAGE
            ),
            'filters' => $filters,
            'summary' => $this->residencias->summary($ownedUserId, $queryFilters),
            'pagination' => [
                'page' => $page,
                'per_page' => self::INDEX_PER_PAGE,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function show(string $id): void
    {
        $residencia = $this->findResidenciaForAccess((int) $id);

        $this->view('cadastro.residencias.show', [
            'title' => 'Residencia ' . $residencia['protocolo'],
            'residencia' => $residencia,
            'familias' => $this->familias->byResidencia((int) $id),
            'documentos' => $this->documentos->byResidencia((int) $id),
        ]);
    }

    public function dti(string $id): void
    {
        $residencia = $this->findResidenciaForAccess((int) $id);
        $documentos = $this->documentos->byResidencia((int) $id);
        $embedDocument = (string) ($_GET['embed_document'] ?? '') === '1';

        $this->view('cadastro.residencias.dti', [
            'title' => 'DTI ' . $residencia['protocolo'],
            'residencia' => $residencia,
            'familias' => $this->familias->byResidencia((int) $id),
            'documentos' => $documentos,
            'fotos' => $this->dtiImageDocuments($documentos),
            'fotosResidencia' => $this->dtiResidenceImageDocuments($documentos),
            'fotosDocumentos' => $this->dtiFamilyDocumentImages($documentos),
            'signature' => $this->latestDtiSignature((int) $id),
            'coSignatureStatus' => $this->dtiCoSignatureStatus((int) $id),
            'signatureUsers' => (new UsuarioRepository())->activeExcept((int) (current_user()['id'] ?? 0)),
            'generatedAt' => new \DateTimeImmutable('now'),
            'embedDocument' => $embedDocument,
        ], $embedDocument ? 'embed' : 'app');
    }

    public function signDti(string $id): void
    {
        $residencia = $this->findResidenciaForAccess((int) $id);
        $this->guardPost('cadastro.residencia.dti.sign.' . (int) $id, '/cadastros/residencias/' . (int) $id . '/dti');

        $familias = $this->familias->byResidencia((int) $id);
        $coSigners = $this->selectedDtiCoSigners();
        $signature = $this->buildDtiSignature($residencia, $familias, $coSigners);

        (new AuditLogService())->record(
            'assinou_dti',
            'residencias',
            (int) $id,
            json_encode($signature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->createDtiCoSignatureRequests($residencia, $signature, $coSigners);

        Session::flash(
            'success',
            $coSigners === []
                ? 'DTI assinada digitalmente. A impressao esta liberada.'
                : 'DTI assinada pelo usuario principal. A impressao sera liberada apos autorizacao dos coautores.'
        );
        $this->redirect('/cadastros/residencias/' . (int) $id . '/dti');
    }

    public function removeDtiSignature(string $id): void
    {
        $residencia = $this->findResidenciaForAccess((int) $id);
        $this->guardPost('cadastro.residencia.dti.remove_signature.' . (int) $id, '/cadastros/residencias/' . (int) $id . '/dti');

        $signature = $this->latestDtiSignature((int) $id);

        if ($signature === null) {
            Session::flash('warning', 'Esta DTI nao possui assinatura ativa para remover.');
            $this->redirect('/cadastros/residencias/' . (int) $id . '/dti');
        }

        $principalUserId = (int) ($signature['usuario_id'] ?? 0);
        if ($principalUserId <= 0 || $principalUserId !== (int) (current_user()['id'] ?? 0)) {
            $this->abort(403);
        }

        $removePrincipal = (string) ($_POST['remover_assinatura_principal'] ?? '') === '1';
        $removeCoSignatureIds = $this->postedCoSignatureRequestIds();

        if (!$removePrincipal && $removeCoSignatureIds === []) {
            Session::flash('warning', 'Selecione a assinatura principal ou pelo menos um coautor para remover.');
            $this->redirect('/cadastros/residencias/' . (int) $id . '/dti');
        }

        if (!$removePrincipal) {
            $removed = (new CoassinaturaRepository())->cancelCoSignatures(
                'dti',
                $this->dtiDocumentKey((int) $id),
                $removeCoSignatureIds,
                $principalUserId
            );

            if ($removed <= 0) {
                Session::flash('warning', 'Nenhuma coassinatura selecionada pode ser removida.');
                $this->redirect('/cadastros/residencias/' . (int) $id . '/dti');
            }

            (new AuditLogService())->record(
                'removeu_coassinaturas_dti',
                'residencias',
                (int) $id,
                json_encode([
                    'protocolo' => (string) ($residencia['protocolo'] ?? ''),
                    'removido_por' => $principalUserId,
                    'coassinaturas' => $removeCoSignatureIds,
                    'removed_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            Session::flash('success', 'Coassinatura(s) selecionada(s) removida(s). A assinatura principal foi mantida.');
            $this->redirect('/cadastros/residencias/' . (int) $id . '/dti');
        }

        (new AuditLogService())->record(
            'removeu_assinatura_dti',
            'residencias',
            (int) $id,
            json_encode([
                'protocolo' => (string) ($residencia['protocolo'] ?? ''),
                'removido_por' => $principalUserId,
                'coassinaturas' => $removeCoSignatureIds,
                'removed_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        (new CoassinaturaRepository())->cancelDocument('dti', $this->dtiDocumentKey((int) $id));

        Session::flash('success', 'Assinatura da DTI removida. O documento pode ser assinado novamente.');
        $this->redirect('/cadastros/residencias/' . (int) $id . '/dti');
    }

    public function viewDocument(string $id, string $documentoId): void
    {
        $this->findResidenciaForAccess((int) $id);

        $documento = $this->documentos->findForResidencia((int) $documentoId, (int) $id);

        if ($documento === null || !str_starts_with((string) $documento['mime_type'], 'image/')) {
            $this->abort(404);
        }

        $relativePath = str_replace('\\', '/', (string) $documento['caminho_arquivo']);
        $baseDir = realpath(BASE_PATH . '/storage/private_uploads');
        $filePath = realpath(BASE_PATH . '/' . ltrim($relativePath, '/'));

        $normalizedBase = $baseDir === false ? '' : rtrim(str_replace('\\', '/', $baseDir), '/') . '/';
        $normalizedFile = $filePath === false ? '' : str_replace('\\', '/', $filePath);

        if ($baseDir === false || $filePath === false || !str_starts_with($normalizedFile, $normalizedBase) || !is_file($filePath)) {
            $this->abort(404);
        }

        $filename = str_replace(['"', "\r", "\n"], '', basename((string) $documento['nome_original']));

        header('Content-Type: ' . (string) $documento['mime_type']);
        header('Content-Length: ' . (string) filesize($filePath));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        exit;
    }

    public function edit(string $id): void
    {
        $residencia = $this->findResidenciaForEdit((int) $id);

        $this->form(
            $this->actionFromResidencia($residencia),
            $residencia,
            [],
            'Editar residencia',
            '/cadastros/residencias/' . (int) $id,
            'Salvar alteracoes',
            '/cadastros/residencias/' . (int) $id,
            false
        );
    }

    public function update(string $id): void
    {
        $residencia = $this->findResidenciaForEdit((int) $id);
        $this->guardPost('cadastro.residencia.update.' . (int) $id, '/cadastros/residencias/' . (int) $id . '/editar');

        $data = $this->input();
        $existingDocuments = $this->documentos->byResidencia((int) $id);
        $removeDocumentIds = $this->postedDocumentIds('remover_documentos');
        $mainDocumentIds = $this->documentIdsByType($existingDocuments, (int) $id, 'foto_georreferenciada');
        $data['foto_georreferenciada'] = $residencia['foto_georreferenciada'] ?? null;

        if (array_intersect($removeDocumentIds, $mainDocumentIds) !== []) {
            $data['foto_georreferenciada'] = null;
        }

        $validator = $this->validator($data);
        $familiasCadastradas = $this->familias->countByResidencia((int) $id);

        if (!$validator->fails() && (int) $data['quantidade_familias'] < $familiasCadastradas) {
            $validator->add('quantidade_familias', 'A quantidade de familias nao pode ser menor que as familias ja cadastradas nesta residencia.');
        }

        if ($validator->fails()) {
            $this->form(
                $this->actionFromResidencia($residencia),
                $data + ['id' => $residencia['id'], 'protocolo' => $residencia['protocolo']],
                $validator->errors(),
                'Editar residencia',
                '/cadastros/residencias/' . (int) $id,
                'Salvar alteracoes',
                '/cadastros/residencias/' . (int) $id,
                false
            );
            return;
        }

        $upload = new UploadService();
        $foto = $_FILES['foto_georreferenciada'] ?? null;
        $fotoMetadata = null;
        $extraPhotoMetadata = [];

        if (is_array($foto) && $upload->hasFile($foto)) {
            try {
                $fotoMetadata = $upload->storePrivate($foto, 'residencias', ['image/jpeg', 'image/png']);
                $data['foto_georreferenciada'] = $fotoMetadata['caminho_arquivo'];
            } catch (RuntimeException $exception) {
                $errors = $validator->errors();
                $errors['foto_georreferenciada'][] = $exception->getMessage();
                $this->form(
                    $this->actionFromResidencia($residencia),
                    $data + ['id' => $residencia['id'], 'protocolo' => $residencia['protocolo']],
                    $errors,
                    'Editar residencia',
                    '/cadastros/residencias/' . (int) $id,
                    'Salvar alteracoes',
                    '/cadastros/residencias/' . (int) $id,
                    false
                );
                return;
            }
        }

        try {
            $extraPhotoMetadata = $this->storeExtraResidencePhotos(
                $upload,
                $this->documentos->countResidenceDocumentsByTypeExcludingIds((int) $id, 'foto_residencia_extra', $removeDocumentIds)
            );
        } catch (RuntimeException $exception) {
            $errors = $validator->errors();
            $errors['fotos_residencia'][] = $exception->getMessage();
            $this->form(
                $this->actionFromResidencia($residencia),
                $data + ['id' => $residencia['id'], 'protocolo' => $residencia['protocolo']],
                $errors,
                'Editar residencia',
                '/cadastros/residencias/' . (int) $id,
                'Salvar alteracoes',
                '/cadastros/residencias/' . (int) $id,
                false
            );
            return;
        }

        $this->residencias->update((int) $id, $data);

        if ($fotoMetadata !== null) {
            $this->documentos->softDeleteResidenceDocumentsByType((int) $id, 'foto_georreferenciada');
            $this->documentos->create($fotoMetadata + [
                'residencia_id' => (int) $id,
                'familia_id' => null,
                'tipo_documento' => 'foto_georreferenciada',
                'enviado_por' => (int) (current_user()['id'] ?? 0),
            ]);
        }

        $this->documentos->softDeleteByResidenciaAndIds((int) $id, $removeDocumentIds);
        $this->createExtraResidencePhotoDocuments((int) $id, $extraPhotoMetadata);

        (new AuditLogService())->record('alterou_residencia', 'residencias', (int) $id, (string) $residencia['protocolo']);
        Session::flash('success', 'Residencia atualizada.');

        $this->redirect('/cadastros/residencias/' . (int) $id);
    }

    public function createFromAction(string $token): void
    {
        $acao = $this->openAction($token);
        Session::put('active_action_token', $token);

        $this->form($acao, [
            'bairro_comunidade' => '',
            'endereco' => '',
            'complemento' => '',
            'imovel' => '',
            'condicao_residencia' => '',
            'latitude' => '',
            'longitude' => '',
            'quantidade_familias' => '1',
        ], []);
    }

    public function storeFromAction(string $token): void
    {
        $acao = $this->openAction($token);
        Session::put('active_action_token', $token);
        $this->guardPost('cadastro.residencia.store.' . $token, '/acao/' . $token . '/residencias/novo');

        $data = $this->input();
        $validator = $this->validator($data);

        if ($validator->fails()) {
            $this->form($acao, $data, $validator->errors());
            return;
        }

        $data['acao_id'] = (int) $acao['id'];
        $data['municipio_id'] = (int) $acao['municipio_id'];
        $data['protocolo'] = (new ProtocoloService())->generate($acao);
        $data['cadastrado_por'] = (int) (current_user()['id'] ?? 0);
        $data['foto_georreferenciada'] = null;

        $upload = new UploadService();
        $foto = $_FILES['foto_georreferenciada'] ?? null;
        $fotoMetadata = null;
        $extraPhotoMetadata = [];

        if (is_array($foto) && $upload->hasFile($foto)) {
            try {
                $fotoMetadata = $upload->storePrivate($foto, 'residencias', ['image/jpeg', 'image/png']);
                $data['foto_georreferenciada'] = $fotoMetadata['caminho_arquivo'];
            } catch (RuntimeException $exception) {
                $errors = $validator->errors();
                $errors['foto_georreferenciada'][] = $exception->getMessage();
                $this->form($acao, $data, $errors);
                return;
            }
        }

        try {
            $extraPhotoMetadata = $this->storeExtraResidencePhotos($upload);
        } catch (RuntimeException $exception) {
            $errors = $validator->errors();
            $errors['fotos_residencia'][] = $exception->getMessage();
            $this->form($acao, $data, $errors);
            return;
        }

        $id = $this->residencias->create($data);

        if ($fotoMetadata !== null) {
            $this->documentos->create($fotoMetadata + [
                'residencia_id' => $id,
                'familia_id' => null,
                'tipo_documento' => 'foto_georreferenciada',
                'enviado_por' => (int) (current_user()['id'] ?? 0),
            ]);
        }

        $this->createExtraResidencePhotoDocuments($id, $extraPhotoMetadata);

        (new AuditLogService())->record('criou_residencia', 'residencias', $id, $data['protocolo']);
        Session::flash('success', 'Residencia cadastrada. Agora cadastre as familias vinculadas.');

        $this->redirect('/cadastros/residencias/' . $id);
    }

    private function form(
        array $acao,
        array $residencia,
        array $errors,
        string $title = 'Nova residencia',
        ?string $action = null,
        string $submitLabel = 'Salvar residencia',
        ?string $cancelUrl = null,
        bool $useOfflineQueue = true
    ): void
    {
        $this->view('cadastro.residencias.form', [
            'title' => $title,
            'acao' => $acao,
            'residencia' => $residencia,
            'errors' => $errors,
            'action' => $action ?? '/acao/' . $acao['token_publico'] . '/residencias',
            'submitLabel' => $submitLabel,
            'cancelUrl' => $cancelUrl ?? '/acao/' . $acao['token_publico'],
            'useOfflineQueue' => $useOfflineQueue,
            'offlineTokens' => $useOfflineQueue ? $this->offlineTokens($acao['token_publico']) : [],
            'bairroOptions' => $this->bairroOptions($acao),
            'extraResidencePhotosCount' => !empty($residencia['id'])
                ? $this->documentos->countResidenceDocumentsByType((int) $residencia['id'], 'foto_residencia_extra')
                : 0,
            'documentos' => !empty($residencia['id'])
                ? $this->documentos->byResidencia((int) $residencia['id'])
                : [],
        ]);
    }

    private function postedDocumentIds(string $key): array
    {
        $ids = is_array($_POST[$key] ?? null) ? $_POST[$key] : [];

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids
        ), static fn (int $id): bool => $id > 0)));
    }

    private function documentIdsByType(array $documents, int $residenciaId, string $tipoDocumento): array
    {
        return array_values(array_map(
            static fn (array $documento): int => (int) $documento['id'],
            array_filter($documents, static fn (array $documento): bool =>
                (int) ($documento['residencia_id'] ?? 0) === $residenciaId
                && empty($documento['familia_id'])
                && (string) ($documento['tipo_documento'] ?? '') === $tipoDocumento
            )
        ));
    }

    private function bairroOptions(array $acao): array
    {
        $options = $this->residencias->neighborhoodsByMunicipalityId(
            (int) $acao['municipio_id'],
            (int) ($acao['id'] ?? 0),
            $this->ownedRecordsUserId()
        );
        $localidade = trim((string) ($acao['localidade'] ?? ''));
        $lower = static fn (string $value): string => function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
        $normalized = array_map($lower, $options);

        if ($localidade !== '' && !in_array($lower($localidade), $normalized, true)) {
            array_unshift($options, $localidade);
        }

        return array_values(array_unique($options));
    }

    private function offlineTokens(string $token): array
    {
        $service = new IdempotenciaService();
        $tokens = [];

        for ($index = 0; $index < 20; $index++) {
            $tokens[] = $service->generate('cadastro.residencia.store.' . $token);
        }

        return $tokens;
    }

    private function openAction(string $token): array
    {
        $acao = $this->acoes->findByPublicToken($token);

        if ($acao === null) {
            $this->abort(404);
        }

        if ($acao['status'] !== 'aberta') {
            Session::flash('warning', 'Esta acao nao esta aberta para novos cadastros.');
            $this->redirect('/acao/' . $token);
        }

        return $acao;
    }

    private function findResidenciaForEdit(int $id): array
    {
        $residencia = $this->findResidenciaForAccess($id);

        if (($residencia['acao_status'] ?? null) !== 'aberta') {
            Session::flash('warning', 'Esta acao nao esta aberta para editar residencias.');
            $this->redirect('/cadastros/residencias/' . $id);
        }

        return $residencia;
    }

    private function findResidenciaForAccess(int $id): array
    {
        $residencia = $this->residencias->find($id);

        if ($residencia === null || !$this->canAccessResidencia($residencia)) {
            $this->abort(404);
        }

        return $residencia;
    }

    private function canAccessResidencia(array $residencia): bool
    {
        $ownerId = $this->ownedRecordsUserId();

        if ($ownerId === null) {
            return true;
        }

        if ((int) ($residencia['cadastrado_por'] ?? 0) !== $ownerId) {
            return false;
        }

        $activeActionToken = $this->activeActionTokenForCadastrador();

        return $activeActionToken === null || hash_equals($activeActionToken, (string) ($residencia['token_publico'] ?? ''));
    }

    private function ownedRecordsUserId(): ?int
    {
        $user = current_user();

        if (($user['perfil'] ?? null) !== 'cadastrador') {
            return null;
        }

        return (int) ($user['id'] ?? 0);
    }

    private function scopedFilters(array $filters): array
    {
        $activeActionToken = $this->activeActionTokenForCadastrador();

        if ($activeActionToken !== null) {
            $filters['active_action_token'] = $activeActionToken;
        }

        return $filters;
    }

    private function activeActionTokenForCadastrador(): ?string
    {
        if ((current_user()['perfil'] ?? null) !== 'cadastrador') {
            return null;
        }

        $token = Session::get('active_action_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function actionFromResidencia(array $residencia): array
    {
        return [
            'id' => $residencia['acao_id'],
            'municipio_id' => $residencia['municipio_id'],
            'municipio_nome' => $residencia['municipio_nome'],
            'uf' => $residencia['uf'],
            'localidade' => $residencia['localidade'],
            'tipo_evento' => $residencia['tipo_evento'],
            'token_publico' => $residencia['token_publico'],
        ];
    }

    private function input(): array
    {
        return [
            'bairro_comunidade' => trim((string) ($_POST['bairro_comunidade'] ?? '')),
            'endereco' => trim((string) ($_POST['endereco'] ?? '')),
            'complemento' => trim((string) ($_POST['complemento'] ?? '')),
            'imovel' => trim((string) ($_POST['imovel'] ?? '')),
            'condicao_residencia' => trim((string) ($_POST['condicao_residencia'] ?? '')),
            'latitude' => trim((string) ($_POST['latitude'] ?? '')),
            'longitude' => trim((string) ($_POST['longitude'] ?? '')),
            'quantidade_familias' => trim((string) ($_POST['quantidade_familias'] ?? '1')),
        ];
    }

    private function indexFilters(): array
    {
        $imovel = trim((string) ($_GET['imovel'] ?? ''));
        $condicao = trim((string) ($_GET['condicao'] ?? ''));
        $familias = trim((string) ($_GET['familias'] ?? ''));

        if (!in_array($imovel, self::IMOVEL_OPTIONS, true)) {
            $imovel = '';
        }

        if (!in_array($condicao, self::CONDICAO_RESIDENCIA_OPTIONS, true)) {
            $condicao = '';
        }

        if (!in_array($familias, ['completas', 'pendentes', 'sem_familias'], true)) {
            $familias = '';
        }

        return [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120),
            'imovel' => $imovel,
            'condicao' => $condicao,
            'familias' => $familias,
            'data_inicio' => $this->validDateFilter($_GET['data_inicio'] ?? null),
            'data_fim' => $this->validDateFilter($_GET['data_fim'] ?? null),
        ];
    }

    private function requestedPage(): int
    {
        $page = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);

        return is_int($page) && $page > 0 ? $page : 1;
    }

    private function validDateFilter(mixed $value): string
    {
        $date = trim((string) $value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();

        return $parsed && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))
            ? $date
            : '';
    }

    private function validator(array $data): Validator
    {
        return (new Validator())
            ->required('bairro_comunidade', $data['bairro_comunidade'], 'Bairro/comunidade')
            ->max('bairro_comunidade', $data['bairro_comunidade'], 180, 'Bairro/comunidade')
            ->required('endereco', $data['endereco'], 'Endereco')
            ->max('endereco', $data['endereco'], 255, 'Endereco')
            ->max('complemento', $data['complemento'], 180, 'Complemento')
            ->required('imovel', $data['imovel'], 'Imovel')
            ->in('imovel', $data['imovel'], self::IMOVEL_OPTIONS, 'Imovel')
            ->required('condicao_residencia', $data['condicao_residencia'], 'Condicao da residencia')
            ->in('condicao_residencia', $data['condicao_residencia'], self::CONDICAO_RESIDENCIA_OPTIONS, 'Condicao da residencia')
            ->integer('quantidade_familias', $data['quantidade_familias'], 'Quantidade de familias')
            ->minInt('quantidade_familias', $data['quantidade_familias'], 1, 'Quantidade de familias')
            ->decimalRange('latitude', $data['latitude'], -90, 90, 'Latitude')
            ->decimalRange('longitude', $data['longitude'], -180, 180, 'Longitude');
    }

    private function storeExtraResidencePhotos(UploadService $upload, int $existingCount = 0): array
    {
        $files = $_FILES['fotos_residencia'] ?? null;

        if (!is_array($files)) {
            return [];
        }

        $pendingFiles = array_values(array_filter(
            $upload->normalizeMultiple($files),
            static fn (array $file): bool => $upload->hasFile($file)
        ));

        if ($pendingFiles === []) {
            return [];
        }

        if ($existingCount + count($pendingFiles) > self::MAX_FOTOS_RESIDENCIA_EXTRAS) {
            throw new RuntimeException('Limite maximo de 3 fotos extras da residencia atingido.');
        }

        $stored = [];

        foreach ($pendingFiles as $file) {
            $stored[] = $upload->storePrivate($file, 'residencias', ['image/jpeg', 'image/png']);
        }

        return $stored;
    }

    private function createExtraResidencePhotoDocuments(int $residenciaId, array $photos): void
    {
        foreach ($photos as $photoMetadata) {
            $this->documentos->create($photoMetadata + [
                'residencia_id' => $residenciaId,
                'familia_id' => null,
                'tipo_documento' => 'foto_residencia_extra',
                'enviado_por' => (int) (current_user()['id'] ?? 0),
            ]);
        }
    }

    private function dtiImageDocuments(array $documentos): array
    {
        return array_values(array_filter(
            $documentos,
            static fn (array $documento): bool => str_starts_with((string) ($documento['mime_type'] ?? ''), 'image/')
        ));
    }

    private function dtiResidenceImageDocuments(array $documentos): array
    {
        return array_values(array_filter(
            $this->dtiImageDocuments($documentos),
            static fn (array $documento): bool =>
                in_array((string) ($documento['tipo_documento'] ?? ''), ['foto_georreferenciada', 'foto_residencia_extra'], true)
                && empty($documento['familia_id'])
        ));
    }

    private function dtiFamilyDocumentImages(array $documentos): array
    {
        return array_values(array_filter(
            $this->dtiImageDocuments($documentos),
            static fn (array $documento): bool =>
                (int) ($documento['familia_id'] ?? 0) > 0
                || (string) ($documento['tipo_documento'] ?? '') === 'documento_familia'
        ));
    }

    private function latestDtiSignature(int $residenciaId): ?array
    {
        $log = (new LogRepository())->latestForEntityActions(
            ['assinou_dti', 'removeu_assinatura_dti'],
            'residencias',
            $residenciaId
        );

        if ($log === null || (string) ($log['acao'] ?? '') === 'removeu_assinatura_dti') {
            return null;
        }

        $decoded = json_decode((string) ($log['descricao'] ?? ''), true);

        if (is_array($decoded)) {
            $signature = $decoded + [
                'signed_at' => $log['criado_em'] ?? '',
                'usuario_id' => $log['usuario_id'] ?? null,
            ];

            return $this->enrichSignatureWithCoSignatures($signature, 'dti', $this->dtiDocumentKey($residenciaId));
        }

        return $this->enrichSignatureWithCoSignatures([
            'nome' => $log['usuario_nome'] ?? '',
            'cpf' => $log['usuario_cpf'] ?? '',
            'graduacao' => $log['usuario_graduacao'] ?? '',
            'nome_guerra' => $log['usuario_nome_guerra'] ?? '',
            'matricula_funcional' => $log['usuario_matricula_funcional'] ?? '',
            'usuario_id' => $log['usuario_id'] ?? null,
            'signed_at' => $log['criado_em'] ?? '',
            'hash' => '',
        ], 'dti', $this->dtiDocumentKey($residenciaId));
    }

    private function selectedDtiCoSigners(): array
    {
        $postedIds = is_array($_POST['assinantes_usuarios'] ?? null) ? $_POST['assinantes_usuarios'] : [];
        $currentUserId = (int) (current_user()['id'] ?? 0);
        $ids = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $postedIds
        ), static fn (int $id): bool => $id > 0 && $id !== $currentUserId));

        return (new UsuarioRepository())->activeByIds($ids);
    }

    private function postedCoSignatureRequestIds(): array
    {
        $postedIds = is_array($_POST['remover_coassinaturas'] ?? null) ? $_POST['remover_coassinaturas'] : [];

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $postedIds
        ), static fn (int $id): bool => $id > 0)));
    }

    private function signatureUserPayload(array $user, string $tipo): array
    {
        return [
            'usuario_id' => (int) ($user['id'] ?? 0),
            'tipo' => $tipo,
            'nome' => (string) ($user['nome'] ?? ''),
            'cpf' => (string) ($user['cpf'] ?? ''),
            'graduacao' => (string) ($user['graduacao'] ?? ''),
            'nome_guerra' => (string) ($user['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($user['matricula_funcional'] ?? ''),
            'orgao' => (string) ($user['orgao'] ?? ''),
            'unidade_setor' => (string) ($user['unidade_setor'] ?? ''),
        ];
    }

    private function buildDtiSignature(array $residencia, array $familias, array $coSigners): array
    {
        $sessionUser = current_user() ?? [];
        $userId = (int) ($sessionUser['id'] ?? 0);
        $user = $userId > 0 ? ((new UsuarioRepository())->find($userId) ?? $sessionUser) : $sessionUser;
        $signedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $assinantes = array_merge(
            [$this->signatureUserPayload($user, 'assinante_principal')],
            []
        );
        $familyIds = implode(',', array_map(
            static fn (array $familia): string => (string) ($familia['id'] ?? ''),
            $familias
        ));
        $signerIds = implode(',', array_map(
            static fn (array $assinante): string => (string) ($assinante['usuario_id'] ?? ''),
            $assinantes
        ));
        $base = implode('|', [
            (string) ($residencia['id'] ?? ''),
            (string) ($residencia['protocolo'] ?? ''),
            $familyIds,
            $signerIds,
            $signedAt,
        ]);

        return [
            'nome' => (string) ($user['nome'] ?? ''),
            'cpf' => (string) ($user['cpf'] ?? ''),
            'graduacao' => (string) ($user['graduacao'] ?? ''),
            'nome_guerra' => (string) ($user['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($user['matricula_funcional'] ?? ''),
            'orgao' => (string) ($user['orgao'] ?? ''),
            'unidade_setor' => (string) ($user['unidade_setor'] ?? ''),
            'usuario_id' => (int) ($user['id'] ?? 0),
            'signed_at' => $signedAt,
            'hash' => strtoupper(hash('sha256', $base)),
            'documento' => 'DTI - Descricao Tecnica de Imovel',
            'protocolo' => (string) ($residencia['protocolo'] ?? ''),
            'assinantes' => $assinantes,
            'coassinantes_solicitados' => array_map(
                fn (array $coSigner): array => $this->signatureUserPayload($coSigner, 'coassinante'),
                $coSigners
            ),
        ];
    }

    private function dtiDocumentKey(int $residenciaId): string
    {
        return 'dti:' . $residenciaId;
    }

    private function dtiCoSignatureStatus(int $residenciaId): array
    {
        return (new CoassinaturaRepository())->statusSummary('dti', $this->dtiDocumentKey($residenciaId));
    }

    private function createDtiCoSignatureRequests(array $residencia, array $signature, array $coSigners): void
    {
        (new CoassinaturaRepository())->replacePendingRequests([
            'documento_tipo' => 'dti',
            'documento_chave' => $this->dtiDocumentKey((int) ($residencia['id'] ?? 0)),
            'entidade' => 'residencias',
            'entidade_id' => (int) ($residencia['id'] ?? 0),
            'titulo' => 'DTI ' . (string) ($residencia['protocolo'] ?? ''),
            'descricao' => trim((string) ($residencia['municipio_nome'] ?? '') . '/' . (string) ($residencia['uf'] ?? '') . ' - ' . (string) ($residencia['bairro_comunidade'] ?? '')),
            'url_documento' => '/cadastros/residencias/' . (int) ($residencia['id'] ?? 0) . '/dti',
            'solicitante_usuario_id' => (int) (current_user()['id'] ?? 0),
            'assinante_principal' => array_merge(
                is_array($signature['assinantes'][0] ?? null) ? $signature['assinantes'][0] : [],
                [
                    'usuario_id' => (int) ($signature['usuario_id'] ?? current_user()['id'] ?? 0),
                    'signed_at' => (string) ($signature['signed_at'] ?? ''),
                    'hash' => (string) ($signature['hash'] ?? ''),
                ]
            ),
            'payload' => [
                'documento' => $signature['documento'] ?? 'DTI',
                'protocolo' => $residencia['protocolo'] ?? '',
                'hash' => $signature['hash'] ?? '',
            ],
        ], $coSigners);
    }

    private function enrichSignatureWithCoSignatures(array $signature, string $documentType, string $documentKey): array
    {
        $repository = new CoassinaturaRepository();
        $status = $repository->statusSummary($documentType, $documentKey);
        $primary = is_array($signature['assinantes'][0] ?? null) ? [$signature['assinantes'][0]] : [[
            'tipo' => 'assinante_principal',
            'usuario_id' => (int) ($signature['usuario_id'] ?? 0),
            'nome' => (string) ($signature['nome'] ?? ''),
            'cpf' => (string) ($signature['cpf'] ?? ''),
            'graduacao' => (string) ($signature['graduacao'] ?? ''),
            'nome_guerra' => (string) ($signature['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($signature['matricula_funcional'] ?? ''),
            'orgao' => (string) ($signature['orgao'] ?? ''),
            'unidade_setor' => (string) ($signature['unidade_setor'] ?? ''),
        ]];

        $signature['assinantes'] = array_merge($primary, $repository->authorizedSignerPayloads($documentType, $documentKey));
        $signature['coassinatura_status'] = $status;
        $signature['impressao_liberada'] = (bool) ($status['impressao_liberada'] ?? true);

        return $signature;
    }

    private function guardPost(string $scope, string $failureRedirect): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Sessao expirada ou formulario invalido.');
            $this->redirect($failureRedirect);
        }

        $idempotency = (new IdempotenciaService())->validateAndReserve($_POST['_idempotency_token'] ?? null, $scope);

        if (!$idempotency['ok']) {
            Session::flash('warning', $idempotency['message']);
            $this->redirect($failureRedirect);
        }
    }
}
