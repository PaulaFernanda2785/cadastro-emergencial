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
use App\Services\TicketEmailService;
use Throwable;

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
            'title' => 'Validar QR para entrega',
            'activeDeliveryPage' => 'validacao',
            'validationMode' => 'entregar',
        ]);
    }

    public function registerBatch(): void
    {
        $batchFilters = $this->batchFilters();

        $this->view('gestor.entregas.lote', [
            'title' => 'Registrar em lote',
            'batchFilters' => $batchFilters,
            'batchHasFilters' => $this->hasBatchFilters($batchFilters),
            'batchFamilies' => $this->hasBatchFilters($batchFilters) ? $this->familias->deliveryCandidates($batchFilters) : [],
            'acoes' => $this->acoes->all(),
            'residencias' => $this->residencias->optionsByOpenActions(),
            'tipos' => $this->tipos->active(),
            'activeDeliveryPage' => 'registrar',
            'deliveryMode' => 'registrar',
            'formAction' => '/gestor/entregas/registrar/lote',
        ]);
    }

    public function batch(): void
    {
        $batchFilters = $this->batchFilters();
        $batchFilters['status_entrega'] = 'registrado';

        $this->view('gestor.entregas.lote', [
            'title' => 'Entrega em lote',
            'batchFilters' => $batchFilters,
            'batchHasFilters' => $this->hasBatchFilters($batchFilters),
            'batchFamilies' => $this->hasBatchFilters($batchFilters) ? $this->familias->deliveryCandidates($batchFilters) : [],
            'acoes' => $this->acoes->all(),
            'residencias' => $this->residencias->optionsByOpenActions(),
            'tipos' => $this->tipos->active(),
            'activeDeliveryPage' => 'lote',
            'deliveryMode' => 'entregar',
            'formAction' => '/gestor/entregas/lote',
        ]);
    }

    public function create(string $familiaId): void
    {
        $familia = $this->findFamilia((int) $familiaId);
        $this->form($familia, $this->emptyInput(), []);
    }

    public function confirm(string $familiaId): void
    {
        $familia = $this->findFamilia((int) $familiaId);
        $pending = $this->entregas->pendingRegistrationsByFamilia((int) $familia['id']);

        if ($pending === []) {
            Session::flash('warning', 'Esta familia nao possui itens registrados pendentes de entrega.');
            $this->redirect('/gestor/entregas/validacao');
        }

        $this->view('gestor.entregas.confirmar', [
            'title' => 'Confirmar entrega',
            'familia' => $familia,
            'registros' => $pending,
            'action' => '/gestor/familias/' . (int) $familia['id'] . '/entregas/confirmar',
            'activeDeliveryPage' => 'validacao',
        ]);
    }

    public function confirmStore(string $familiaId): void
    {
        $familia = $this->findFamilia((int) $familiaId);
        $this->guardPost('gestor.entregas.confirmar.' . (int) $familiaId, '/gestor/familias/' . (int) $familiaId . '/entregas/confirmar');

        $updated = $this->entregas->deliverRegisteredForFamily((int) $familia['id'], (int) (current_user()['id'] ?? 0));

        if ($updated <= 0) {
            Session::flash('warning', 'Nenhum item registrado pendente foi encontrado para esta familia.');
            $this->redirect('/gestor/entregas/validacao');
        }

        (new AuditLogService())->record('confirmou_entrega_registrada', 'familias', (int) $familia['id'], 'itens=' . $updated);
        Session::flash('success', 'Entrega confirmada com ' . $updated . ' item(ns) registrado(s).');
        $this->redirect('/gestor/entregas');
    }

    public function receipt(string $id): void
    {
        $entrega = $this->entregas->find((int) $id);

        if ($entrega === null) {
            $this->abort(404);
        }

        $itens = array_map(
            static function (array $item): string {
                $line = '- ' . (string) $item['tipo_ajuda_nome'] . ': ' . number_format((float) $item['quantidade'], 2, ',', '.') . ' ' . (string) $item['unidade_medida'];
                $observacao = trim((string) ($item['observacao'] ?? ''));

                return $observacao !== '' ? $line . ' | Obs.: ' . $observacao : $line;
            },
            $entrega['itens'] ?? []
        );
        $whatsappText = implode("\n", array_filter([
            'Comprovante de entrega - Cadastro Emergencial',
            'Responsavel: ' . (string) $entrega['responsavel_nome'],
            'CPF: ' . (string) $entrega['responsavel_cpf'],
            'Codigo: ' . (string) $entrega['comprovante_codigo'],
            $itens !== [] ? 'Itens:' . "\n" . implode("\n", $itens) : '',
        ]));
        $whatsappDestino = familia_whatsapp_destino($entrega);

        $this->view('gestor.entregas.receipt', [
            'title' => 'Comprovante ' . $entrega['comprovante_codigo'],
            'entrega' => $entrega,
            'whatsappAppUrl' => whatsapp_app_url($whatsappDestino['telefone'], $whatsappText),
            'whatsappUrl' => whatsapp_direct_url($whatsappDestino['telefone'], $whatsappText),
            'whatsappFallbackAppUrl' => whatsapp_app_url($whatsappDestino['fallback_telefone'], $whatsappText),
            'whatsappFallbackUrl' => whatsapp_direct_url($whatsappDestino['fallback_telefone'], $whatsappText),
            'whatsappTarget' => $whatsappDestino,
            'whatsappText' => $whatsappText,
            'generatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function emailReceipt(string $id): void
    {
        $entrega = $this->entregas->find((int) $id);

        if ($entrega === null) {
            $this->abort(404);
        }

        $redirect = '/gestor/entregas/' . (int) $entrega['id'] . '/comprovante';
        $this->guardPost('gestor.entregas.receipt.email.' . (int) $entrega['id'], $redirect);

        try {
            $result = (new TicketEmailService())->sendDeliveryReceipt($entrega, new \DateTimeImmutable());
        } catch (Throwable $exception) {
            error_log('Falha ao enviar comprovante de entrega por e-mail: ' . $exception->getMessage());
            Session::flash('error', 'Nao foi possivel enviar o comprovante por e-mail.');
            $this->redirect($redirect);
        }

        if ($result['ok']) {
            (new AuditLogService())->record('enviou_comprovante_entrega_email', 'entregas_ajuda', (int) $entrega['id'], (string) $entrega['comprovante_codigo']);
            Session::flash('success', $result['message']);
        } else {
            Session::flash('warning', $result['message']);
        }

        $this->redirect($redirect);
    }

    public function validateReceiptQuery(): void
    {
        $code = $this->extractReceiptCode(trim((string) ($_GET['codigo'] ?? '')));

        if ($code === '') {
            Session::flash('warning', 'Informe ou leia o código do comprovante de cadastro familiar.');
            $this->redirect('/gestor/entregas/validacao');
        }

        $this->validateReceipt($code);
    }

    public function validateRegistrationReceiptQuery(): void
    {
        $code = $this->extractReceiptCode(trim((string) ($_GET['codigo'] ?? '')));

        if ($code === '') {
            Session::flash('warning', 'Informe ou leia o codigo do comprovante de cadastro familiar.');
            $this->redirect('/gestor/entregas/registrar');
        }

        $this->validateRegistrationReceipt($code);
    }

    public function validateReceipt(string $codigo): void
    {
        $code = $this->extractReceiptCode($codigo);
        $familia = $this->familias->findByReceiptCode($code);

        if ($familia === null) {
            Session::flash('error', 'Comprovante de cadastro familiar inválido ou não localizado.');
            $this->redirect('/gestor/entregas/validacao');
        }

        $pending = $this->entregas->pendingRegistrationsByFamilia((int) $familia['id']);

        if ($pending === []) {
            Session::flash('warning', 'Esta familia ainda nao possui itens registrados para entrega.');
            $this->redirect('/gestor/entregas/validacao');
        }

        (new AuditLogService())->record('validou_qr_entrega', 'familias', (int) $familia['id'], $code);
        Session::flash('success', 'QR validado. Confira os itens registrados antes de confirmar a entrega.');

        $this->redirect('/gestor/familias/' . (int) $familia['id'] . '/entregas/confirmar');
    }

    public function validateRegistrationReceipt(string $codigo): void
    {
        $code = $this->extractReceiptCode($codigo);
        $familia = $this->familias->findByReceiptCode($code);

        if ($familia === null) {
            Session::flash('error', 'Comprovante de cadastro familiar invalido ou nao localizado.');
            $this->redirect('/gestor/entregas/registrar');
        }

        (new AuditLogService())->record('validou_qr_registro_entrega', 'familias', (int) $familia['id'], $code);
        Session::flash('success', 'Cadastro familiar validado. Registre os itens previstos para esta familia.');

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
        $data['status_operacional'] = 'registrado';
        $groupCode = $this->generateReceiptCode();
        $hasMultipleItems = count($data['tipo_ajuda_ids']) > 1;

        foreach ($data['tipo_ajuda_ids'] as $index => $tipoId) {
            $item = $data['itens'][(int) $tipoId];
            $row = $data;
            $row['tipo_ajuda_id'] = (int) $tipoId;
            $row['quantidade'] = $item['quantidade'];
            $row['observacao'] = $item['observacao'];
            $row['grupo_comprovante_codigo'] = $groupCode;
            $row['comprovante_codigo'] = $hasMultipleItems ? $groupCode . '-ITEM-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) : $groupCode;

            $id = $this->entregas->create($row);
            (new AuditLogService())->record('registrou_itens_entrega', 'entregas_ajuda', $id, $groupCode);
        }

        Session::flash('success', 'Itens registrados com codigo ' . $groupCode . '. A entrega fica disponivel apos este registro.');

        $this->redirect('/gestor/entregas');
    }

    public function registerBatchStore(): void
    {
        $this->guardPost('gestor.entregas.registrar.lote', '/gestor/entregas/registrar');

        $familiaIds = $this->integerList($_POST['familia_ids'] ?? []);
        $tipoIds = $this->integerList($_POST['tipo_ajuda_ids'] ?? []);
        $itens = $this->deliveryItemsInput($tipoIds);
        $validator = new Validator();
        $this->validateDeliveryItems($validator, ['tipo_ajuda_ids' => $tipoIds, 'itens' => $itens]);

        if ($familiaIds === []) {
            $validator->add('familia_ids', 'Selecione pelo menos uma familia para registro em lote.');
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
            $this->redirect('/gestor/entregas/registrar');
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
                $item = $itens[$tipoId];
                $data = [
                    'familia_id' => $familiaId,
                    'tipo_ajuda_id' => $tipoId,
                    'quantidade' => $item['quantidade'],
                    'status_operacional' => 'registrado',
                    'entregue_por' => $userId,
                    'comprovante_codigo' => $hasMultipleItems ? $groupCode . '-ITEM-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) : $groupCode,
                    'grupo_comprovante_codigo' => $groupCode,
                    'observacao' => $item['observacao'],
                ];
                $id = $this->entregas->create($data);
                $created++;
                (new AuditLogService())->record('registrou_itens_lote', 'entregas_ajuda', $id, $groupCode);
            }
        }

        Session::flash('success', $created . ' item(ns) registrado(s) em lote para entrega posterior.');
        $this->redirect('/gestor/entregas');
    }

    public function batchStore(): void
    {
        $this->guardPost('gestor.entregas.lote', '/gestor/entregas/lote');

        $familiaIds = $this->integerList($_POST['familia_ids'] ?? []);

        if ($familiaIds === []) {
            Session::flash('error', 'Selecione pelo menos uma familia com registro pendente.');
            $this->redirect('/gestor/entregas/lote');
        }

        $updated = $this->entregas->deliverRegisteredForFamilies($familiaIds, (int) (current_user()['id'] ?? 0));

        if ($updated <= 0) {
            Session::flash('warning', 'Nenhum item registrado pendente foi encontrado para entrega em lote.');
            $this->redirect('/gestor/entregas/lote');
        }

        (new AuditLogService())->record('confirmou_entrega_lote', 'entregas_ajuda', null, 'familias=' . count($familiaIds) . ';itens=' . $updated);
        Session::flash('success', $updated . ' item(ns) registrado(s) confirmado(s) como entregue.');
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
            'title' => 'Registrar itens',
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
            'itens' => [],
        ];
    }

    private function input(): array
    {
        $tipoIds = $this->integerList($_POST['tipo_ajuda_ids'] ?? []);

        return [
            'tipo_ajuda_ids' => $tipoIds,
            'itens' => $this->deliveryItemsInput($tipoIds),
        ];
    }

    private function validator(array $data): Validator
    {
        $validator = new Validator();
        $this->validateDeliveryItems($validator, $data);

        return $validator;
    }

    private function deliveryItemsInput(array $tipoIds): array
    {
        $postedItems = is_array($_POST['itens'] ?? null) ? $_POST['itens'] : [];
        $fallbackQuantity = str_replace(',', '.', trim((string) ($_POST['quantidade'] ?? '1')));
        $fallbackObservation = trim((string) ($_POST['observacao'] ?? ''));
        $items = [];

        foreach ($tipoIds as $tipoId) {
            $rawItem = is_array($postedItems[$tipoId] ?? null) ? $postedItems[$tipoId] : [];
            $items[$tipoId] = [
                'quantidade' => str_replace(',', '.', trim((string) ($rawItem['quantidade'] ?? $fallbackQuantity))),
                'observacao' => trim((string) ($rawItem['observacao'] ?? $fallbackObservation)),
            ];
        }

        return $items;
    }

    private function validateDeliveryItems(Validator $validator, array $data): void
    {
        $tipoIds = $data['tipo_ajuda_ids'] ?? [];
        $items = $data['itens'] ?? [];

        if ($tipoIds === []) {
            $validator->add('tipo_ajuda_ids', 'Selecione pelo menos um tipo de ajuda.');
            return;
        }

        foreach ($tipoIds as $tipoId) {
            $item = $items[(int) $tipoId] ?? ['quantidade' => '', 'observacao' => ''];
            $validator
                ->required('item_quantidade_' . (int) $tipoId, $item['quantidade'], 'Quantidade do item #' . (int) $tipoId)
                ->decimalRange('item_quantidade_' . (int) $tipoId, $item['quantidade'], 0.01, 999999.99, 'Quantidade do item #' . (int) $tipoId)
                ->max('item_observacao_' . (int) $tipoId, $item['observacao'], 500, 'Observação do item #' . (int) $tipoId);
        }
    }

    private function generateReceiptCode(): string
    {
        return 'ENT-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function filters(): array
    {
        $acaoBusca = trim((string) ($_GET['acao_busca'] ?? ''));
        $acaoId = $this->positiveInt($_GET['acao_id'] ?? '');
        $statusEntrega = $this->deliveryStatus($_GET['status_entrega'] ?? '');

        if ($acaoId === '' && preg_match('/A[cç][aã]o\s+#(\d+)/iu', $acaoBusca, $matches) === 1) {
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
            'status_entrega' => $statusEntrega,
            'data_inicio' => trim((string) ($_GET['data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_GET['data_fim'] ?? '')),
        ];
    }

    private function batchFilters(): array
    {
        $acaoBusca = trim((string) ($_GET['lote_acao_busca'] ?? ''));
        $acaoId = $this->positiveInt($_GET['lote_acao_id'] ?? '');
        $statusEntrega = $this->deliveryStatus($_GET['lote_status_entrega'] ?? '');

        if ($acaoId === '' && preg_match('/A[cç][aã]o\s+#(\d+)/iu', $acaoBusca, $matches) === 1) {
            $acaoId = $this->positiveInt($matches[1]);
        }

        return [
            'q' => trim((string) ($_GET['lote_q'] ?? '')),
            'acao_id' => $acaoId,
            'acao_busca' => $acaoBusca,
            'residencia_id' => trim((string) ($_GET['lote_residencia_id'] ?? '')),
            'residencia_busca' => trim((string) ($_GET['lote_residencia_busca'] ?? '')),
            'status_entrega' => $statusEntrega,
            'data_inicio' => trim((string) ($_GET['lote_data_inicio'] ?? '')),
            'data_fim' => trim((string) ($_GET['lote_data_fim'] ?? '')),
        ];
    }

    private function hasBatchFilters(array $filters): bool
    {
        foreach (['q', 'acao_id', 'acao_busca', 'residencia_id', 'residencia_busca', 'status_entrega', 'data_inicio', 'data_fim'] as $key) {
            if (($filters[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function deliveryStatus(mixed $value): string
    {
        $status = trim((string) $value);

        return in_array($status, ['registrado', 'entregue', 'nao_entregue'], true) ? $status : '';
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

            if (is_string($path) && preg_match('#/gestor/entregas/(?:registrar/)?validar/([^/]+)$#', $path, $matches) === 1) {
                return strtoupper(rawurldecode($matches[1]));
            }
        }

        return strtoupper($value);
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
}
