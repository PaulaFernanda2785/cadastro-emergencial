<?php

declare(strict_types=1);

namespace App\Controllers\Gestor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\EntregaAjudaRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\ResidenciaRepository;
use App\Repositories\TipoAjudaRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;

final class EntregaAjudaController extends Controller
{
    public function __construct(
        private readonly EntregaAjudaRepository $entregas = new EntregaAjudaRepository(),
        private readonly FamiliaRepository $familias = new FamiliaRepository(),
        private readonly TipoAjudaRepository $tipos = new TipoAjudaRepository(),
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository(),
        private readonly ResidenciaRepository $residencias = new ResidenciaRepository()
    ) {
    }

    public function index(): void
    {
        $filters = $this->filters();
        $page = max(1, (int) ($_GET['pagina'] ?? 1));
        $perPage = 10;
        $total = $this->entregas->countSearch($filters);

        $this->view('gestor.entregas.index', [
            'title' => 'Entregas de ajuda',
            'entregas' => $this->entregas->search($filters, $perPage, ($page - 1) * $perPage),
            'summary' => $this->entregas->summary($filters),
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $perPage)),
            ],
            'acoes' => $this->acoes->all(),
            'residencias' => $this->residencias->optionsAll(),
            'familias' => $this->familias->deliveryHistoryOptions(),
            'tipos' => $this->tipos->active(),
            'activeDeliveryPage' => 'historico',
        ]);
    }

    public function validationPage(): void
    {
        $this->view('gestor.entregas.validacao', [
            'title' => 'Validar comprovante',
            'activeDeliveryPage' => 'validacao',
        ]);
    }

    public function batch(): void
    {
        $batchFilters = $this->batchFilters();

        $this->view('gestor.entregas.lote', [
            'title' => 'Entrega em lote',
            'batchFilters' => $batchFilters,
            'batchHasFilters' => $this->hasBatchFilters($batchFilters),
            'batchFamilies' => $this->hasBatchFilters($batchFilters) ? $this->familias->deliveryCandidates($batchFilters) : [],
            'acoes' => $this->acoes->all(),
            'residencias' => $this->residencias->optionsByOpenActions(),
            'tipos' => $this->tipos->active(),
            'activeDeliveryPage' => 'lote',
        ]);
    }

    public function create(string $familiaId): void
    {
        $familia = $this->findFamilia((int) $familiaId);
        $this->form($familia, $this->emptyInput(), []);
    }

    public function receipt(string $id): void
    {
        $entrega = $this->entregas->find((int) $id);

        if ($entrega === null) {
            $this->abort(404);
        }

        $this->view('gestor.entregas.receipt', [
            'title' => 'Comprovante ' . $entrega['comprovante_codigo'],
            'entrega' => $entrega,
            'generatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function validateReceiptQuery(): void
    {
        $code = $this->extractReceiptCode(trim((string) ($_GET['codigo'] ?? '')));

        if ($code === '') {
            Session::flash('warning', 'Informe ou leia o codigo do comprovante de cadastro familiar.');
            $this->redirect('/gestor/entregas/validacao');
        }

        $this->validateReceipt($code);
    }

    public function validateReceipt(string $codigo): void
    {
        $code = $this->extractReceiptCode($codigo);
        $familia = $this->familias->findByReceiptCode($code);

        if ($familia === null) {
            Session::flash('error', 'Comprovante de cadastro familiar invalido ou nao localizado.');
            $this->redirect('/gestor/entregas/validacao');
        }

        (new AuditLogService())->record('validou_comprovante_familia', 'familias', (int) $familia['id'], $code);
        Session::flash('success', 'Cadastro familiar validado pelo QR. Confira os dados e registre a entrega.');

        $this->redirect('/gestor/familias/' . (int) $familia['id'] . '/entregas/novo');
    }

    public function store(string $familiaId): void
    {
        $familia = $this->findFamilia((int) $familiaId);
        $this->guardPost('gestor.entregas.store.' . (int) $familiaId, '/gestor/familias/' . (int) $familiaId . '/entregas/novo');

        $data = $this->input();
        $validator = $this->validator($data);

        foreach ($data['tipo_ajuda_ids'] as $tipoId) {
            $tipo = $this->tipos->find((int) $tipoId);

            if ($tipo === null || (int) ($tipo['ativo'] ?? 0) !== 1) {
                $validator->add('tipo_ajuda_ids', 'Selecione apenas tipos de ajuda ativos.');
                break;
            }
        }

        if ($validator->fails()) {
            $this->form($familia, $data, $validator->errors());
            return;
        }

        $data['familia_id'] = (int) $familiaId;
        $data['entregue_por'] = (int) (current_user()['id'] ?? 0);
        $createdIds = [];
        $groupCode = $this->generateReceiptCode();
        $hasMultipleItems = count($data['tipo_ajuda_ids']) > 1;

        foreach ($data['tipo_ajuda_ids'] as $index => $tipoId) {
            $row = $data;
            $row['tipo_ajuda_id'] = (int) $tipoId;
            $row['grupo_comprovante_codigo'] = $groupCode;
            $row['comprovante_codigo'] = $hasMultipleItems ? $groupCode . '-ITEM-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) : $groupCode;

            $id = $this->entregas->create($row);
            $createdIds[] = $id;
            (new AuditLogService())->record('registrou_entrega', 'entregas_ajuda', $id, $groupCode);
        }

        Session::flash('success', 'Entrega registrada com comprovante ' . $groupCode . '.');

        $this->redirect('/gestor/entregas/' . (int) $createdIds[0] . '/comprovante');
    }

    public function batchStore(): void
    {
        $this->guardPost('gestor.entregas.lote', '/gestor/entregas/lote');

        $familiaIds = $this->integerList($_POST['familia_ids'] ?? []);
        $tipoIds = $this->integerList($_POST['tipo_ajuda_ids'] ?? []);
        $quantidade = str_replace(',', '.', trim((string) ($_POST['quantidade'] ?? '1')));
        $observacao = trim((string) ($_POST['observacao'] ?? ''));
        $validator = (new Validator())
            ->required('quantidade', $quantidade, 'Quantidade')
            ->decimalRange('quantidade', $quantidade, 0.01, 999999.99, 'Quantidade')
            ->max('observacao', $observacao, 500, 'Observacao');

        if ($familiaIds === []) {
            $validator->add('familia_ids', 'Selecione pelo menos uma familia para entrega em lote.');
        }

        if ($tipoIds === []) {
            $validator->add('tipo_ajuda_ids', 'Selecione pelo menos um tipo de ajuda.');
        }

        foreach ($tipoIds as $tipoId) {
            $tipo = $this->tipos->find($tipoId);

            if ($tipo === null || (int) ($tipo['ativo'] ?? 0) !== 1) {
                $validator->add('tipo_ajuda_ids', 'Selecione apenas tipos de ajuda ativos.');
                break;
            }
        }

        if ($validator->fails()) {
            Session::flash('error', implode(' ', array_map(static fn (array $messages): string => $messages[0] ?? '', $validator->errors())));
            $this->redirect('/gestor/entregas/lote');
        }

        $created = 0;
        $userId = (int) (current_user()['id'] ?? 0);

        foreach ($familiaIds as $familiaId) {
            if ($this->familias->find($familiaId) === null) {
                continue;
            }

            $groupCode = $this->generateReceiptCode();
            $hasMultipleItems = count($tipoIds) > 1;

            foreach ($tipoIds as $index => $tipoId) {
                $data = [
                    'familia_id' => $familiaId,
                    'tipo_ajuda_id' => $tipoId,
                    'quantidade' => $quantidade,
                    'entregue_por' => $userId,
                    'comprovante_codigo' => $hasMultipleItems ? $groupCode . '-ITEM-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) : $groupCode,
                    'grupo_comprovante_codigo' => $groupCode,
                    'observacao' => $observacao,
                ];
                $id = $this->entregas->create($data);
                $created++;
                (new AuditLogService())->record('registrou_entrega_lote', 'entregas_ajuda', $id, $groupCode);
            }
        }

        Session::flash('success', $created . ' item(ns) registrado(s) em lote, agrupados por familia no historico.');
        $this->redirect('/gestor/entregas');
    }

    private function findFamilia(int $id): array
    {
        $familia = $this->familias->find($id);

        if ($familia === null) {
            $this->abort(404);
        }

        return $familia;
    }

    private function form(array $familia, array $entrega, array $errors): void
    {
        $this->view('gestor.entregas.form', [
            'title' => 'Registrar entrega',
            'familia' => $familia,
            'entrega' => $entrega,
            'tipos' => $this->tipos->active(),
            'historico' => $this->entregas->byFamilia((int) $familia['id']),
            'errors' => $errors,
            'action' => '/gestor/familias/' . $familia['id'] . '/entregas',
        ]);
    }

    private function emptyInput(): array
    {
        return [
            'tipo_ajuda_ids' => [],
            'quantidade' => '1',
            'observacao' => '',
        ];
    }

    private function input(): array
    {
        return [
            'tipo_ajuda_ids' => $this->integerList($_POST['tipo_ajuda_ids'] ?? []),
            'quantidade' => str_replace(',', '.', trim((string) ($_POST['quantidade'] ?? '1'))),
            'observacao' => trim((string) ($_POST['observacao'] ?? '')),
        ];
    }

    private function validator(array $data): Validator
    {
        $validator = (new Validator())
            ->required('quantidade', $data['quantidade'], 'Quantidade')
            ->decimalRange('quantidade', $data['quantidade'], 0.01, 999999.99, 'Quantidade')
            ->max('observacao', $data['observacao'], 500, 'Observacao');

        if (($data['tipo_ajuda_ids'] ?? []) === []) {
            $validator->add('tipo_ajuda_ids', 'Selecione pelo menos um tipo de ajuda.');
        }

        return $validator;
    }

    private function generateReceiptCode(): string
    {
        return 'ENT-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function filters(): array
    {
        $acaoBusca = trim((string) ($_GET['acao_busca'] ?? ''));
        $acaoId = $this->positiveInt($_GET['acao_id'] ?? '');

        if ($acaoId === '' && preg_match('/Acao\s+#(\d+)/i', $acaoBusca, $matches) === 1) {
            $acaoId = $this->positiveInt($matches[1]);
        }

        return [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'acao_id' => $acaoId,
            'acao_busca' => $acaoBusca,
            'residencia_id' => trim((string) ($_GET['residencia_id'] ?? '')),
            'residencia_busca' => trim((string) ($_GET['residencia_busca'] ?? '')),
            'familia_id' => trim((string) ($_GET['familia_id'] ?? '')),
            'familia_busca' => trim((string) ($_GET['familia_busca'] ?? '')),
            'tipo_ajuda_id' => trim((string) ($_GET['tipo_ajuda_id'] ?? '')),
            'data_inicio' => trim((string) ($_GET['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_GET['data_fim'] ?? '')),
        ];
    }

    private function batchFilters(): array
    {
        $acaoBusca = trim((string) ($_GET['lote_acao_busca'] ?? ''));
        $acaoId = $this->positiveInt($_GET['lote_acao_id'] ?? '');

        if ($acaoId === '' && preg_match('/Acao\s+#(\d+)/i', $acaoBusca, $matches) === 1) {
            $acaoId = $this->positiveInt($matches[1]);
        }

        return [
            'q' => trim((string) ($_GET['lote_q'] ?? '')),
            'acao_id' => $acaoId,
            'acao_busca' => $acaoBusca,
            'residencia_id' => trim((string) ($_GET['lote_residencia_id'] ?? '')),
            'residencia_busca' => trim((string) ($_GET['lote_residencia_busca'] ?? '')),
            'data_inicio' => trim((string) ($_GET['lote_data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_GET['lote_data_fim'] ?? '')),
        ];
    }

    private function hasBatchFilters(array $filters): bool
    {
        foreach (['q', 'acao_id', 'acao_busca', 'residencia_id', 'residencia_busca', 'data_inicio', 'data_fim'] as $key) {
            if (($filters[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function integerList(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];

        return array_values(array_unique(array_filter(array_map(static function (mixed $item): int {
            return max(0, (int) $item);
        }, $items))));
    }

    private function positiveInt(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }

        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0 ? $value : '';
    }

    private function extractReceiptCode(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $path = parse_url($value, PHP_URL_PATH);

            if (is_string($path) && preg_match('#/gestor/entregas/validar/([^/]+)$#', $path, $matches) === 1) {
                return strtoupper(rawurldecode($matches[1]));
            }
        }

        return strtoupper($value);
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
