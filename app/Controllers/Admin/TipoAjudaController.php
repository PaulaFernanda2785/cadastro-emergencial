<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\TipoAjudaRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;

final class TipoAjudaController extends Controller
{
    private const INDEX_PER_PAGE = 5;

    public function __construct(
        private readonly TipoAjudaRepository $tipos = new TipoAjudaRepository()
    ) {
    }

    public function index(): void
    {
        $filters = $this->indexFilters();
        $total = $this->tipos->countSearch($filters);
        $totalPages = max(1, (int) ceil($total / self::INDEX_PER_PAGE));
        $page = min($this->requestedPage(), $totalPages);

        $this->view('admin.ajudas.index', [
            'title' => 'Tipos de ajuda',
            'tipos' => $this->tipos->search($filters, self::INDEX_PER_PAGE, ($page - 1) * self::INDEX_PER_PAGE),
            'filters' => $filters,
            'summary' => $this->tipos->searchSummary($filters),
            'allTipos' => $this->tipos->all(),
            'unidades' => $this->tipos->unitOptions(),
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
        $this->view('admin.ajudas.form', [
            'title' => 'Novo tipo de ajuda',
            'tipo' => ['nome' => '', 'unidade_medida' => '', 'ativo' => 1],
            'errors' => [],
            'action' => '/admin/ajudas',
        ]);
    }

    public function store(): void
    {
        $this->guardPost('admin.ajudas.store', '/admin/ajudas/novo');

        $nome = trim((string) ($_POST['nome'] ?? ''));
        $unidade = trim((string) ($_POST['unidade_medida'] ?? ''));
        $validator = $this->validator($nome, $unidade);

        if ($validator->fails()) {
            $this->view('admin.ajudas.form', [
                'title' => 'Novo tipo de ajuda',
                'tipo' => ['nome' => $nome, 'unidade_medida' => $unidade, 'ativo' => 1],
                'errors' => $validator->errors(),
                'action' => '/admin/ajudas',
            ]);
            return;
        }

        $id = $this->tipos->create($nome, $unidade);
        (new AuditLogService())->record('criou_tipo_ajuda', 'tipos_ajuda', $id, $nome);
        Session::flash('success', 'Tipo de ajuda cadastrado.');

        $this->redirect('/admin/ajudas');
    }

    public function edit(string $id): void
    {
        $tipo = $this->tipos->find((int) $id);

        if ($tipo === null) {
            $this->abort(404);
        }

        $this->view('admin.ajudas.form', [
            'title' => 'Editar tipo de ajuda',
            'tipo' => $tipo,
            'errors' => [],
            'action' => '/admin/ajudas/' . (int) $id,
        ]);
    }

    public function update(string $id): void
    {
        $tipo = $this->tipos->find((int) $id);

        if ($tipo === null) {
            $this->abort(404);
        }

        $this->guardPost('admin.ajudas.update.' . (int) $id, '/admin/ajudas/' . (int) $id . '/editar');

        $nome = trim((string) ($_POST['nome'] ?? ''));
        $unidade = trim((string) ($_POST['unidade_medida'] ?? ''));
        $ativo = (string) ($_POST['ativo'] ?? '1') === '1';
        $validator = $this->validator($nome, $unidade);

        if ($validator->fails()) {
            $this->view('admin.ajudas.form', [
                'title' => 'Editar tipo de ajuda',
                'tipo' => ['id' => (int) $id, 'nome' => $nome, 'unidade_medida' => $unidade, 'ativo' => $ativo ? 1 : 0],
                'errors' => $validator->errors(),
                'action' => '/admin/ajudas/' . (int) $id,
            ]);
            return;
        }

        $this->tipos->update((int) $id, $nome, $unidade, $ativo);
        (new AuditLogService())->record('atualizou_tipo_ajuda', 'tipos_ajuda', (int) $id, $nome);
        Session::flash('success', 'Tipo de ajuda atualizado.');

        $this->redirect('/admin/ajudas');
    }

    public function activate(string $id): void
    {
        $tipo = $this->findTipo((int) $id);
        $this->guardPost('admin.ajudas.status.' . (int) $id . '.ativar', '/admin/ajudas');

        $this->tipos->setActive((int) $id, true);
        (new AuditLogService())->record('ativou_tipo_ajuda', 'tipos_ajuda', (int) $id, $tipo['nome']);
        Session::flash('success', 'Tipo de ajuda ativado.');

        $this->redirect('/admin/ajudas');
    }

    public function deactivate(string $id): void
    {
        $tipo = $this->findTipo((int) $id);
        $this->guardPost('admin.ajudas.status.' . (int) $id . '.inativar', '/admin/ajudas');

        $this->tipos->setActive((int) $id, false);
        (new AuditLogService())->record('inativou_tipo_ajuda', 'tipos_ajuda', (int) $id, $tipo['nome']);
        Session::flash('success', 'Tipo de ajuda inativado.');

        $this->redirect('/admin/ajudas');
    }

    public function delete(string $id): void
    {
        $tipo = $this->findTipo((int) $id);
        $this->guardPost('admin.ajudas.delete.' . (int) $id, '/admin/ajudas');

        if ($this->tipos->countDeliveries((int) $id) > 0) {
            Session::flash('warning', 'Não é possível excluir um tipo de ajuda que já possui entregas registradas. Inative o tipo para impedir novas entregas.');
            $this->redirect('/admin/ajudas');
        }

        $this->tipos->delete((int) $id);
        (new AuditLogService())->record('excluiu_tipo_ajuda', 'tipos_ajuda', (int) $id, $tipo['nome']);
        Session::flash('success', 'Tipo de ajuda excluído.');

        $this->redirect('/admin/ajudas');
    }

    private function findTipo(int $id): array
    {
        $tipo = $this->tipos->find($id);

        if ($tipo === null) {
            $this->abort(404);
        }

        return $tipo;
    }

    private function indexFilters(): array
    {
        $status = trim((string) ($_GET['status'] ?? ''));

        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $status = '';
        }

        return [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120),
            'status' => $status,
            'unidade' => mb_substr(trim((string) ($_GET['unidade'] ?? '')), 0, 50),
        ];
    }

    private function requestedPage(): int
    {
        $page = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);

        return is_int($page) && $page > 0 ? $page : 1;
    }

    private function validator(string $nome, string $unidade): Validator
    {
        return (new Validator())
            ->required('nome', $nome, 'Nome')
            ->max('nome', $nome, 180, 'Nome')
            ->required('unidade_medida', $unidade, 'Unidade de medida')
            ->max('unidade_medida', $unidade, 50, 'Unidade de medida');
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
