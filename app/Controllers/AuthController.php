<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\UsuarioRepository;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\IdempotenciaService;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth.login', [
            'title' => 'Entrar',
            'email' => '',
            'errors' => [],
        ]);
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Sessao expirada ou formulario invalido.');
            $this->redirect('/login');
        }

        $idempotency = (new IdempotenciaService())->validateAndReserve(
            $_POST['_idempotency_token'] ?? null,
            'auth.login'
        );

        if (!$idempotency['ok']) {
            Session::flash('warning', $idempotency['message']);
            $this->redirect('/login');
        }

        $validator = (new Validator())
            ->required('email', $email, 'E-mail')
            ->email('email', $email, 'E-mail')
            ->max('email', $email, 180, 'E-mail')
            ->required('password', $password, 'Senha');

        if ($validator->fails()) {
            $this->view('auth.login', [
                'title' => 'Entrar',
                'email' => $email,
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $user = (new AuthService())->attempt($email, $password);

        if ($user === null) {
            (new AuditLogService())->record('login_falhou', 'usuarios', null, 'Tentativa de login para ' . $email);
            Session::flash('error', 'Credenciais invalidas ou usuario inativo.');
            $this->redirect('/login');
        }

        Session::regenerate();
        Session::put('user', $user);

        (new AuditLogService())->record('login_sucesso', 'usuarios', (int) $user['id'], 'Usuario autenticado.', (int) $user['id']);

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Sessao expirada ou formulario invalido.');
            $this->redirect('/dashboard');
        }

        $userId = current_user()['id'] ?? null;
        (new AuditLogService())->record('logout', 'usuarios', is_numeric($userId) ? (int) $userId : null, 'Usuario encerrou a sessao.');

        Session::destroy();
        Session::start();
        Session::flash('success', 'Sessao encerrada com seguranca.');

        $this->redirect('/login');
    }

    public function showChangePassword(): void
    {
        $this->view('auth.change_password', [
            'title' => 'Alterar senha',
            'errors' => [],
        ]);
    }

    public function changePassword(): void
    {
        $this->guardPost('auth.change_password', '/alterar-senha');

        $currentPassword = (string) ($_POST['senha_atual'] ?? '');
        $newPassword = (string) ($_POST['nova_senha'] ?? '');
        $confirmation = (string) ($_POST['confirmar_senha'] ?? '');
        $validator = (new Validator())
            ->required('senha_atual', $currentPassword, 'Senha atual')
            ->required('nova_senha', $newPassword, 'Nova senha')
            ->required('confirmar_senha', $confirmation, 'Confirmacao de senha');

        if ($newPassword !== '' && strlen($newPassword) < 8) {
            $validator->add('nova_senha', 'Nova senha deve ter no minimo 8 caracteres.');
        }

        if ($newPassword !== $confirmation) {
            $validator->add('confirmar_senha', 'Confirmacao nao confere com a nova senha.');
        }

        $userId = (int) (current_user()['id'] ?? 0);
        $user = (new UsuarioRepository())->find($userId);

        if ($user === null || !password_verify($currentPassword, (string) $user['senha_hash'])) {
            $validator->add('senha_atual', 'Senha atual invalida.');
        }

        if ($validator->fails()) {
            $this->view('auth.change_password', [
                'title' => 'Alterar senha',
                'errors' => $validator->errors(),
            ]);
            return;
        }

        (new UsuarioRepository())->updatePassword($userId, $newPassword);
        (new AuditLogService())->record('alterou_senha', 'usuarios', $userId, 'Usuario alterou a propria senha.');
        Session::flash('success', 'Senha alterada com seguranca.');

        $this->redirect('/dashboard');
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
