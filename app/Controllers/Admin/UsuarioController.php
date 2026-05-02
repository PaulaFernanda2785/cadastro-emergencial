<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\UsuarioRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;

final class UsuarioController extends Controller
{
    private const PROFILES = ['cadastrador', 'gestor', 'administrador'];

    public function __construct(
        private readonly UsuarioRepository $usuarios = new UsuarioRepository()
    ) {
    }

    public function index(): void
    {
        $this->view('admin.usuarios.index', [
            'title' => 'Usuarios',
            'usuarios' => $this->usuarios->all(),
        ]);
    }

    public function create(): void
    {
        $this->form('Novo usuario', $this->emptyInput(), [], '/admin/usuarios');
    }

    public function store(): void
    {
        $this->guardPost('admin.usuarios.store', '/admin/usuarios/novo');

        $data = $this->input();
        $validator = $this->validator($data, true);

        if ($validator->fails()) {
            $this->form('Novo usuario', $data, $validator->errors(), '/admin/usuarios');
            return;
        }

        $id = $this->usuarios->create($data);
        (new AuditLogService())->record('criou_usuario', 'usuarios', $id, $data['email']);
        Session::flash('success', 'Usuario cadastrado.');

        $this->redirect('/admin/usuarios');
    }

    public function edit(string $id): void
    {
        $usuario = $this->findUsuario((int) $id);
        unset($usuario['senha_hash']);
        $usuario['senha'] = '';
        $usuario['confirmar_senha'] = '';

        $this->form('Editar usuario', $usuario, [], '/admin/usuarios/' . (int) $id);
    }

    public function update(string $id): void
    {
        $usuario = $this->findUsuario((int) $id);
        $this->guardPost('admin.usuarios.update.' . (int) $id, '/admin/usuarios/' . (int) $id . '/editar');

        $data = $this->input();
        $validator = $this->validator($data, false, (int) $id);
        $isCurrentUser = (int) (current_user()['id'] ?? 0) === (int) $id;

        if ($isCurrentUser && ($data['perfil'] !== 'administrador' || empty($data['ativo']))) {
            $validator->add('perfil', 'Voce nao pode remover seu proprio acesso administrativo ou inativar sua conta.');
        }

        if ($validator->fails()) {
            $data['id'] = (int) $id;
            $this->form('Editar usuario', $data, $validator->errors(), '/admin/usuarios/' . (int) $id);
            return;
        }

        $this->usuarios->update((int) $id, $data);

        if ($data['senha'] !== '') {
            $this->usuarios->updatePassword((int) $id, $data['senha']);
        }

        if ((int) (current_user()['id'] ?? 0) === (int) $id) {
            Session::put('user', array_merge(current_user() ?? [], [
                'nome' => $data['nome'],
                'cpf' => $data['cpf'],
                'email' => $data['email'],
                'telefone' => $data['telefone'],
                'orgao' => $data['orgao'],
                'unidade_setor' => $data['unidade_setor'],
                'militar' => $data['militar'],
                'graduacao' => $data['graduacao'],
                'nome_guerra' => $data['nome_guerra'],
                'matricula_funcional' => $data['matricula_funcional'],
                'perfil' => $data['perfil'],
                'ativo' => !empty($data['ativo']) ? 1 : 0,
            ]));
        }

        (new AuditLogService())->record('atualizou_usuario', 'usuarios', (int) $id, $usuario['email']);
        Session::flash('success', 'Usuario atualizado.');

        $this->redirect('/admin/usuarios');
    }

    private function findUsuario(int $id): array
    {
        $usuario = $this->usuarios->find($id);

        if ($usuario === null) {
            $this->abort(404);
        }

        return $usuario;
    }

    private function form(string $title, array $usuario, array $errors, string $action): void
    {
        $this->view('admin.usuarios.form', [
            'title' => $title,
            'usuario' => $usuario,
            'errors' => $errors,
            'profiles' => self::PROFILES,
            'action' => $action,
        ]);
    }

    private function emptyInput(): array
    {
        return [
            'nome' => '',
            'cpf' => '',
            'email' => '',
            'telefone' => '',
            'orgao' => '',
            'unidade_setor' => '',
            'militar' => '',
            'graduacao' => '',
            'nome_guerra' => '',
            'matricula_funcional' => '',
            'perfil' => 'cadastrador',
            'ativo' => '1',
            'senha' => '',
            'confirmar_senha' => '',
        ];
    }

    private function input(): array
    {
        $militar = isset($_POST['militar']) ? '1' : '';

        return [
            'nome' => trim((string) ($_POST['nome'] ?? '')),
            'cpf' => trim((string) ($_POST['cpf'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
            'orgao' => trim((string) ($_POST['orgao'] ?? '')),
            'unidade_setor' => trim((string) ($_POST['unidade_setor'] ?? '')),
            'militar' => $militar,
            'graduacao' => $militar !== '' ? trim((string) ($_POST['graduacao'] ?? '')) : '',
            'nome_guerra' => $militar !== '' ? trim((string) ($_POST['nome_guerra'] ?? '')) : '',
            'matricula_funcional' => $militar !== '' ? trim((string) ($_POST['matricula_funcional'] ?? '')) : '',
            'perfil' => trim((string) ($_POST['perfil'] ?? 'cadastrador')),
            'ativo' => (string) ($_POST['ativo'] ?? '1') === '1' ? '1' : '',
            'senha' => (string) ($_POST['senha'] ?? ''),
            'confirmar_senha' => (string) ($_POST['confirmar_senha'] ?? ''),
        ];
    }

    private function validator(array $data, bool $passwordRequired, ?int $ignoreId = null): Validator
    {
        $validator = (new Validator())
            ->required('nome', $data['nome'], 'Nome')
            ->max('nome', $data['nome'], 180, 'Nome')
            ->required('cpf', $data['cpf'], 'CPF')
            ->max('cpf', $data['cpf'], 14, 'CPF')
            ->cpf('cpf', $data['cpf'], 'CPF')
            ->required('email', $data['email'], 'E-mail')
            ->email('email', $data['email'], 'E-mail')
            ->max('email', $data['email'], 180, 'E-mail')
            ->max('telefone', $data['telefone'], 30, 'Telefone')
            ->max('orgao', $data['orgao'], 180, 'Orgao')
            ->max('unidade_setor', $data['unidade_setor'], 180, 'Unidade/setor')
            ->max('graduacao', $data['graduacao'], 80, 'Graduacao')
            ->max('nome_guerra', $data['nome_guerra'], 120, 'Nome de guerra')
            ->max('matricula_funcional', $data['matricula_funcional'], 60, 'Matricula funcional')
            ->in('perfil', $data['perfil'], self::PROFILES, 'Perfil');

        if ($passwordRequired || $data['senha'] !== '') {
            $validator->required('senha', $data['senha'], 'Senha');

            if (strlen((string) $data['senha']) < 8) {
                $validator->add('senha', 'Senha deve ter no minimo 8 caracteres.');
            }

            if ($data['senha'] !== $data['confirmar_senha']) {
                $validator->add('confirmar_senha', 'Confirmacao nao confere com a senha.');
            }
        }

        $sameEmailUser = $data['email'] !== '' ? $this->usuarios->findByEmail($data['email']) : null;
        if ($sameEmailUser !== null && (int) $sameEmailUser['id'] !== (int) $ignoreId) {
            $validator->add('email', 'E-mail ja cadastrado para outro usuario.');
        }

        $sameCpfUser = $data['cpf'] !== '' ? $this->usuarios->findByCpf($data['cpf']) : null;
        if ($sameCpfUser !== null && (int) $sameCpfUser['id'] !== (int) $ignoreId) {
            $validator->add('cpf', 'CPF ja cadastrado para outro usuario.');
        }

        return $validator;
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
