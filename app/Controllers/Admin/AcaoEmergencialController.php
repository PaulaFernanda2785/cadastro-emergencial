<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\MunicipioRepository;
use App\Services\AcaoEmergencialService;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;
use App\Services\TerritorioService;

final class AcaoEmergencialController extends Controller
{
    private const STATUS = ['aberta', 'encerrada', 'cancelada'];
    private const INDEX_PER_PAGE = 10;

    public function __construct(
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository(),
        private readonly MunicipioRepository $municipios = new MunicipioRepository(),
        private readonly TerritorioService $territorios = new TerritorioService()
    ) {
    }

    public function index(): void
    {
        $filters = $this->indexFilters();
        $total = $this->acoes->countSearch($filters);
        $totalPages = max(1, (int) ceil($total / self::INDEX_PER_PAGE));
        $page = min($this->requestedPage(), $totalPages);

        $this->view('admin.acoes.index', [
            'title' => 'Ações emergenciais',
            'acoes' => $this->acoes->search(
                $filters,
                self::INDEX_PER_PAGE,
                ($page - 1) * self::INDEX_PER_PAGE
            ),
            'filters' => $filters,
            'summary' => $this->acoes->searchSummary($filters),
            'municipios' => $this->acoes->municipalityOptions(),
            'eventos' => $this->acoes->eventOptions(),
            'pagination' => [
                'page' => $page,
                'per_page' => self::INDEX_PER_PAGE,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function create(): void
    {
        $this->form('Nova ação emergencial', [
            'estado' => '',
            'municipio_id' => '',
            'municipio_codigo_ibge' => '',
            'municipio_nome' => '',
            'localidade' => '',
            'tipo_evento' => '',
            'data_evento' => date('Y-m-d'),
            'status' => 'aberta',
        ], [], '/admin/acoes');
    }

    public function store(): void
    {
        $this->guardPost('admin.acoes.store', '/admin/acoes/novo');

        $data = $this->input();
        $validator = $this->validator($data);
        $municipioId = $this->resolveMunicipioId($data, $validator);

        if ($validator->fails() || $municipioId === null) {
            $this->form('Nova ação emergencial', $data, $validator->errors(), '/admin/acoes');
            return;
        }

        $data['municipio_id'] = $municipioId;
        $id = (new AcaoEmergencialService())->create($data);
        (new AuditLogService())->record('criou_acao_emergencial', 'acoes_emergenciais', $id, $data['localidade']);
        Session::flash('success', 'Ação emergencial cadastrada.');

        $this->redirect('/admin/acoes');
    }

    public function edit(string $id): void
    {
        $acao = $this->acoes->find((int) $id);

        if ($acao === null) {
            $this->abort(404);
        }

        if ((string) ($acao['status'] ?? '') !== 'aberta') {
            Session::flash('warning', 'Acoes encerradas ou canceladas nao podem ser editadas. Ative a acao para editar novamente.');
            $this->redirect('/admin/acoes');
        }

        $acao['estado'] = $acao['uf'] ?? '';
        $acao['municipio_codigo_ibge'] = $acao['codigo_ibge'] ?? '';
        $acao['municipio_nome'] = $acao['municipio_nome'] ?? '';
        $this->form('Editar ação emergencial', $acao, [], '/admin/acoes/' . (int) $id);
    }

    public function update(string $id): void
    {
        $acao = $this->acoes->find((int) $id);

        if ($acao === null) {
            $this->abort(404);
        }

        $this->guardPost('admin.acoes.update.' . (int) $id, '/admin/acoes/' . (int) $id . '/editar');

        if ((string) ($acao['status'] ?? '') !== 'aberta') {
            Session::flash('warning', 'Acoes encerradas ou canceladas nao podem ser editadas. Ative a acao para editar novamente.');
            $this->redirect('/admin/acoes');
        }

        $data = $this->input();
        $validator = $this->validator($data);
        $municipioId = $this->resolveMunicipioId($data, $validator);
        $statusMessage = $this->statusTransitionMessage(
            (string) ($acao['status'] ?? ''),
            (string) ($data['status'] ?? ''),
            $this->acoes->countActiveRecords((int) $id)
        );

        if ($statusMessage !== null) {
            $validator->add('status', $statusMessage);
        }

        if ($validator->fails() || $municipioId === null) {
            $data['id'] = (int) $id;
            $data['token_publico'] = $acao['token_publico'];
            $this->form('Editar ação emergencial', $data, $validator->errors(), '/admin/acoes/' . (int) $id);
            return;
        }

        $data['municipio_id'] = $municipioId;
        $this->acoes->update((int) $id, $data);
        (new AuditLogService())->record('atualizou_acao_emergencial', 'acoes_emergenciais', (int) $id, $data['localidade']);
        Session::flash('success', 'Ação emergencial atualizada.');

        $this->redirect('/admin/acoes');
    }

    public function activate(string $id): void
    {
        $this->changeStatus((int) $id, 'aberta', 'Ação emergencial ativada.');
    }

    public function close(string $id): void
    {
        $this->changeStatus((int) $id, 'encerrada', 'Ação emergencial encerrada.');
    }

    public function cancel(string $id): void
    {
        $this->changeStatus((int) $id, 'cancelada', 'Ação emergencial cancelada.');
    }

    public function delete(string $id): void
    {
        $acao = $this->acoes->find((int) $id);

        if ($acao === null) {
            $this->abort(404);
        }

        $this->guardPost('admin.acoes.delete.' . (int) $id, '/admin/acoes');
        $this->acoes->softDelete((int) $id);
        (new AuditLogService())->record('excluiu_acao_emergencial', 'acoes_emergenciais', (int) $id, $acao['localidade']);
        Session::flash('success', 'Ação emergencial removida da listagem.');

        $this->redirect('/admin/acoes');
    }

    private function changeStatus(int $id, string $status, string $message): void
    {
        $acao = $this->acoes->find($id);

        if ($acao === null) {
            $this->abort(404);
        }

        $this->guardPost('admin.acoes.status.' . $id . '.' . $status, '/admin/acoes');
        $statusMessage = $this->statusTransitionMessage(
            (string) ($acao['status'] ?? ''),
            $status,
            $this->acoes->countActiveRecords($id)
        );

        if ($statusMessage !== null) {
            Session::flash('warning', $statusMessage);
            $this->redirect('/admin/acoes');
        }

        $this->acoes->updateStatus($id, $status);
        (new AuditLogService())->record('alterou_status_acao_emergencial', 'acoes_emergenciais', $id, $status);
        Session::flash('success', $message);

        $this->redirect('/admin/acoes');
    }

    private function statusTransitionMessage(string $currentStatus, string $targetStatus, int $activeRecords): ?string
    {
        if ($targetStatus === $currentStatus) {
            return null;
        }

        if ($targetStatus === 'aberta') {
            return null;
        }

        if ($currentStatus !== 'aberta') {
            return 'Acoes encerradas ou canceladas devem ser ativadas antes de mudar para outro status.';
        }

        if ($targetStatus === 'cancelada' && $activeRecords > 0) {
            return 'Nao e possivel cancelar uma acao que ja possui registros. Use encerrar.';
        }

        if ($targetStatus === 'encerrada' && $activeRecords === 0) {
            return 'Nao e possivel encerrar uma acao sem registros. Use cancelar.';
        }

        return null;
    }

    private function form(string $title, array $acao, array $errors, string $action): void
    {
        $this->view('admin.acoes.form', [
            'title' => $title,
            'acao' => $acao,
            'estados' => $this->territorios->states(),
            'municipiosTerritoriais' => $this->territorios->municipalities(),
            'localidadesPorMunicipio' => $this->acoes->localitiesByMunicipalityCode(),
            'statuses' => self::STATUS,
            'errors' => $errors,
            'action' => $action,
        ]);
    }

    private function input(): array
    {
        return [
            'estado' => trim((string) ($_POST['estado'] ?? '')),
            'municipio_id' => trim((string) ($_POST['municipio_id'] ?? '')),
            'municipio_codigo_ibge' => trim((string) ($_POST['municipio_codigo_ibge'] ?? '')),
            'municipio_nome' => trim((string) ($_POST['municipio_nome'] ?? '')),
            'localidade' => trim((string) ($_POST['localidade'] ?? '')),
            'tipo_evento' => trim((string) ($_POST['tipo_evento'] ?? '')),
            'data_evento' => trim((string) ($_POST['data_evento'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'aberta')),
        ];
    }

    private function indexFilters(): array
    {
        $status = trim((string) ($_GET['status'] ?? ''));

        if (!in_array($status, self::STATUS, true)) {
            $status = '';
        }

        return [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120),
            'status' => $status,
            'municipio_id' => $this->integerFilter($_GET['municipio_id'] ?? null),
            'municipio_busca' => mb_substr(trim((string) ($_GET['municipio_busca'] ?? '')), 0, 120),
            'tipo_evento' => mb_substr(trim((string) ($_GET['tipo_evento'] ?? '')), 0, 120),
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
        return (new Validator())
            ->required('estado', $data['estado'], 'Estado')
            ->max('estado', $data['estado'], 2, 'Estado')
            ->required('municipio_codigo_ibge', $data['municipio_codigo_ibge'], 'Município')
            ->max('municipio_codigo_ibge', $data['municipio_codigo_ibge'], 20, 'Município')
            ->required('localidade', $data['localidade'], 'Localidade')
            ->max('localidade', $data['localidade'], 180, 'Localidade')
            ->required('tipo_evento', $data['tipo_evento'], 'Tipo de evento')
            ->max('tipo_evento', $data['tipo_evento'], 180, 'Tipo de evento')
            ->required('data_evento', $data['data_evento'], 'Data do evento')
            ->date('data_evento', $data['data_evento'], 'Data do evento')
            ->in('status', $data['status'], self::STATUS, 'Status');
    }

    private function resolveMunicipioId(array $data, Validator $validator): ?int
    {
        if ($data['municipio_codigo_ibge'] === '') {
            return null;
        }

        $municipio = $this->territorios->findMunicipalityByCode($data['municipio_codigo_ibge']);

        if ($municipio === null) {
            $validator->add('municipio_codigo_ibge', 'Município não encontrado nos arquivos territoriais.');
            return null;
        }

        if (strtoupper((string) $data['estado']) !== $municipio['uf']) {
            $validator->add('estado', 'Estado não corresponde ao município selecionado.');
            return null;
        }

        return $this->municipios->ensure($municipio);
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
