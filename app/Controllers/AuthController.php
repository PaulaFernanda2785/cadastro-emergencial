<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\IdempotenciaService;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (($_GET['intended'] ?? '') !== '1') {
            Session::forget('intended_url');
        }

        $this->sendNoStoreHeaders();

        $this->view('auth.login', [
            'title' => 'Entrar',
            'email' => '',
            'errors' => [],
            'canRegister' => $this->canRegisterFromQr(),
        ]);
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $csrfValid = Csrf::validate($_POST['_csrf_token'] ?? null);

        if (!$csrfValid) {
            Session::flash('error', 'Sessao expirada. Recarregue a pagina e tente novamente.');
            $this->redirect('/login');
        }

        $validator = (new Validator())
            ->required('email', $email, 'E-mail')
            ->email('email', $email, 'E-mail')
            ->max('email', $email, 180, 'E-mail')
            ->required('password', $password, 'Senha');

        if ($validator->fails()) {
            $this->sendNoStoreHeaders();

            $this->view('auth.login', [
                'title' => 'Entrar',
                'email' => $email,
                'errors' => $validator->errors(),
                'canRegister' => $this->canRegisterFromQr(),
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
        Session::put('last_activity_at', time());
        $this->restoreActiveActionForUser($user);

        (new AuditLogService())->record('login_sucesso', 'usuarios', (int) $user['id'], 'Usuario autenticado.', (int) $user['id']);

        $intendedUrl = Session::get('intended_url');
        Session::forget('intended_url');

        if (is_string($intendedUrl) && str_starts_with($intendedUrl, '/') && !str_starts_with($intendedUrl, '//')) {
            header('Location: ' . url($intendedUrl));
            exit;
        }

        $this->redirect('/dashboard');
    }

    public function showRegister(): void
    {
        if (!$this->canRegisterFromQr()) {
            Session::flash('warning', 'Acesse o cadastro pelo QR Code da acao.');
            $this->redirect('/login');
        }

        $this->sendNoStoreHeaders();

        $this->view('auth.register', [
            'title' => 'Criar cadastro',
            'usuario' => $this->emptyRegisterInput(),
            'errors' => [],
            'acao' => $this->intendedQrAction(),
        ]);
    }

    public function register(): void
    {
        if (!$this->canRegisterFromQr()) {
            Session::flash('warning', 'Acesse o cadastro pelo QR Code da acao.');
            $this->redirect('/login');
        }

        $this->guardPost('auth.qr_register', '/cadastro-qr');

        $data = $this->registerInput();
        $validator = $this->registerValidator($data);

        if ($validator->fails()) {
            $this->sendNoStoreHeaders();

            $this->view('auth.register', [
                'title' => 'Criar cadastro',
                'usuario' => $data,
                'errors' => $validator->errors(),
                'acao' => $this->intendedQrAction(),
            ]);
            return;
        }

        $data['perfil'] = 'cadastrador';
        $data['ativo'] = '1';

        $repository = new UsuarioRepository();
        $id = $repository->create($data);
        $user = $repository->find($id);

        if ($user === null) {
            Session::flash('error', 'Nao foi possivel iniciar a sessao apos o cadastro.');
            $this->redirect('/login');
        }

        unset($user['senha_hash']);
        Session::regenerate();
        Session::put('user', $user);
        Session::put('last_activity_at', time());
        $this->restoreActiveActionForUser($user);
        $repository->touchLastAccess($id);

        (new AuditLogService())->record('criou_usuario_qr', 'usuarios', $id, $data['email'], $id);
        Session::flash('success', 'Cadastro criado. Continue o registro da acao.');

        $this->redirectToIntended();
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

    private function sendNoStoreHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function emptyRegisterInput(): array
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
            'senha' => '',
            'confirmar_senha' => '',
        ];
    }

    private function registerInput(): array
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
            'senha' => (string) ($_POST['senha'] ?? ''),
            'confirmar_senha' => (string) ($_POST['confirmar_senha'] ?? ''),
        ];
    }

    private function registerValidator(array $data): Validator
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
            ->max('orgao', $data['orgao'], 180, 'Orgao/instituicao')
            ->max('unidade_setor', $data['unidade_setor'], 180, 'Unidade/setor')
            ->max('graduacao', $data['graduacao'], 80, 'Graduacao')
            ->max('nome_guerra', $data['nome_guerra'], 120, 'Nome de guerra')
            ->max('matricula_funcional', $data['matricula_funcional'], 60, 'Matricula funcional')
            ->required('senha', $data['senha'], 'Senha');

        if (strlen((string) $data['senha']) < 8) {
            $validator->add('senha', 'Senha deve ter no minimo 8 caracteres.');
        }

        if ($data['senha'] !== $data['confirmar_senha']) {
            $validator->add('confirmar_senha', 'Confirmacao nao confere com a senha.');
        }

        $repository = new UsuarioRepository();

        if ($data['email'] !== '' && $repository->findByEmail($data['email']) !== null) {
            $validator->add('email', 'E-mail ja cadastrado. Use a tela de login.');
        }

        if ($data['cpf'] !== '' && $repository->findByCpf($data['cpf']) !== null) {
            $validator->add('cpf', 'CPF ja cadastrado. Use a tela de login.');
        }

        return $validator;
    }

    private function canRegisterFromQr(): bool
    {
        $intendedUrl = Session::get('intended_url');

        return is_string($intendedUrl)
            && str_contains($intendedUrl, '/acao/')
            && str_contains($intendedUrl, '/residencias/novo');
    }

    private function intendedQrAction(): ?array
    {
        $intendedUrl = Session::get('intended_url');

        if (!is_string($intendedUrl) || !preg_match('#/acao/([^/]+)/residencias/novo#', $intendedUrl, $matches)) {
            return null;
        }

        return (new AcaoEmergencialRepository())->findByPublicToken(rawurldecode($matches[1]));
    }

    private function redirectToIntended(): void
    {
        $intendedUrl = Session::get('intended_url');
        Session::forget('intended_url');

        if (is_string($intendedUrl) && str_starts_with($intendedUrl, '/') && !str_starts_with($intendedUrl, '//')) {
            header('Location: ' . url($intendedUrl));
            exit;
        }

        $this->redirect('/dashboard');
    }

    private function restoreActiveActionForUser(?array $user): void
    {
        if (($user['perfil'] ?? null) !== 'cadastrador') {
            return;
        }

        $repository = new AcaoEmergencialRepository();
        $token = Session::get('active_action_token');

        if (is_string($token) && $token !== '') {
            $acao = $repository->findByPublicToken($token);

            if ($acao !== null && ($acao['status'] ?? null) === 'aberta') {
                return;
            }

            Session::forget('active_action_token');
        }

        $intendedUrl = Session::get('intended_url');

        if (!is_string($intendedUrl) || !preg_match('#/acao/([^/]+)/#', $intendedUrl, $matches)) {
            return;
        }

        $acao = $repository->findByPublicToken(rawurldecode($matches[1]));

        if ($acao !== null && ($acao['status'] ?? null) === 'aberta') {
            Session::put('active_action_token', (string) $acao['token_publico']);
        }
    }
}
