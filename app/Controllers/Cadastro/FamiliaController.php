<?php

declare(strict_types=1);

namespace App\Controllers\Cadastro;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\DocumentoAnexoRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\ResidenciaRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;
use App\Services\UploadService;
use RuntimeException;

final class FamiliaController extends Controller
{
    private const INDEX_PER_PAGE = 10;
    private const SITUACAO_OPTIONS = ['desabrigado', 'desalojado', 'aluguel_social', 'permanece_residencia'];
    private const ENTREGA_FILTERS = ['com_entrega', 'sem_entrega'];
    private const CADASTRO_FILTERS = ['concluido', 'pendente'];

    public function __construct(
        private readonly FamiliaRepository $familias = new FamiliaRepository(),
        private readonly ResidenciaRepository $residencias = new ResidenciaRepository(),
        private readonly DocumentoAnexoRepository $documentos = new DocumentoAnexoRepository()
    ) {
    }

    public function index(): void
    {
        $filters = $this->indexFilters();
        $ownedUserId = $this->ownedRecordsUserId();
        $total = $this->familias->countSearch($ownedUserId, $filters);
        $totalPages = max(1, (int) ceil($total / self::INDEX_PER_PAGE));
        $page = min($this->requestedPage(), $totalPages);

        $this->view('cadastro.familias.index', [
            'title' => 'Familias cadastradas',
            'familias' => $this->familias->search(
                $ownedUserId,
                $filters,
                self::INDEX_PER_PAGE,
                ($page - 1) * self::INDEX_PER_PAGE
            ),
            'filters' => $filters,
            'summary' => $this->familias->searchSummary($ownedUserId, $filters),
            'acoes' => $this->familias->familyActionOptions($ownedUserId),
            'residencias' => $this->familias->familyResidenceOptions($ownedUserId),
            'pagination' => [
                'page' => $page,
                'per_page' => self::INDEX_PER_PAGE,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function create(string $residenciaId): void
    {
        $residencia = $this->findResidenciaForCadastro((int) $residenciaId);
        $this->ensureFamilyCapacity($residencia);

        $this->form('Nova familia', $residencia, $this->emptyInput(), [], '/cadastros/residencias/' . (int) $residenciaId . '/familias', 'Salvar familia');
    }

    public function show(string $residenciaId, string $familiaId): void
    {
        $residencia = $this->findResidencia((int) $residenciaId);
        $familia = $this->findFamiliaForResidencia((int) $familiaId, (int) $residenciaId);

        $this->view('cadastro.familias.show', [
            'title' => 'Familia ' . $familia['responsavel_nome'],
            'residencia' => $residencia,
            'familia' => $familia,
        ]);
    }

    public function receipt(string $residenciaId, string $familiaId): void
    {
        $residencia = $this->findResidencia((int) $residenciaId);
        $familia = $this->findFamiliaForResidencia((int) $familiaId, (int) $residenciaId);
        $receiptCode = familia_comprovante_codigo($familia);
        $validationUrl = public_url('/gestor/entregas/validar/' . rawurlencode($receiptCode));
        $whatsappText = implode("\n", [
            'Comprovante de cadastro familiar - Cadastro Emergencial',
            'Responsavel: ' . (string) $familia['responsavel_nome'],
            'CPF: ' . (string) $familia['responsavel_cpf'],
            'Codigo: ' . $receiptCode,
            'Validacao: ' . $validationUrl,
        ]);

        $this->view('cadastro.familias.receipt', [
            'title' => 'Comprovante ' . $receiptCode,
            'residencia' => $residencia,
            'familia' => $familia,
            'receiptCode' => $receiptCode,
            'validationUrl' => $validationUrl,
            'whatsappUrl' => 'https://wa.me/?text=' . rawurlencode($whatsappText),
            'whatsappText' => $whatsappText,
            'generatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function viewDocument(string $residenciaId, string $familiaId, string $documentoId): void
    {
        $this->findResidencia((int) $residenciaId);
        $this->findFamiliaForResidencia((int) $familiaId, (int) $residenciaId);
        $documento = $this->documentos->findForFamilia((int) $documentoId, (int) $familiaId);

        if ($documento === null) {
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

    public function edit(string $residenciaId, string $familiaId): void
    {
        $residencia = $this->findResidenciaForCadastro((int) $residenciaId);
        $familia = $this->findFamiliaForResidencia((int) $familiaId, (int) $residenciaId);

        $this->form(
            'Editar familia',
            $residencia,
            $familia,
            [],
            '/cadastros/residencias/' . (int) $residenciaId . '/familias/' . (int) $familiaId,
            'Salvar alteracoes',
            $this->documentos->byFamilia((int) $familiaId)
        );
    }

    public function store(string $residenciaId): void
    {
        $residencia = $this->findResidenciaForCadastro((int) $residenciaId);
        $this->guardPost('cadastro.familia.store.' . (int) $residenciaId, '/cadastros/residencias/' . (int) $residenciaId . '/familias/novo');

        $data = $this->input();
        $validator = $this->validator($data);

        $this->validateFamilyCapacity($validator, $residencia);

        if (!$validator->fails()) {
            $this->validateCpfUniquenessInOpenAction($validator, $residencia, $data);
        }

        if ($validator->fails()) {
            $this->form('Nova familia', $residencia, $data, $validator->errors(), '/cadastros/residencias/' . (int) $residenciaId . '/familias', 'Salvar familia');
            return;
        }

        $upload = new UploadService();
        $documentos = [];
        $files = is_array($_FILES['documentos'] ?? null) ? $upload->normalizeMultiple($_FILES['documentos']) : [];

        foreach ($files as $file) {
            if (!$upload->hasFile($file)) {
                continue;
            }

            try {
                $documentos[] = $upload->storePrivate($file, 'familias');
            } catch (RuntimeException $exception) {
                $errors = $validator->errors();
                $errors['documentos'][] = $exception->getMessage();
                $this->form('Nova familia', $residencia, $data, $errors, '/cadastros/residencias/' . (int) $residenciaId . '/familias', 'Salvar familia');
                return;
            }
        }

        $data['residencia_id'] = (int) $residenciaId;
        $id = $this->familias->create($data);

        foreach ($documentos as $metadata) {
            $this->documentos->create($metadata + [
                'familia_id' => $id,
                'residencia_id' => null,
                'tipo_documento' => 'documento_familia',
                'enviado_por' => (int) (current_user()['id'] ?? 0),
            ]);
        }

        (new AuditLogService())->record('criou_familia', 'familias', $id, $data['responsavel_nome']);
        Session::flash('success', 'Familia cadastrada.');

        $this->redirect('/cadastros/residencias/' . (int) $residenciaId . '/familias/' . $id . '/comprovante');
    }

    public function update(string $residenciaId, string $familiaId): void
    {
        $residencia = $this->findResidenciaForCadastro((int) $residenciaId);
        $familia = $this->findFamiliaForResidencia((int) $familiaId, (int) $residenciaId);
        $this->guardPost('cadastro.familia.update.' . (int) $familiaId, '/cadastros/residencias/' . (int) $residenciaId . '/familias/' . (int) $familiaId . '/editar');

        $data = $this->input();
        $validator = $this->validator($data);
        $action = '/cadastros/residencias/' . (int) $residenciaId . '/familias/' . (int) $familiaId;

        if (!$validator->fails()) {
            $this->validateCpfUniquenessInOpenAction($validator, $residencia, $data, (int) $familiaId);
        }

        if ($validator->fails()) {
            $this->form('Editar familia', $residencia, $data + ['id' => $familia['id']], $validator->errors(), $action, 'Salvar alteracoes', $this->documentos->byFamilia((int) $familiaId));
            return;
        }

        $upload = new UploadService();
        $documentos = [];
        $files = is_array($_FILES['documentos'] ?? null) ? $upload->normalizeMultiple($_FILES['documentos']) : [];
        $removeDocumentIds = is_array($_POST['remover_documentos'] ?? null) ? $_POST['remover_documentos'] : [];

        foreach ($files as $file) {
            if (!$upload->hasFile($file)) {
                continue;
            }

            try {
                $documentos[] = $upload->storePrivate($file, 'familias');
            } catch (RuntimeException $exception) {
                $errors = $validator->errors();
                $errors['documentos'][] = $exception->getMessage();
                $this->form('Editar familia', $residencia, $data + ['id' => $familia['id']], $errors, $action, 'Salvar alteracoes', $this->documentos->byFamilia((int) $familiaId));
                return;
            }
        }

        $this->familias->update((int) $familiaId, $data);

        if ($removeDocumentIds !== []) {
            $this->documentos->softDeleteByFamiliaAndIds((int) $familiaId, $removeDocumentIds);
        }

        foreach ($documentos as $metadata) {
            $this->documentos->create($metadata + [
                'familia_id' => (int) $familiaId,
                'residencia_id' => null,
                'tipo_documento' => 'documento_familia',
                'enviado_por' => (int) (current_user()['id'] ?? 0),
            ]);
        }

        (new AuditLogService())->record('alterou_familia', 'familias', (int) $familiaId, $data['responsavel_nome']);
        Session::flash('success', 'Familia atualizada.');

        $this->redirect('/cadastros/residencias/' . (int) $residenciaId . '/familias/' . (int) $familiaId . '/comprovante');
    }

    public function delete(string $residenciaId, string $familiaId): void
    {
        $this->findResidenciaForCadastro((int) $residenciaId);
        $familia = $this->findFamiliaForResidencia((int) $familiaId, (int) $residenciaId);
        $this->guardPost('cadastro.familia.delete.' . (int) $familiaId, '/cadastros/residencias/' . (int) $residenciaId);

        $this->familias->softDelete((int) $familiaId);
        (new AuditLogService())->record('excluiu_familia', 'familias', (int) $familiaId, $familia['responsavel_nome']);
        Session::flash('success', 'Familia removida da listagem.');

        $this->redirect('/cadastros/residencias/' . (int) $residenciaId);
    }

    private function findResidencia(int $id): array
    {
        $residencia = $this->residencias->find($id);

        if ($residencia === null || !$this->canAccessResidencia($residencia)) {
            $this->abort(404);
        }

        return $residencia;
    }

    private function findResidenciaForCadastro(int $id): array
    {
        $residencia = $this->findResidencia($id);

        if (($residencia['acao_status'] ?? null) !== 'aberta') {
            Session::flash('warning', 'Esta acao nao esta aberta para novos cadastros.');
            $this->redirect('/cadastros/residencias/' . $id);
        }

        if (!empty($residencia['token_publico'])) {
            Session::put('active_action_token', (string) $residencia['token_publico']);
        }

        return $residencia;
    }

    private function findFamiliaForResidencia(int $familiaId, int $residenciaId): array
    {
        $familia = $this->familias->findForResidencia($familiaId, $residenciaId);

        if ($familia === null) {
            $this->abort(404);
        }

        return $familia;
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

    private function form(string $title, array $residencia, array $familia, array $errors, string $action, string $submitLabel, array $documentos = []): void
    {
        $this->view('cadastro.familias.form', [
            'title' => $title,
            'residencia' => $residencia,
            'familia' => $familia,
            'errors' => $errors,
            'action' => $action,
            'submitLabel' => $submitLabel,
            'documentos' => $documentos,
        ]);
    }

    private function emptyInput(): array
    {
        return [
            'responsavel_nome' => '',
            'responsavel_cpf' => '',
            'responsavel_rg' => '',
            'responsavel_sexo' => '',
            'responsavel_orgao_expedidor' => '',
            'data_nascimento' => '',
            'telefone' => '',
            'email' => '',
            'quantidade_integrantes' => '1',
            'renda_familiar' => '',
            'perdas_bens_moveis' => '',
            'situacao_familia' => '',
            'recebe_beneficio_social' => '',
            'beneficio_social_nome' => '',
            'cadastro_concluido' => '',
            'conclusao_observacoes' => '',
            'possui_criancas' => '',
            'possui_idosos' => '',
            'possui_pcd' => '',
            'possui_gestantes' => '',
            'registrar_representante' => '',
            'representante_nome' => '',
            'representante_cpf' => '',
            'representante_rg' => '',
            'representante_orgao_expedidor' => '',
            'representante_data_nascimento' => '',
            'representante_sexo' => '',
            'representante_telefone' => '',
        ];
    }

    private function input(): array
    {
        return [
            'responsavel_nome' => trim((string) ($_POST['responsavel_nome'] ?? '')),
            'responsavel_cpf' => trim((string) ($_POST['responsavel_cpf'] ?? '')),
            'responsavel_rg' => trim((string) ($_POST['responsavel_rg'] ?? '')),
            'responsavel_sexo' => trim((string) ($_POST['responsavel_sexo'] ?? '')),
            'responsavel_orgao_expedidor' => trim((string) ($_POST['responsavel_orgao_expedidor'] ?? '')),
            'data_nascimento' => trim((string) ($_POST['data_nascimento'] ?? '')),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'quantidade_integrantes' => trim((string) ($_POST['quantidade_integrantes'] ?? '1')),
            'renda_familiar' => trim((string) ($_POST['renda_familiar'] ?? '')),
            'perdas_bens_moveis' => trim((string) ($_POST['perdas_bens_moveis'] ?? '')),
            'situacao_familia' => trim((string) ($_POST['situacao_familia'] ?? '')),
            'recebe_beneficio_social' => isset($_POST['recebe_beneficio_social']) ? '1' : '',
            'beneficio_social_nome' => isset($_POST['recebe_beneficio_social']) ? trim((string) ($_POST['beneficio_social_nome'] ?? '')) : '',
            'cadastro_concluido' => isset($_POST['cadastro_concluido']) ? '1' : '',
            'conclusao_observacoes' => trim((string) ($_POST['conclusao_observacoes'] ?? '')),
            'possui_criancas' => isset($_POST['possui_criancas']) ? '1' : '',
            'possui_idosos' => isset($_POST['possui_idosos']) ? '1' : '',
            'possui_pcd' => isset($_POST['possui_pcd']) ? '1' : '',
            'possui_gestantes' => isset($_POST['possui_gestantes']) ? '1' : '',
            'registrar_representante' => isset($_POST['registrar_representante']) ? '1' : '',
            'representante_nome' => isset($_POST['registrar_representante']) ? trim((string) ($_POST['representante_nome'] ?? '')) : '',
            'representante_cpf' => isset($_POST['registrar_representante']) ? trim((string) ($_POST['representante_cpf'] ?? '')) : '',
            'representante_rg' => isset($_POST['registrar_representante']) ? trim((string) ($_POST['representante_rg'] ?? '')) : '',
            'representante_orgao_expedidor' => isset($_POST['registrar_representante']) ? trim((string) ($_POST['representante_orgao_expedidor'] ?? '')) : '',
            'representante_data_nascimento' => isset($_POST['registrar_representante']) ? trim((string) ($_POST['representante_data_nascimento'] ?? '')) : '',
            'representante_sexo' => isset($_POST['registrar_representante']) ? trim((string) ($_POST['representante_sexo'] ?? '')) : '',
            'representante_telefone' => isset($_POST['registrar_representante']) ? trim((string) ($_POST['representante_telefone'] ?? '')) : '',
        ];
    }

    private function indexFilters(): array
    {
        $situacao = trim((string) ($_GET['situacao'] ?? ''));
        $entregas = trim((string) ($_GET['entregas'] ?? ''));
        $cadastro = trim((string) ($_GET['cadastro'] ?? ''));

        if (!in_array($situacao, self::SITUACAO_OPTIONS, true)) {
            $situacao = '';
        }

        if (!in_array($entregas, self::ENTREGA_FILTERS, true)) {
            $entregas = '';
        }

        if (!in_array($cadastro, self::CADASTRO_FILTERS, true)) {
            $cadastro = '';
        }

        return [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120),
            'acao_id' => $this->integerFilter($_GET['acao_id'] ?? null),
            'acao_busca' => mb_substr(trim((string) ($_GET['acao_busca'] ?? '')), 0, 120),
            'residencia_id' => $this->integerFilter($_GET['residencia_id'] ?? null),
            'residencia_busca' => mb_substr(trim((string) ($_GET['residencia_busca'] ?? '')), 0, 120),
            'situacao' => $situacao,
            'entregas' => $entregas,
            'cadastro' => $cadastro,
            'data_inicio' => $this->validDateFilter($_GET['data_inicio'] ?? null),
            'data_fim' => $this->validDateFilter($_GET['data_fim'] ?? null),
        ];
    }

    private function requestedPage(): int
    {
        $page = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);

        return is_int($page) && $page > 0 ? $page : 1;
    }

    private function integerFilter(mixed $value): string
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($id) && $id > 0 ? (string) $id : '';
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
        $validator = (new Validator())
            ->required('responsavel_nome', $data['responsavel_nome'], 'Responsavel familiar')
            ->max('responsavel_nome', $data['responsavel_nome'], 180, 'Responsavel familiar')
            ->required('responsavel_cpf', $data['responsavel_cpf'], 'CPF do responsavel')
            ->max('responsavel_cpf', $data['responsavel_cpf'], 14, 'CPF do responsavel')
            ->cpf('responsavel_cpf', $data['responsavel_cpf'], 'CPF do responsavel')
            ->max('responsavel_rg', $data['responsavel_rg'], 30, 'RG')
            ->required('responsavel_orgao_expedidor', $data['responsavel_orgao_expedidor'], 'Orgao expedidor do responsavel')
            ->max('responsavel_orgao_expedidor', $data['responsavel_orgao_expedidor'], 30, 'Orgao expedidor')
            ->required('data_nascimento', $data['data_nascimento'], 'Data de nascimento')
            ->date('data_nascimento', $data['data_nascimento'], 'Data de nascimento')
            ->max('telefone', $data['telefone'], 30, 'Telefone')
            ->email('email', $data['email'], 'E-mail')
            ->max('email', $data['email'], 180, 'E-mail')
            ->required('quantidade_integrantes', $data['quantidade_integrantes'], 'Quantidade de integrantes')
            ->integer('quantidade_integrantes', $data['quantidade_integrantes'], 'Quantidade de integrantes')
            ->minInt('quantidade_integrantes', $data['quantidade_integrantes'], 1, 'Quantidade de integrantes')
            ->max('perdas_bens_moveis', $data['perdas_bens_moveis'], 1000, 'Perdas de bens moveis')
            ->required('renda_familiar', $data['renda_familiar'], 'Renda familiar')
            ->required('situacao_familia', $data['situacao_familia'], 'Situacao da familia')
            ->max('beneficio_social_nome', $data['beneficio_social_nome'], 180, 'Beneficio social')
            ->required('cadastro_concluido', $data['cadastro_concluido'], 'Cadastro familiar revisado e concluido')
            ->required('conclusao_observacoes', $data['conclusao_observacoes'], 'Observacoes finais')
            ->max('conclusao_observacoes', $data['conclusao_observacoes'], 1000, 'Observacoes da conclusao')
            ->max('representante_nome', $data['representante_nome'], 180, 'Representante')
            ->max('representante_cpf', $data['representante_cpf'], 14, 'CPF do representante')
            ->cpf('representante_cpf', $data['representante_cpf'], 'CPF do representante')
            ->max('representante_rg', $data['representante_rg'], 30, 'RG do representante')
            ->max('representante_orgao_expedidor', $data['representante_orgao_expedidor'], 30, 'Orgao expedidor do representante')
            ->date('representante_data_nascimento', $data['representante_data_nascimento'], 'Data de nascimento do representante')
            ->max('representante_telefone', $data['representante_telefone'], 30, 'Telefone do representante');

        $validator->required('responsavel_sexo', $data['responsavel_sexo'], 'Sexo do responsavel');

        if ($data['responsavel_sexo'] !== '') {
            $validator->in('responsavel_sexo', $data['responsavel_sexo'], ['feminino', 'masculino', 'outro', 'nao_informado'], 'Sexo do responsavel');
        }

        if ($data['renda_familiar'] !== '') {
            $validator->in('renda_familiar', $data['renda_familiar'], ['0_3_salarios', 'acima_3_salarios'], 'Renda familiar');
        }

        if ($data['situacao_familia'] !== '') {
            $validator->in('situacao_familia', $data['situacao_familia'], ['desabrigado', 'desalojado', 'aluguel_social', 'permanece_residencia'], 'Situacao da familia');
        }

        if ($data['registrar_representante'] !== '') {
            $validator
                ->required('representante_nome', $data['representante_nome'], 'Nome completo do representante')
                ->required('representante_cpf', $data['representante_cpf'], 'CPF do representante')
                ->required('representante_orgao_expedidor', $data['representante_orgao_expedidor'], 'Orgao expedidor do representante')
                ->required('representante_data_nascimento', $data['representante_data_nascimento'], 'Data de nascimento do representante')
                ->required('representante_sexo', $data['representante_sexo'], 'Sexo do representante');

            if ($data['representante_sexo'] !== '') {
                $validator->in('representante_sexo', $data['representante_sexo'], ['feminino', 'masculino', 'outro', 'nao_informado'], 'Sexo do representante');
            }
        }

        return $validator;
    }

    private function ensureFamilyCapacity(array $residencia): void
    {
        if (!$this->hasFamilyCapacity($residencia)) {
            Session::flash('warning', 'A quantidade de familias definida para esta residencia ja foi atingida.');
            $this->redirect('/cadastros/residencias/' . (int) $residencia['id']);
        }
    }

    private function validateFamilyCapacity(Validator $validator, array $residencia): void
    {
        if (!$this->hasFamilyCapacity($residencia)) {
            $validator->add('quantidade_integrantes', 'A quantidade de familias definida para esta residencia ja foi atingida.');
        }
    }

    private function hasFamilyCapacity(array $residencia): bool
    {
        $limite = max(1, (int) ($residencia['quantidade_familias'] ?? 1));

        return $this->familias->countByResidencia((int) $residencia['id']) < $limite;
    }

    private function validateCpfUniquenessInOpenAction(Validator $validator, array $residencia, array $data, ?int $excludeFamiliaId = null): void
    {
        if (($residencia['acao_status'] ?? null) !== 'aberta') {
            return;
        }

        $cpfs = [
            (string) $data['responsavel_cpf'],
            (string) $data['representante_cpf'],
        ];
        $conflict = $this->familias->findCpfConflictInOpenAction((int) $residencia['acao_id'], $cpfs, $excludeFamiliaId);

        if ($conflict === null) {
            return;
        }

        $responsavelCpf = $this->normalizeCpf((string) $data['responsavel_cpf']);
        $representanteCpf = $this->normalizeCpf((string) $data['representante_cpf']);
        $conflictCpfs = array_filter([
            $this->normalizeCpf((string) ($conflict['responsavel_cpf'] ?? '')),
            $this->normalizeCpf((string) ($conflict['representante_cpf'] ?? '')),
        ]);
        $message = 'Este CPF ja esta vinculado a uma familia cadastrada nesta acao aberta.';

        if ($responsavelCpf !== '' && in_array($responsavelCpf, $conflictCpfs, true)) {
            $validator->add('responsavel_cpf', $message);
        }

        if ($representanteCpf !== '' && in_array($representanteCpf, $conflictCpfs, true)) {
            $validator->add('representante_cpf', $message);
        }

        if ($responsavelCpf === '' && $representanteCpf === '') {
            $validator->add('responsavel_cpf', $message);
        }
    }

    private function normalizeCpf(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? '';
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
