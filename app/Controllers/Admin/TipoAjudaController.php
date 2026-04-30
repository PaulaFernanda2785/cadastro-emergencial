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
    public function __construct(
        private readonly TipoAjudaRepository $tipos = new TipoAjudaRepository()
    ) {
    }

    public function index(): void
    {
        $this->view('admin.ajudas.index', [
            'title' => 'Tipos de ajuda',
            'tipos' => $this->tipos->all(),
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
