<?php

declare(strict_types=1);

namespace App\Controllers\Gestor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\EntregaAjudaRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\TipoAjudaRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;

final class EntregaAjudaController extends Controller
{
    public function __construct(
        private readonly EntregaAjudaRepository $entregas = new EntregaAjudaRepository(),
        private readonly FamiliaRepository $familias = new FamiliaRepository(),
        private readonly TipoAjudaRepository $tipos = new TipoAjudaRepository()
    ) {
    }

    public function index(): void
    {
        $this->view('gestor.entregas.index', [
            'title' => 'Entregas de ajuda',
            'entregas' => $this->entregas->all(),
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
        ], 'receipt');
    }

    public function validateReceiptQuery(): void
    {
        $code = $this->extractReceiptCode(trim((string) ($_GET['codigo'] ?? '')));

        if ($code === '') {
            Session::flash('warning', 'Informe ou leia o codigo do comprovante de cadastro familiar.');
            $this->redirect('/gestor/entregas');
        }

        $this->validateReceipt($code);
    }

    public function validateReceipt(string $codigo): void
    {
        $code = $this->extractReceiptCode($codigo);
        $familia = $this->familias->findByReceiptCode($code);

        if ($familia === null) {
            Session::flash('error', 'Comprovante de cadastro familiar invalido ou nao localizado.');
            $this->redirect('/gestor/entregas');
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
        $tipo = $data['tipo_ajuda_id'] !== '' ? $this->tipos->find((int) $data['tipo_ajuda_id']) : null;

        if ($tipo === null || (int) ($tipo['ativo'] ?? 0) !== 1) {
            $validator->add('tipo_ajuda_id', 'Selecione um tipo de ajuda ativo.');
        }

        if ($validator->fails()) {
            $this->form($familia, $data, $validator->errors());
            return;
        }

        $data['familia_id'] = (int) $familiaId;
        $data['entregue_por'] = (int) (current_user()['id'] ?? 0);
        $data['comprovante_codigo'] = $this->generateReceiptCode();

        $id = $this->entregas->create($data);
        (new AuditLogService())->record('registrou_entrega', 'entregas_ajuda', $id, $data['comprovante_codigo']);
        Session::flash('success', 'Entrega registrada com comprovante ' . $data['comprovante_codigo'] . '.');

        $this->redirect('/gestor/entregas/' . $id . '/comprovante');
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
            'tipo_ajuda_id' => '',
            'quantidade' => '1',
            'observacao' => '',
        ];
    }

    private function input(): array
    {
        return [
            'tipo_ajuda_id' => trim((string) ($_POST['tipo_ajuda_id'] ?? '')),
            'quantidade' => str_replace(',', '.', trim((string) ($_POST['quantidade'] ?? '1'))),
            'observacao' => trim((string) ($_POST['observacao'] ?? '')),
        ];
    }

    private function validator(array $data): Validator
    {
        return (new Validator())
            ->required('tipo_ajuda_id', $data['tipo_ajuda_id'], 'Tipo de ajuda')
            ->integer('tipo_ajuda_id', $data['tipo_ajuda_id'], 'Tipo de ajuda')
            ->required('quantidade', $data['quantidade'], 'Quantidade')
            ->decimalRange('quantidade', $data['quantidade'], 0.01, 999999.99, 'Quantidade')
            ->max('observacao', $data['observacao'], 500, 'Observacao');
    }

    private function generateReceiptCode(): string
    {
        return 'ENT-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
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
