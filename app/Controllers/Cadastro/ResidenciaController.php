<?php

declare(strict_types=1);

namespace App\Controllers\Cadastro;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\DocumentoAnexoRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\ResidenciaRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;
use App\Services\ProtocoloService;
use App\Services\UploadService;
use RuntimeException;

final class ResidenciaController extends Controller
{
    private const IMOVEL_OPTIONS = ['proprio', 'alugado', 'cedido'];
    private const CONDICAO_RESIDENCIA_OPTIONS = ['perda_total', 'perda_parcial', 'nao_atingida'];

    public function __construct(
        private readonly ResidenciaRepository $residencias = new ResidenciaRepository(),
        private readonly FamiliaRepository $familias = new FamiliaRepository(),
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository(),
        private readonly DocumentoAnexoRepository $documentos = new DocumentoAnexoRepository()
    ) {
    }

    public function index(): void
    {
        $this->view('cadastro.residencias.index', [
            'title' => 'Cadastros de residencias',
            'residencias' => $this->residencias->all($this->ownedRecordsUserId()),
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
        $data['foto_georreferenciada'] = $residencia['foto_georreferenciada'] ?? null;
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

        $this->residencias->update((int) $id, $data);

        if ($fotoMetadata !== null) {
            $this->documentos->create($fotoMetadata + [
                'residencia_id' => (int) $id,
                'familia_id' => null,
                'tipo_documento' => 'foto_georreferenciada',
                'enviado_por' => (int) (current_user()['id'] ?? 0),
            ]);
        }

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

        $id = $this->residencias->create($data);

        if ($fotoMetadata !== null) {
            $this->documentos->create($fotoMetadata + [
                'residencia_id' => $id,
                'familia_id' => null,
                'tipo_documento' => 'foto_georreferenciada',
                'enviado_por' => (int) (current_user()['id'] ?? 0),
            ]);
        }

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
        ]);
    }

    private function bairroOptions(array $acao): array
    {
        $options = $this->residencias->neighborhoodsByMunicipalityId((int) $acao['municipio_id']);
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

        return $ownerId === null || (int) ($residencia['cadastrado_por'] ?? 0) === $ownerId;
    }

    private function ownedRecordsUserId(): ?int
    {
        $user = current_user();

        if (($user['perfil'] ?? null) !== 'cadastrador') {
            return null;
        }

        return (int) ($user['id'] ?? 0);
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
