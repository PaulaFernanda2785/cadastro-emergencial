<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

require BASE_PATH . '/app/Helpers/functions.php';

use App\Controllers\AuthController;
use App\Controllers\AssinaturaController;
use App\Controllers\DashboardController;
use App\Controllers\Admin\AcaoEmergencialController;
use App\Controllers\Admin\TipoAjudaController;
use App\Controllers\Admin\UsuarioController;
use App\Controllers\Cadastro\FamiliaController;
use App\Controllers\Cadastro\ResidenciaController;
use App\Controllers\Gestor\EntregaAjudaController;
use App\Controllers\Gestor\PrestacaoContasController;
use App\Controllers\Gestor\RecomecarController;
use App\Controllers\Gestor\RelatorioController;
use App\Controllers\PublicAcaoController;
use App\Core\Env;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

Env::load(BASE_PATH . '/.env');

$app = require BASE_PATH . '/config/app.php';
enforce_https_request($app);
send_security_headers();
date_default_timezone_set((string) $app['timezone']);

Session::start();

$router = new Router();

$router->get('/', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/cadastro-qr', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/cadastro-qr', [AuthController::class, 'register'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/alterar-senha', [AuthController::class, 'showChangePassword'], ['auth']);
$router->post('/alterar-senha', [AuthController::class, 'changePassword'], ['auth']);
$router->get('/assinaturas', [AssinaturaController::class, 'index'], ['auth']);
$router->get('/assinaturas/{id}', [AssinaturaController::class, 'show'], ['auth']);
$router->post('/assinaturas/{id}/autorizar', [AssinaturaController::class, 'authorize'], ['auth']);
$router->post('/assinaturas/{id}/negar', [AssinaturaController::class, 'reject'], ['auth']);
$router->post('/assinaturas/{id}/retornar-assinatura', [AssinaturaController::class, 'returnToSignature'], ['auth']);
$router->get('/admin', [DashboardController::class, 'admin'], ['auth', 'role:administrador']);
$router->get('/admin/usuarios', [UsuarioController::class, 'index'], ['auth', 'role:administrador']);
$router->get('/admin/usuarios/novo', [UsuarioController::class, 'create'], ['auth', 'role:administrador']);
$router->post('/admin/usuarios', [UsuarioController::class, 'store'], ['auth', 'role:administrador']);
$router->get('/admin/usuarios/{id}/editar', [UsuarioController::class, 'edit'], ['auth', 'role:administrador']);
$router->get('/admin/usuarios/{id}/senha', [UsuarioController::class, 'password'], ['auth', 'role:administrador']);
$router->post('/admin/usuarios/{id}/senha', [UsuarioController::class, 'updatePassword'], ['auth', 'role:administrador']);
$router->post('/admin/usuarios/{id}/ativar', [UsuarioController::class, 'activate'], ['auth', 'role:administrador']);
$router->post('/admin/usuarios/{id}/inativar', [UsuarioController::class, 'deactivate'], ['auth', 'role:administrador']);
$router->post('/admin/usuarios/{id}', [UsuarioController::class, 'update'], ['auth', 'role:administrador']);
$router->get('/admin/ajudas', [TipoAjudaController::class, 'index'], ['auth', 'role:administrador']);
$router->get('/admin/ajudas/novo', [TipoAjudaController::class, 'create'], ['auth', 'role:administrador']);
$router->post('/admin/ajudas', [TipoAjudaController::class, 'store'], ['auth', 'role:administrador']);
$router->get('/admin/ajudas/{id}/editar', [TipoAjudaController::class, 'edit'], ['auth', 'role:administrador']);
$router->post('/admin/ajudas/{id}/ativar', [TipoAjudaController::class, 'activate'], ['auth', 'role:administrador']);
$router->post('/admin/ajudas/{id}/inativar', [TipoAjudaController::class, 'deactivate'], ['auth', 'role:administrador']);
$router->post('/admin/ajudas/{id}/excluir', [TipoAjudaController::class, 'delete'], ['auth', 'role:administrador']);
$router->post('/admin/ajudas/{id}', [TipoAjudaController::class, 'update'], ['auth', 'role:administrador']);
$router->get('/admin/acoes', [AcaoEmergencialController::class, 'index'], ['auth', 'role:gestor,administrador']);
$router->get('/admin/acoes/novo', [AcaoEmergencialController::class, 'create'], ['auth', 'role:gestor,administrador']);
$router->post('/admin/acoes', [AcaoEmergencialController::class, 'store'], ['auth', 'role:gestor,administrador']);
$router->get('/admin/acoes/{id}/editar', [AcaoEmergencialController::class, 'edit'], ['auth', 'role:gestor,administrador']);
$router->post('/admin/acoes/{id}/ativar', [AcaoEmergencialController::class, 'activate'], ['auth', 'role:gestor,administrador']);
$router->post('/admin/acoes/{id}/encerrar', [AcaoEmergencialController::class, 'close'], ['auth', 'role:gestor,administrador']);
$router->post('/admin/acoes/{id}/cancelar', [AcaoEmergencialController::class, 'cancel'], ['auth', 'role:gestor,administrador']);
$router->post('/admin/acoes/{id}/excluir', [AcaoEmergencialController::class, 'delete'], ['auth', 'role:gestor,administrador']);
$router->post('/admin/acoes/{id}', [AcaoEmergencialController::class, 'update'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/familias', [FamiliaController::class, 'index'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias', [ResidenciaController::class, 'index'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias/{id}/documentos/{documentoId}', [ResidenciaController::class, 'viewDocument'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias/{id}/dti', [ResidenciaController::class, 'dti'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->post('/cadastros/residencias/{id}/dti/assinar', [ResidenciaController::class, 'signDti'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->post('/cadastros/residencias/{id}/dti/remover-assinatura', [ResidenciaController::class, 'removeDtiSignature'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias/{id}/editar', [ResidenciaController::class, 'edit'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->post('/cadastros/residencias/{id}', [ResidenciaController::class, 'update'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias/{id}', [ResidenciaController::class, 'show'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias/{id}/familias/novo', [FamiliaController::class, 'create'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->post('/cadastros/residencias/{id}/familias', [FamiliaController::class, 'store'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias/{id}/familias/{familiaId}/comprovante', [FamiliaController::class, 'receipt'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->post('/cadastros/residencias/{id}/familias/{familiaId}/comprovante/email', [FamiliaController::class, 'emailReceipt'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias/{id}/familias/{familiaId}', [FamiliaController::class, 'show'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias/{id}/familias/{familiaId}/documentos/{documentoId}', [FamiliaController::class, 'viewDocument'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/cadastros/residencias/{id}/familias/{familiaId}/editar', [FamiliaController::class, 'edit'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->post('/cadastros/residencias/{id}/familias/{familiaId}', [FamiliaController::class, 'update'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->post('/cadastros/residencias/{id}/familias/{familiaId}/excluir', [FamiliaController::class, 'delete'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/gestor/entregas', [EntregaAjudaController::class, 'index'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/entregas/registrar', [EntregaAjudaController::class, 'registerBatch'], ['auth', 'role:gestor,administrador']);
$router->post('/gestor/entregas/registrar/lote', [EntregaAjudaController::class, 'registerBatchStore'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/entregas/registrar/validar', [EntregaAjudaController::class, 'validateRegistrationReceiptQuery'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/entregas/registrar/validar/{codigo}', [EntregaAjudaController::class, 'validateRegistrationReceipt'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/entregas/validacao', [EntregaAjudaController::class, 'validationPage'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/entregas/lote', [EntregaAjudaController::class, 'batch'], ['auth', 'role:gestor,administrador']);
$router->post('/gestor/entregas/lote', [EntregaAjudaController::class, 'batchStore'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/entregas/validar', [EntregaAjudaController::class, 'validateReceiptQuery'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/entregas/validar/{codigo}', [EntregaAjudaController::class, 'validateReceipt'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/entregas/{id}/comprovante', [EntregaAjudaController::class, 'receipt'], ['auth', 'role:gestor,administrador']);
$router->post('/gestor/entregas/{id}/comprovante/email', [EntregaAjudaController::class, 'emailReceipt'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/familias/{id}/entregas/novo', [EntregaAjudaController::class, 'create'], ['auth', 'role:gestor,administrador']);
$router->post('/gestor/familias/{id}/entregas', [EntregaAjudaController::class, 'store'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/familias/{id}/entregas/confirmar', [EntregaAjudaController::class, 'confirm'], ['auth', 'role:gestor,administrador']);
$router->post('/gestor/familias/{id}/entregas/confirmar', [EntregaAjudaController::class, 'confirmStore'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/prestacao-contas', [PrestacaoContasController::class, 'index'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->post('/gestor/prestacao-contas/assinar', [PrestacaoContasController::class, 'sign'], ['auth', 'role:gestor,administrador']);
$router->post('/gestor/prestacao-contas/remover-assinatura', [PrestacaoContasController::class, 'removeSignature'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/relatorios', [RelatorioController::class, 'index'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/relatorios/exportar', [RelatorioController::class, 'export'], ['auth', 'role:gestor,administrador']);
$router->get('/gestor/recomecar', [RecomecarController::class, 'index'], ['auth', 'role:gestor,administrador']);
$router->post('/gestor/recomecar/assinar', [RecomecarController::class, 'sign'], ['auth', 'role:gestor,administrador']);
$router->post('/gestor/recomecar/remover-assinatura', [RecomecarController::class, 'removeSignature'], ['auth', 'role:gestor,administrador']);
$router->get('/acao/{token}/residencias/novo', [ResidenciaController::class, 'createFromAction'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->post('/acao/{token}/residencias', [ResidenciaController::class, 'storeFromAction'], ['auth', 'role:cadastrador,gestor,administrador']);
$router->get('/acao/{token}', [PublicAcaoController::class, 'show']);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $exception) {
    $appConfig = require BASE_PATH . '/config/app.php';

    if (!empty($appConfig['debug'])) {
        throw $exception;
    }

    error_log($exception->getMessage());
    http_response_code(500);
    View::render('errors.500', ['title' => 'Erro interno']);
}
