<?php

declare(strict_types=1);

namespace App\Controllers\Gestor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\CoassinaturaRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\LogRepository;
use App\Repositories\RecomecarRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;

final class RecomecarController extends Controller
{
    private const PER_PAGE = 10;
    private const ANALYSIS_PER_PAGE = 8;
    private const DOCUMENT_TYPE = 'recomecar';
    private const IMOVEL_OPTIONS = ['proprio', 'alugado', 'cedido'];
    private const CONDICAO_RESIDENCIA_OPTIONS = ['perda_total', 'perda_parcial', 'nao_atingida'];
    private const SEXO_OPTIONS = ['feminino', 'masculino', 'outro', 'nao_informado'];
    private const RENDA_OPTIONS = ['0_3_salarios', 'acima_3_salarios'];
    private const SITUACAO_OPTIONS = ['desabrigado', 'desalojado', 'aluguel_social', 'permanece_residencia'];

    public function __construct(
        private readonly RecomecarRepository $recomecar = new RecomecarRepository(),
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository(),
        private readonly UsuarioRepository $usuarios = new UsuarioRepository(),
        private readonly FamiliaRepository $familias = new FamiliaRepository()
    ) {
    }

    public function index(): void
    {
        $filters = $this->filters();
        $embedDocument = (string) ($_GET['embed_document'] ?? '') === '1';
        $hasAppliedFilters = $this->hasAppliedFilters($filters);
        $documentDetails = $hasAppliedFilters ? $this->recomecar->details($filters) : [];
        $page = max(1, (int) ($_GET['pagina'] ?? 1));
        $total = count($documentDetails);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $generatedAt = new \DateTimeImmutable();
        $currentUser = $this->currentUserRecord();
        $documentIdentity = $hasAppliedFilters ? $this->documentIdentity($filters, $documentDetails) : null;
        $showSignature = $hasAppliedFilters && (string) ($_GET['assinatura'] ?? '') === '1';
        $signature = ($documentIdentity !== null && $showSignature) ? $this->latestSignature($documentIdentity) : null;
        $coSignatureStatus = $documentIdentity !== null
            ? (new CoassinaturaRepository())->statusSummary(self::DOCUMENT_TYPE, (string) $documentIdentity['document_key'])
            : $this->emptyCoSignatureStatus();

        $this->view('gestor.recomecar.index', [
            'title' => 'Recomeçar',
            'filters' => $filters,
            'acoes' => $this->acoes->all(),
            'signatureUsers' => $this->signatureUsers((int) ($currentUser['id'] ?? 0)),
            'hasAppliedFilters' => $hasAppliedFilters,
            'indicators' => $hasAppliedFilters ? $this->recomecar->indicators($filters) : $this->emptyIndicators(),
            'details' => array_slice($documentDetails, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'documentDetails' => $documentDetails,
            'documentContext' => $hasAppliedFilters ? $this->recomecar->documentContext($filters) : [],
            'currentUser' => $currentUser,
            'signature' => $signature,
            'coSignatureStatus' => $coSignatureStatus,
            'documentIdentity' => $documentIdentity,
            'pagination' => [
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => $total,
                'pages' => $totalPages,
            ],
            'generatedAt' => $generatedAt,
            'embedDocument' => $embedDocument,
        ], $embedDocument ? 'embed' : 'app');
    }

    public function analysis(): void
    {
        $filters = $this->analysisFilters();
        $hasAppliedFilters = $this->hasAppliedAnalysisFilters($filters);
        $currentUser = current_user() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $canManageAssignments = $this->canManageAnalysisAssignments();
        $queryFilters = $this->analysisVisibilityFilters($filters, $currentUser);
        $total = $hasAppliedFilters ? $this->recomecar->countAnalysisRecords($queryFilters) : 0;
        $totalPages = max(1, (int) ceil($total / self::ANALYSIS_PER_PAGE));
        $page = min($this->requestedPage(), $totalPages);
        $records = $hasAppliedFilters
            ? $this->recomecar->analysisRecords($queryFilters, self::ANALYSIS_PER_PAGE, ($page - 1) * self::ANALYSIS_PER_PAGE)
            : [];
        $familiaIds = array_map(static fn (array $record): int => (int) ($record['familia_id'] ?? 0), $records);

        $this->view('gestor.recomecar.analise', [
            'title' => 'Analise do Programa Recomecar',
            'filters' => $filters,
            'acoes' => $this->acoes->all(),
            'hasAppliedFilters' => $hasAppliedFilters,
            'summary' => $hasAppliedFilters ? $this->recomecar->analysisSummary($queryFilters) : $this->emptyAnalysisSummary(),
            'records' => $records,
            'documentsByRecord' => $this->recomecar->documentsForRecords($records),
            'historyByRecord' => $this->recomecar->historyForRecords($familiaIds),
            'analysisUsers' => $this->recomecar->analysisAssignmentUsers(),
            'assignmentSummary' => $hasAppliedFilters ? $this->recomecar->analysisAssignmentSummary($filters) : [],
            'userQueueStatus' => $this->recomecar->userAnalysisQueueStatus($currentUserId),
            'managedQueueStatus' => $this->recomecar->managedAnalysisQueueStatus($currentUserId),
            'distributionHistory' => $canManageAssignments ? $this->recomecar->managedAnalysisDistributionHistory($currentUserId) : [],
            'canManageAssignments' => $canManageAssignments,
            'currentUser' => $currentUser,
            'pagination' => [
                'page' => $page,
                'per_page' => self::ANALYSIS_PER_PAGE,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'openEditId' => $this->positiveInt($_GET['editar'] ?? null),
        ]);
    }

    public function updateAnalysisRecord(string $familiaId): void
    {
        $id = (int) $familiaId;
        $redirect = $this->analysisRedirectFromPost($id, true);
        $this->guardPost('gestor.recomecar.analysis.update.' . $id, $redirect);

        $before = $this->recomecar->findAnalysisRecord($id);
        if ($before === null) {
            $this->abort(404);
        }

        if (!$this->canCurrentUserAnalyzeFamily($id)) {
            $this->abort(403);
        }

        $data = $this->analysisInput();
        $validator = $this->analysisValidator($data);
        $familiasCadastradas = $this->familias->countByResidencia((int) $before['residencia_id']);

        if (!$validator->fails() && (int) $data['quantidade_familias'] < $familiasCadastradas) {
            $validator->add('quantidade_familias', 'A quantidade de familias nao pode ser menor que as familias ja cadastradas nesta residencia.');
        }

        if (!$validator->fails()) {
            $this->validateCpfUniquenessInOpenAction($validator, $before, $data, $id);
        }

        if ($validator->fails()) {
            Session::flash('recomecar_analysis_validation', [
                'familia_id' => $id,
                'messages' => $this->analysisValidationMessages($validator->errors()),
            ]);
            Session::flash('recomecar_analysis_old_input', $data);
            $this->redirect($redirect);
        }

        $this->recomecar->updateAnalysisRecord($id, $data);
        $after = $this->recomecar->findAnalysisRecord($id) ?? $before;
        $changes = $this->analysisChanges($before, $after);

        (new AuditLogService())->record(
            'editou_recomecar_dados',
            'recomecar_analise',
            $id,
            json_encode([
                'familia_id' => $id,
                'residencia_id' => (int) ($before['residencia_id'] ?? 0),
                'protocolo' => (string) ($before['protocolo'] ?? ''),
                'alteracoes' => $changes,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Session::flash('success', $changes === [] ? 'Registro conferido. Nenhum campo foi alterado.' : 'Registro atualizado e historico gravado.');
        $this->redirect($redirect);
    }

    public function markAnalysisRecord(string $familiaId): void
    {
        $id = (int) $familiaId;
        $redirect = $this->analysisRedirectFromPost($id);
        $this->guardPost('gestor.recomecar.analysis.mark.' . $id, $redirect);

        $record = $this->recomecar->findAnalysisRecord($id);
        if ($record === null) {
            $this->abort(404);
        }

        if (!$this->canCurrentUserAnalyzeFamily($id)) {
            $this->abort(403);
        }

        $observacao = mb_substr(trim((string) ($_POST['observacao_analise'] ?? '')), 0, 1000);
        (new AuditLogService())->record(
            'analisou_recomecar_familia',
            'recomecar_analise',
            $id,
            json_encode([
                'familia_id' => $id,
                'residencia_id' => (int) ($record['residencia_id'] ?? 0),
                'protocolo' => (string) ($record['protocolo'] ?? ''),
                'aptidao' => (string) ($record['aptidao'] ?? ''),
                'status_entrega' => (string) ($record['status_entrega'] ?? ''),
                'observacao' => $observacao,
                'liberado_para_entrega' => true,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Session::flash('success', 'Familia marcada como analisada e liberada para entrega.');
        $this->redirect($redirect);
    }

    public function distributeAnalysisRecords(): void
    {
        if (!$this->canManageAnalysisAssignments()) {
            $this->abort(403);
        }

        $params = is_array($_POST['filters'] ?? null) ? $_POST['filters'] : [];
        $filters = $this->analysisFiltersFromArray($params);
        $redirect = $this->analysisUrlFromFilters($filters);
        $this->guardPost('gestor.recomecar.analysis.assign', $redirect);

        if ((int) ($filters['acao_id'] ?? 0) <= 0) {
            Session::flash('warning', 'Selecione a acao emergencial que recebera a distribuicao da analise.');
            $this->redirect($redirect);
        }

        $userIds = is_array($_POST['analistas_usuarios'] ?? null) ? $_POST['analistas_usuarios'] : [];
        $userIds = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $userIds
        ), static fn (int $id): bool => $id > 0));

        if ($userIds === []) {
            Session::flash('warning', 'Selecione pelo menos um usuario ativo para receber registros.');
            $this->redirect($redirect);
        }

        $strategy = (string) ($_POST['distribuicao_estrategia'] ?? '');
        if (!in_array($strategy, ['pares_impares', 'blocos'], true)) {
            Session::flash('warning', 'Selecione a forma de distribuicao dos registros.');
            $this->redirect($redirect);
        }

        $periodStart = $this->date($_POST['periodo_inicio'] ?? null);
        $periodEnd = $this->date($_POST['periodo_fim'] ?? null);
        if ($periodStart === '' || $periodEnd === '') {
            Session::flash('warning', 'Informe o periodo de cadastro com data inicial e data final.');
            $this->redirect($redirect);
        }

        if ($periodStart > $periodEnd) {
            Session::flash('warning', 'O periodo inicial nao pode ser maior que o periodo final.');
            $this->redirect($redirect);
        }

        $filters['data_inicio'] = $periodStart;
        $filters['data_fim'] = $periodEnd;
        $redirect = $this->analysisUrlFromFilters($filters);

        $result = $this->recomecar->assignAnalysisRecords(
            $filters,
            $userIds,
            $strategy,
            (int) (current_user()['id'] ?? 0)
        );

        (new AuditLogService())->record(
            'distribuiu_recomecar_analise',
            'recomecar_analise',
            null,
            json_encode([
                'filtros' => $filters,
                'estrategia' => $strategy,
                'periodo_inicio' => $periodStart,
                'periodo_fim' => $periodEnd,
                'usuarios' => $userIds,
                'resultado' => $result,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Session::flash(
            $result['assigned'] > 0 ? 'success' : 'warning',
            $result['assigned'] > 0
                ? 'Distribuicao concluida: ' . (int) $result['assigned'] . ' registro(s) atribuido(s) para ' . (int) $result['users'] . ' usuario(s).'
                : 'Nenhum registro foi encontrado no recorte atual para distribuicao.'
        );
        $this->redirect($redirect);
    }

    public function completeAnalysisQueue(): void
    {
        $user = current_user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        $redirect = '/gestor/recomecar/analise';
        $this->guardPost('gestor.recomecar.analysis.queue.complete.' . $userId, $redirect);

        $status = $this->recomecar->userAnalysisQueueStatus($userId);
        if ((int) ($status['total'] ?? 0) <= 0 || (int) ($status['abertas'] ?? 0) <= 0) {
            Session::flash('warning', 'Nao existe distribuicao aberta para concluir.');
            $this->redirect($redirect);
        }

        if ((int) ($status['pendentes'] ?? 0) > 0) {
            Session::flash('warning', 'Ainda existem ' . (int) $status['pendentes'] . ' registro(s) pendente(s) nesta distribuicao.');
            $this->redirect($redirect);
        }

        $updated = $this->recomecar->completeUserAnalysisQueue($userId);
        (new AuditLogService())->record(
            'concluiu_recomecar_fila_analise',
            'recomecar_analise',
            null,
            json_encode([
                'usuario_id' => $userId,
                'total' => (int) ($status['total'] ?? 0),
                'analisadas' => (int) ($status['analisadas'] ?? 0),
                'registros_atualizados' => $updated,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Session::flash('success', 'Analise concluida. A distribuicao foi encerrada para o seu usuario.');
        $this->redirect($redirect);
    }

    public function previewAnalysisDistribution(): void
    {
        if (!$this->canManageAnalysisAssignments()) {
            $this->json(['ok' => false, 'message' => 'Acesso negado.'], 403);
        }

        $params = is_array($_GET['filters'] ?? null) ? $_GET['filters'] : [];
        $filters = $this->analysisFiltersFromArray($params);
        $userIds = is_array($_GET['analistas_usuarios'] ?? null) ? $_GET['analistas_usuarios'] : [];
        $userIds = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $userIds
        ), static fn (int $id): bool => $id > 0));
        $strategy = (string) ($_GET['distribuicao_estrategia'] ?? '');
        $periodStart = $this->date($_GET['periodo_inicio'] ?? null);
        $periodEnd = $this->date($_GET['periodo_fim'] ?? null);

        if ($userIds === [] || (int) ($filters['acao_id'] ?? 0) <= 0 || !in_array($strategy, ['pares_impares', 'blocos'], true) || $periodStart === '' || $periodEnd === '') {
            $this->json([
                'ok' => true,
                'ready' => false,
                'message' => 'Selecione analistas, acao, periodo e forma de distribuicao.',
            ]);
        }

        if ($periodStart > $periodEnd) {
            $this->json([
                'ok' => false,
                'ready' => false,
                'message' => 'O periodo inicial nao pode ser maior que o periodo final.',
            ], 422);
        }

        $filters['data_inicio'] = $periodStart;
        $filters['data_fim'] = $periodEnd;
        $preview = $this->recomecar->previewAnalysisDistribution($filters, $userIds, $strategy);

        $this->json([
            'ok' => true,
            'ready' => true,
            'periodo' => [
                'inicio' => $periodStart,
                'fim' => $periodEnd,
            ],
            'estrategia' => $strategy,
            'preview' => $preview,
        ]);
    }

    public function sign(): void
    {
        $filters = $this->filtersFromArray($_POST);

        if (!$this->hasAppliedFilters($filters)) {
            Session::flash('warning', 'Aplique pelo menos um filtro antes de assinar o documento do Programa Recomeçar.');
            $this->redirect('/gestor/recomecar');
        }

        if (($filters['aptidao'] ?? 'apta') !== 'apta') {
            Session::flash('warning', 'Somente a relação de famílias aptas pode ser assinada para pagamento do Programa Recomeçar.');
            $this->redirect($this->filterUrl($filters));
        }

        $identity = $this->documentIdentity($filters);
        $this->guardPost('gestor.recomecar.sign.' . $identity['entity_id'], $this->filterUrl($filters));
        $coSigners = $this->selectedCoSigners();
        $signature = $this->buildDocumentSignature($filters, $identity, new \DateTimeImmutable(), $this->currentUserRecord(), $coSigners);

        (new AuditLogService())->record(
            'assinou_recomecar',
            self::DOCUMENT_TYPE,
            (int) $identity['entity_id'],
            json_encode($signature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->createCoSignatureRequests($filters, $identity, $signature, $coSigners);

        Session::flash(
            'success',
            $coSigners === []
                ? 'Documento do Programa Recomeçar assinado digitalmente. A impressão está liberada.'
                : 'Documento do Programa Recomeçar assinado pelo usuário principal. A impressão será liberada após autorização dos responsáveis pela conferência.'
        );
        $this->redirect($this->filterUrl($filters, true));
    }

    public function removeSignature(): void
    {
        $filters = $this->filtersFromArray($_POST);

        if (!$this->hasAppliedFilters($filters)) {
            Session::flash('warning', 'Aplique pelo menos um filtro antes de remover assinatura.');
            $this->redirect('/gestor/recomecar');
        }

        $identity = $this->documentIdentity($filters);
        $this->guardPost('gestor.recomecar.remove_signature.' . $identity['entity_id'], $this->filterUrl($filters));
        $signature = $this->latestSignature($identity);

        if ($signature === null) {
            Session::flash('warning', 'Este documento não possui assinatura ativa para remover.');
            $this->redirect($this->filterUrl($filters));
        }

        $principalUserId = (int) ($signature['usuario_id'] ?? 0);
        if ($principalUserId <= 0 || $principalUserId !== (int) (current_user()['id'] ?? 0)) {
            $this->abort(403);
        }

        (new AuditLogService())->record(
            'removeu_assinatura_recomecar',
            self::DOCUMENT_TYPE,
            (int) $identity['entity_id'],
            json_encode([
                'document_key' => $identity['document_key'],
                'removido_por' => $principalUserId,
                'removed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        (new CoassinaturaRepository())->cancelDocument(self::DOCUMENT_TYPE, (string) $identity['document_key']);

        Session::flash('success', 'Assinatura do documento Recomeçar removida. O documento pode ser assinado novamente.');
        $this->redirect($this->filterUrl($filters));
    }

    private function filters(): array
    {
        return $this->filtersFromArray($_GET);
    }

    private function analysisFilters(): array
    {
        return $this->analysisFiltersFromArray($_GET);
    }

    private function analysisFiltersFromArray(array $source): array
    {
        $filters = $this->filtersFromArray($source);
        $filters['aptidao'] = (string) ($source['aptidao'] ?? 'todas');
        if (!in_array($filters['aptidao'], ['apta', 'inapta', 'todas'], true)) {
            $filters['aptidao'] = 'todas';
        }

        $analysis = (string) ($source['analise'] ?? 'pendente');
        if (!in_array($analysis, ['pendente', 'analisado', 'todas'], true)) {
            $analysis = 'pendente';
        }
        $filters['analise'] = $analysis;

        $analystId = $this->positiveInt($source['analista_id'] ?? null);
        if ($analystId !== '') {
            $filters['analista_id'] = $analystId;
        }

        return $filters;
    }

    private function filtersFromArray(array $source): array
    {
        $acaoBusca = mb_substr(trim((string) ($source['acao_busca'] ?? '')), 0, 120);
        $analistaBusca = mb_substr(trim((string) ($source['analista_busca'] ?? '')), 0, 120);
        $acaoId = $this->positiveInt($source['acao_id'] ?? null);
        $aptidao = (string) ($source['aptidao'] ?? 'apta');

        if (!in_array($aptidao, ['apta', 'inapta', 'todas'], true)) {
            $aptidao = 'apta';
        }

        $statusEntrega = (string) ($source['status_entrega'] ?? '');
        if (!in_array($statusEntrega, ['', 'registrado', 'entregue', 'nao_entregue'], true)) {
            $statusEntrega = '';
        }

        $analise = (string) ($source['analise'] ?? '');
        if (!in_array($analise, ['', 'pendente', 'analisado', 'todas'], true)) {
            $analise = '';
        }

        if ($acaoId === '' && preg_match('/A[cç][aã]o\s+#(\d+)/iu', $acaoBusca, $matches) === 1) {
            $acaoId = $this->positiveInt($matches[1]);
        }

        return [
            'q' => mb_substr(trim((string) ($source['q'] ?? '')), 0, 120),
            'acao_id' => $acaoId,
            'acao_busca' => $acaoBusca,
            'localidade_busca' => mb_substr(trim((string) ($source['localidade_busca'] ?? '')), 0, 120),
            'aptidao' => $aptidao,
            'status_entrega' => $statusEntrega,
            'analise' => $analise === 'todas' ? '' : $analise,
            'analista_id' => $this->positiveInt($source['analista_id'] ?? null),
            'analista_busca' => $analistaBusca,
            'data_inicio' => $this->date($source['data_inicio'] ?? null),
            'data_fim' => $this->date($source['data_fim'] ?? null),
            '_aplicado' => $this->filterWasSubmitted($source) ? '1' : '',
        ];
    }

    private function hasAppliedFilters(array $filters): bool
    {
        if (($filters['_aplicado'] ?? '') === '1') {
            return true;
        }

        foreach (['q', 'acao_id', 'acao_busca', 'localidade_busca', 'status_entrega', 'analise', 'analista_id', 'analista_busca', 'data_inicio', 'data_fim'] as $key) {
            if ((string) ($filters[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function filterWasSubmitted(array $source): bool
    {
        if (($source['_aplicado'] ?? '') === '1') {
            return true;
        }

        foreach (['q', 'acao_id', 'acao_busca', 'localidade_busca', 'aptidao', 'status_entrega', 'analise', 'analista_id', 'analista_busca', 'data_inicio', 'data_fim'] as $key) {
            if (array_key_exists($key, $source)) {
                return true;
            }
        }

        return false;
    }

    private function hasAppliedAnalysisFilters(array $filters): bool
    {
        if (($filters['_aplicado'] ?? '') === '1') {
            return true;
        }

        foreach (['q', 'acao_id', 'acao_busca', 'localidade_busca', 'status_entrega', 'analista_id', 'analista_busca', 'data_inicio', 'data_fim'] as $key) {
            if ((string) ($filters[$key] ?? '') !== '') {
                return true;
            }
        }

        if (($filters['aptidao'] ?? 'todas') !== 'todas') {
            return true;
        }

        return ($filters['analise'] ?? 'pendente') !== 'pendente';
    }

    private function analysisVisibilityFilters(array $filters, array $currentUser): array
    {
        $currentUserId = (int) ($currentUser['id'] ?? 0);

        if ((string) ($currentUser['perfil'] ?? '') === 'administrador') {
            unset($filters['analista_usuario_id']);

            return $filters;
        }

        $filters['analista_usuario_id'] = $currentUserId;
        unset($filters['analista_id']);

        return $filters;
    }

    private function canManageAnalysisAssignments(): bool
    {
        return in_array((string) (current_user()['perfil'] ?? ''), ['administrador', 'gestor'], true);
    }

    private function canCurrentUserAnalyzeFamily(int $familiaId): bool
    {
        $user = current_user() ?? [];

        return $this->recomecar->canUserAnalyzeFamily(
            $familiaId,
            (int) ($user['id'] ?? 0),
            (string) ($user['perfil'] ?? '') === 'administrador'
        );
    }

    private function analysisUrlFromFilters(array $filters): string
    {
        $query = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');

        return '/gestor/recomecar/analise' . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    private function emptyIndicators(): array
    {
        return [
            'total_familias' => 0,
            'familias_aptas' => 0,
            'familias_inaptas' => 0,
        ];
    }

    private function emptyAnalysisSummary(): array
    {
        return [
            'total_familias' => 0,
            'familias_aptas' => 0,
            'familias_inaptas' => 0,
            'familias_analisadas' => 0,
            'familias_pendentes' => 0,
        ];
    }

    private function emptyCoSignatureStatus(): array
    {
        return [
            'total' => 0,
            'pendentes' => 0,
            'autorizados' => 0,
            'negados' => 0,
            'impressao_liberada' => true,
            'solicitacoes' => [],
        ];
    }

    private function documentIdentity(array $filters, ?array $details = null, ?array $summary = null): array
    {
        $details = $details ?? $this->recomecar->details($filters);
        $summary = $summary ?? $this->recomecar->indicators($filters);
        $payload = [
            'filters' => $filters,
            'summary' => $summary,
            'details' => array_map(
                static fn (array $item): array => [
                    'familia_id' => (int) ($item['familia_id'] ?? 0),
                    'beneficiario_nome' => (string) ($item['beneficiario_nome'] ?? ''),
                    'beneficiario_cpf' => (string) ($item['beneficiario_cpf'] ?? ''),
                    'beneficiario_rg' => (string) ($item['beneficiario_rg'] ?? ''),
                    'beneficiario_orgao_expedidor' => (string) ($item['beneficiario_orgao_expedidor'] ?? ''),
                    'beneficiario_sexo' => (string) ($item['beneficiario_sexo'] ?? ''),
                    'beneficiario_data_nascimento' => (string) ($item['beneficiario_data_nascimento'] ?? ''),
                    'aptidao' => (string) ($item['aptidao'] ?? ''),
                    'status_entrega' => (string) ($item['status_entrega'] ?? ''),
                    'total_entregas' => (int) ($item['total_entregas'] ?? 0),
                ],
                $details
            ),
        ];
        $documentKey = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'document_key' => $documentKey,
            'entity_id' => (hexdec(substr($documentKey, 0, 8)) & 0x7fffffff) ?: 1,
        ];
    }

    private function filterUrl(array $filters, bool $showSignature = false): string
    {
        $params = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');

        if ($showSignature) {
            $params['assinatura'] = '1';
        }

        return '/gestor/recomecar' . ($params !== [] ? '?' . http_build_query($params) : '');
    }

    private function latestSignature(array $identity): ?array
    {
        $log = (new LogRepository())->latestForEntityActions(
            ['assinou_recomecar', 'removeu_assinatura_recomecar'],
            self::DOCUMENT_TYPE,
            (int) $identity['entity_id']
        );

        if ($log === null || (string) ($log['acao'] ?? '') === 'removeu_assinatura_recomecar') {
            return null;
        }

        $decoded = json_decode((string) ($log['descricao'] ?? ''), true);

        if (!is_array($decoded) || !hash_equals((string) ($identity['document_key'] ?? ''), (string) ($decoded['document_key'] ?? ''))) {
            return null;
        }

        return $this->enrichSignatureWithCoSignatures($decoded + [
            'signed_at' => $log['criado_em'] ?? '',
            'usuario_id' => $log['usuario_id'] ?? null,
        ], (string) $identity['document_key']);
    }

    private function buildDocumentSignature(array $filters, array $identity, \DateTimeImmutable $generatedAt, array $currentUser, array $coSigners): array
    {
        $signers = [$this->signatureUserPayload($currentUser, 'assinante_principal')];
        $base = json_encode([
            'documento' => 'Programa Recomeçar',
            'document_key' => $identity['document_key'],
            'filters' => $filters,
            'generated_at' => $generatedAt->format('Y-m-d H:i:s'),
            'signers' => array_map(static fn (array $signer): int => (int) ($signer['usuario_id'] ?? 0), $signers),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'nome' => (string) ($currentUser['nome'] ?? ''),
            'cpf' => (string) ($currentUser['cpf'] ?? ''),
            'graduacao' => (string) ($currentUser['graduacao'] ?? ''),
            'nome_guerra' => (string) ($currentUser['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($currentUser['matricula_funcional'] ?? ''),
            'orgao' => (string) ($currentUser['orgao'] ?? ''),
            'unidade_setor' => (string) ($currentUser['unidade_setor'] ?? ''),
            'usuario_id' => (int) ($currentUser['id'] ?? 0),
            'signed_at' => $generatedAt->format('Y-m-d H:i:s'),
            'hash' => strtoupper(hash('sha256', (string) $base)),
            'documento' => 'Programa Recomeçar',
            'document_key' => $identity['document_key'],
            'assinantes' => $signers,
            'coassinantes_solicitados' => array_map(
                fn (array $coSigner): array => $this->signatureUserPayload($coSigner, 'responsavel_conferencia'),
                $coSigners
            ),
        ];
    }

    private function createCoSignatureRequests(array $filters, array $identity, array $signature, array $coSigners): void
    {
        (new CoassinaturaRepository())->replacePendingRequests([
            'documento_tipo' => self::DOCUMENT_TYPE,
            'documento_chave' => (string) $identity['document_key'],
            'entidade' => self::DOCUMENT_TYPE,
            'entidade_id' => (int) $identity['entity_id'],
            'titulo' => 'Programa Recomeçar',
            'descricao' => 'Documento de pagamento gerado por filtros operacionais em ' . date('d/m/Y H:i'),
            'url_documento' => $this->filterUrl($filters, true),
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
                'documento' => $signature['documento'] ?? 'Programa Recomeçar',
                'document_key' => $identity['document_key'],
                'hash' => $signature['hash'] ?? '',
                'filters' => $filters,
            ],
        ], $coSigners);
    }

    private function enrichSignatureWithCoSignatures(array $signature, string $documentKey): array
    {
        $repository = new CoassinaturaRepository();
        $status = $repository->statusSummary(self::DOCUMENT_TYPE, $documentKey);
        $primary = is_array($signature['assinantes'][0] ?? null) ? [$signature['assinantes'][0]] : [[
            'tipo' => 'assinante_principal',
            'usuario_id' => (int) ($signature['usuario_id'] ?? 0),
            'nome' => (string) ($signature['nome'] ?? ''),
            'cpf' => (string) ($signature['cpf'] ?? ''),
            'email' => (string) ($signature['email'] ?? ''),
            'telefone' => (string) ($signature['telefone'] ?? ''),
            'graduacao' => (string) ($signature['graduacao'] ?? ''),
            'nome_guerra' => (string) ($signature['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($signature['matricula_funcional'] ?? ''),
            'orgao' => (string) ($signature['orgao'] ?? ''),
            'unidade_setor' => (string) ($signature['unidade_setor'] ?? ''),
        ]];
        $authorized = array_map(static function (array $payload): array {
            $payload['tipo'] = 'responsavel_conferencia';

            return $payload;
        }, $repository->authorizedSignerPayloads(self::DOCUMENT_TYPE, $documentKey));

        $signature['assinantes'] = array_merge($primary, $authorized);
        $signature['coassinatura_status'] = $status;
        $signature['impressao_liberada'] = (bool) ($status['impressao_liberada'] ?? true);

        return $signature;
    }

    private function selectedCoSigners(): array
    {
        $postedIds = is_array($_POST['assinantes_usuarios'] ?? null) ? $_POST['assinantes_usuarios'] : [];
        $currentUserId = (int) (current_user()['id'] ?? 0);
        $ids = array_values(array_filter(array_map(static fn (mixed $id): int => (int) $id, $postedIds), static fn (int $id): bool => $id > 0 && $id !== $currentUserId));

        return array_values(array_filter(
            $this->usuarios->activeByIds($ids),
            static fn (array $user): bool => in_array((string) ($user['perfil'] ?? ''), ['gestor', 'administrador'], true)
        ));
    }

    private function signatureUsers(int $currentUserId): array
    {
        return array_values(array_filter(
            $this->usuarios->activeExcept($currentUserId),
            static fn (array $user): bool => in_array((string) ($user['perfil'] ?? ''), ['gestor', 'administrador'], true)
        ));
    }

    private function currentUserRecord(): array
    {
        $userId = (int) (current_user()['id'] ?? 0);

        if ($userId <= 0) {
            return current_user() ?? [];
        }

        return $this->usuarios->find($userId) ?? (current_user() ?? []);
    }

    private function signatureUserPayload(array $user, string $tipo): array
    {
        return [
            'usuario_id' => (int) ($user['id'] ?? 0),
            'tipo' => $tipo,
            'nome' => (string) ($user['nome'] ?? ''),
            'cpf' => (string) ($user['cpf'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'telefone' => (string) ($user['telefone'] ?? ''),
            'graduacao' => (string) ($user['graduacao'] ?? ''),
            'nome_guerra' => (string) ($user['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($user['matricula_funcional'] ?? ''),
            'orgao' => (string) ($user['orgao'] ?? ''),
            'unidade_setor' => (string) ($user['unidade_setor'] ?? ''),
        ];
    }

    private function guardPost(string $scope, string $failureRedirect): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Sessão expirada ou formulário inválido.');
            $this->redirect($failureRedirect);
        }

        $idempotency = (new IdempotenciaService())->validateAndReserve($_POST['_idempotency_token'] ?? null, $scope);

        if (!$idempotency['ok']) {
            Session::flash('warning', $idempotency['message']);
            $this->redirect($failureRedirect);
        }
    }

    private function requestedPage(): int
    {
        $page = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);

        return is_int($page) && $page > 0 ? $page : 1;
    }

    private function analysisInput(): array
    {
        return [
            'bairro_comunidade' => mb_substr(trim((string) ($_POST['bairro_comunidade'] ?? '')), 0, 180),
            'endereco' => mb_substr(trim((string) ($_POST['endereco'] ?? '')), 0, 255),
            'complemento' => mb_substr(trim((string) ($_POST['complemento'] ?? '')), 0, 180),
            'imovel' => trim((string) ($_POST['imovel'] ?? '')),
            'condicao_residencia' => trim((string) ($_POST['condicao_residencia'] ?? '')),
            'latitude' => trim((string) ($_POST['latitude'] ?? '')),
            'longitude' => trim((string) ($_POST['longitude'] ?? '')),
            'quantidade_familias' => trim((string) ($_POST['quantidade_familias'] ?? '1')),
            'responsavel_nome' => mb_substr(trim((string) ($_POST['responsavel_nome'] ?? '')), 0, 180),
            'responsavel_cpf' => mb_substr(trim((string) ($_POST['responsavel_cpf'] ?? '')), 0, 14),
            'responsavel_rg' => mb_substr(trim((string) ($_POST['responsavel_rg'] ?? '')), 0, 30),
            'responsavel_sexo' => trim((string) ($_POST['responsavel_sexo'] ?? '')),
            'responsavel_orgao_expedidor' => mb_substr(trim((string) ($_POST['responsavel_orgao_expedidor'] ?? '')), 0, 30),
            'data_nascimento' => trim((string) ($_POST['data_nascimento'] ?? '')),
            'telefone' => telefone_cadastro_format($_POST['telefone'] ?? ''),
            'email' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 180),
            'quantidade_integrantes' => trim((string) ($_POST['quantidade_integrantes'] ?? '1')),
            'possui_criancas' => isset($_POST['possui_criancas']) ? '1' : '',
            'possui_idosos' => isset($_POST['possui_idosos']) ? '1' : '',
            'possui_pcd' => isset($_POST['possui_pcd']) ? '1' : '',
            'possui_gestantes' => isset($_POST['possui_gestantes']) ? '1' : '',
            'renda_familiar' => trim((string) ($_POST['renda_familiar'] ?? '')),
            'perdas_bens_moveis' => mb_substr(trim((string) ($_POST['perdas_bens_moveis'] ?? '')), 0, 1000),
            'situacao_familia' => trim((string) ($_POST['situacao_familia'] ?? '')),
            'recebe_beneficio_social' => isset($_POST['recebe_beneficio_social']) ? '1' : '',
            'beneficio_social_nome' => isset($_POST['recebe_beneficio_social'])
                ? mb_substr(trim((string) ($_POST['beneficio_social_nome'] ?? '')), 0, 180)
                : '',
            'cadastro_concluido' => isset($_POST['cadastro_concluido']) ? '1' : '',
            'conclusao_observacoes' => mb_substr(trim((string) ($_POST['conclusao_observacoes'] ?? '')), 0, 1000),
            'representante_nome' => mb_substr(trim((string) ($_POST['representante_nome'] ?? '')), 0, 180),
            'representante_cpf' => mb_substr(trim((string) ($_POST['representante_cpf'] ?? '')), 0, 14),
            'representante_rg' => mb_substr(trim((string) ($_POST['representante_rg'] ?? '')), 0, 30),
            'representante_orgao_expedidor' => mb_substr(trim((string) ($_POST['representante_orgao_expedidor'] ?? '')), 0, 30),
            'representante_data_nascimento' => trim((string) ($_POST['representante_data_nascimento'] ?? '')),
            'representante_sexo' => trim((string) ($_POST['representante_sexo'] ?? '')),
            'representante_telefone' => telefone_cadastro_format($_POST['representante_telefone'] ?? ''),
            'representante_email' => mb_substr(trim((string) ($_POST['representante_email'] ?? '')), 0, 180),
        ];
    }

    private function analysisValidator(array $data): Validator
    {
        $validator = (new Validator())
            ->required('bairro_comunidade', $data['bairro_comunidade'], 'Bairro/comunidade')
            ->max('bairro_comunidade', $data['bairro_comunidade'], 180, 'Bairro/comunidade')
            ->required('endereco', $data['endereco'], 'Endereco')
            ->max('endereco', $data['endereco'], 255, 'Endereco')
            ->max('complemento', $data['complemento'], 180, 'Complemento')
            ->integer('quantidade_familias', $data['quantidade_familias'], 'Quantidade de familias')
            ->minInt('quantidade_familias', $data['quantidade_familias'], 1, 'Quantidade de familias')
            ->decimalRange('latitude', $data['latitude'], -90, 90, 'Latitude')
            ->decimalRange('longitude', $data['longitude'], -180, 180, 'Longitude')
            ->required('responsavel_nome', $data['responsavel_nome'], 'Responsavel familiar')
            ->max('responsavel_nome', $data['responsavel_nome'], 180, 'Responsavel familiar')
            ->max('responsavel_cpf', $data['responsavel_cpf'], 14, 'CPF do responsavel')
            ->max('responsavel_rg', $data['responsavel_rg'], 30, 'RG')
            ->max('responsavel_orgao_expedidor', $data['responsavel_orgao_expedidor'], 30, 'Orgao expedidor')
            ->date('data_nascimento', $data['data_nascimento'], 'Data de nascimento')
            ->max('telefone', $data['telefone'], 30, 'Telefone')
            ->email('email', $data['email'], 'E-mail')
            ->max('email', $data['email'], 180, 'E-mail')
            ->integer('quantidade_integrantes', $data['quantidade_integrantes'], 'Quantidade de integrantes')
            ->minInt('quantidade_integrantes', $data['quantidade_integrantes'], 1, 'Quantidade de integrantes')
            ->max('perdas_bens_moveis', $data['perdas_bens_moveis'], 1000, 'Perdas de bens moveis')
            ->max('beneficio_social_nome', $data['beneficio_social_nome'], 180, 'Beneficio social')
            ->max('conclusao_observacoes', $data['conclusao_observacoes'], 1000, 'Observacoes')
            ->max('representante_nome', $data['representante_nome'], 180, 'Representante')
            ->max('representante_cpf', $data['representante_cpf'], 14, 'CPF do representante')
            ->max('representante_rg', $data['representante_rg'], 30, 'RG do representante')
            ->max('representante_orgao_expedidor', $data['representante_orgao_expedidor'], 30, 'Orgao expedidor do representante')
            ->date('representante_data_nascimento', $data['representante_data_nascimento'], 'Data de nascimento do representante')
            ->max('representante_telefone', $data['representante_telefone'], 30, 'Telefone do representante')
            ->email('representante_email', $data['representante_email'], 'E-mail do representante')
            ->max('representante_email', $data['representante_email'], 180, 'E-mail do representante');

        if ($data['imovel'] !== '') {
            $validator->in('imovel', $data['imovel'], self::IMOVEL_OPTIONS, 'Imovel');
        }

        if ($data['condicao_residencia'] !== '') {
            $validator->in('condicao_residencia', $data['condicao_residencia'], self::CONDICAO_RESIDENCIA_OPTIONS, 'Condicao da residencia');
        }

        if ($this->hasCpfValue($data['responsavel_cpf'])) {
            if (!$this->hasCompleteCpf($data['responsavel_cpf'])) {
                $validator->add('responsavel_cpf', 'CPF do responsavel deve conter 11 digitos ou ficar em branco.');
            } else {
                $validator->cpf('responsavel_cpf', $data['responsavel_cpf'], 'CPF do responsavel');
            }
        }

        if ($data['responsavel_sexo'] !== '') {
            $validator->in('responsavel_sexo', $data['responsavel_sexo'], self::SEXO_OPTIONS, 'Sexo do responsavel');
        }

        if ($data['renda_familiar'] !== '') {
            $validator->in('renda_familiar', $data['renda_familiar'], self::RENDA_OPTIONS, 'Renda familiar');
        }

        if ($data['situacao_familia'] !== '') {
            $validator->in('situacao_familia', $data['situacao_familia'], self::SITUACAO_OPTIONS, 'Situacao da familia');
        }

        if ($this->hasCpfValue($data['representante_cpf'])) {
            if (!$this->hasCompleteCpf($data['representante_cpf'])) {
                $validator->add('representante_cpf', 'CPF do representante deve conter 11 digitos ou ficar em branco.');
            } else {
                $validator->cpf('representante_cpf', $data['representante_cpf'], 'CPF do representante');
            }
        }

        if ($data['representante_sexo'] !== '') {
            $validator->in('representante_sexo', $data['representante_sexo'], self::SEXO_OPTIONS, 'Sexo do representante');
        }

        $this->validateWhatsappPhone($validator, 'telefone', $data['telefone'], 'Telefone do responsavel');
        $this->validateWhatsappPhone($validator, 'representante_telefone', $data['representante_telefone'], 'Telefone do representante');

        return $validator;
    }

    private function hasCpfValue(string $value): bool
    {
        return strlen(preg_replace('/\D+/', '', $value) ?? '') > 0;
    }

    private function hasCompleteCpf(string $value): bool
    {
        return strlen(preg_replace('/\D+/', '', $value) ?? '') === 11;
    }

    private function analysisValidationMessages(array $errors): array
    {
        $messages = [];

        foreach ($errors as $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $text = trim((string) $message);

                if ($text !== '') {
                    $messages[] = $text;
                }
            }
        }

        return array_values(array_unique($messages));
    }

    private function validateWhatsappPhone(Validator $validator, string $field, string $value, string $label): void
    {
        if (trim($value) === '') {
            return;
        }

        if (telefone_cadastro_digits($value) === '') {
            $validator->add($field, $label . ' deve conter DDD e numero valido para WhatsApp.');
        }
    }

    private function validateCpfUniquenessInOpenAction(Validator $validator, array $record, array $data, int $familiaId): void
    {
        $conflict = $this->familias->findCpfConflictInOpenAction(
            (int) ($record['acao_id'] ?? 0),
            [$data['responsavel_cpf'], $data['representante_cpf']],
            $familiaId
        );

        if ($conflict !== null) {
            $validator->add('responsavel_cpf', 'Ja existe familia cadastrada com este CPF na mesma acao aberta. Protocolo: ' . (string) ($conflict['protocolo'] ?? ''));
        }
    }

    private function analysisRedirectFromPost(int $familiaId, bool $openEdit = false): string
    {
        $params = is_array($_POST['filters'] ?? null) ? $_POST['filters'] : [];
        $normalized = $this->analysisFiltersFromArray($params);
        $analysis = (string) ($params['analise'] ?? 'pendente');

        if (!in_array($analysis, ['pendente', 'analisado', 'todas'], true)) {
            $analysis = 'pendente';
        }

        $normalized['analise'] = $analysis;
        $page = filter_var($params['pagina'] ?? null, FILTER_VALIDATE_INT);
        if (is_int($page) && $page > 1) {
            $normalized['pagina'] = (string) $page;
        }

        $query = array_filter($normalized, static fn (mixed $value): bool => (string) $value !== '');

        if ($openEdit) {
            $query['editar'] = (string) $familiaId;
        }

        return '/gestor/recomecar/analise' . ($query !== [] ? '?' . http_build_query($query) : '') . '#familia-' . $familiaId;
    }

    private function analysisChanges(array $before, array $after): array
    {
        $fields = [
            'bairro_comunidade' => 'Bairro/comunidade',
            'endereco' => 'Endereco',
            'complemento' => 'Complemento',
            'imovel' => 'Imovel',
            'condicao_residencia' => 'Condicao da residencia',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
            'quantidade_familias' => 'Quantidade de familias',
            'responsavel_nome' => 'Responsavel',
            'responsavel_cpf' => 'CPF do responsavel',
            'responsavel_rg' => 'RG do responsavel',
            'responsavel_sexo' => 'Sexo do responsavel',
            'responsavel_orgao_expedidor' => 'Orgao expedidor',
            'data_nascimento' => 'Data de nascimento',
            'telefone' => 'Telefone',
            'email' => 'E-mail',
            'quantidade_integrantes' => 'Quantidade de integrantes',
            'possui_criancas' => 'Possui criancas',
            'possui_idosos' => 'Possui idosos',
            'possui_pcd' => 'Possui PCD',
            'possui_gestantes' => 'Possui gestantes',
            'renda_familiar' => 'Renda familiar',
            'perdas_bens_moveis' => 'Perdas de bens moveis',
            'situacao_familia' => 'Situacao da familia',
            'recebe_beneficio_social' => 'Recebe beneficio social',
            'beneficio_social_nome' => 'Beneficio social',
            'cadastro_concluido' => 'Cadastro concluido',
            'conclusao_observacoes' => 'Observacoes',
            'representante_nome' => 'Representante',
            'representante_cpf' => 'CPF do representante',
            'representante_rg' => 'RG do representante',
            'representante_orgao_expedidor' => 'Orgao expedidor do representante',
            'representante_data_nascimento' => 'Nascimento do representante',
            'representante_sexo' => 'Sexo do representante',
            'representante_telefone' => 'Telefone do representante',
            'representante_email' => 'E-mail do representante',
        ];
        $changes = [];

        foreach ($fields as $field => $label) {
            $old = $this->normalizedComparable($before[$field] ?? '');
            $new = $this->normalizedComparable($after[$field] ?? '');

            if ($old === $new) {
                continue;
            }

            $changes[] = [
                'campo' => $field,
                'rotulo' => $label,
                'antes' => $old,
                'depois' => $new,
            ];
        }

        return $changes;
    }

    private function normalizedComparable(mixed $value): string
    {
        return $value === null ? '' : trim((string) $value);
    }

    private function positiveInt(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }

        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0 ? $value : '';
    }

    private function date(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
