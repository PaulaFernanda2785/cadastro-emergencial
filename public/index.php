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
use App\Controllers\DashboardController;
use App\Controllers\Admin\AcaoEmergencialController;
use App\Controllers\Admin\TipoAjudaController;
use App\Controllers\PublicAcaoController;
use App\Core\Env;
use App\Core\Router;
use App\Core\Session;

Env::load(BASE_PATH . '/.env');

$app = require BASE_PATH . '/config/app.php';
date_default_timezone_set((string) $app['timezone']);

Session::start();

$router = new Router();

$router->get('/', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/admin', [DashboardController::class, 'admin'], ['auth', 'role:administrador']);
$router->get('/admin/ajudas', [TipoAjudaController::class, 'index'], ['auth', 'role:administrador']);
$router->get('/admin/ajudas/novo', [TipoAjudaController::class, 'create'], ['auth', 'role:administrador']);
$router->post('/admin/ajudas', [TipoAjudaController::class, 'store'], ['auth', 'role:administrador']);
$router->get('/admin/ajudas/{id}/editar', [TipoAjudaController::class, 'edit'], ['auth', 'role:administrador']);
$router->post('/admin/ajudas/{id}', [TipoAjudaController::class, 'update'], ['auth', 'role:administrador']);
$router->get('/admin/acoes', [AcaoEmergencialController::class, 'index'], ['auth', 'role:administrador']);
$router->get('/admin/acoes/novo', [AcaoEmergencialController::class, 'create'], ['auth', 'role:administrador']);
$router->post('/admin/acoes', [AcaoEmergencialController::class, 'store'], ['auth', 'role:administrador']);
$router->get('/admin/acoes/{id}/editar', [AcaoEmergencialController::class, 'edit'], ['auth', 'role:administrador']);
$router->post('/admin/acoes/{id}', [AcaoEmergencialController::class, 'update'], ['auth', 'role:administrador']);
$router->get('/acao/{token}', [PublicAcaoController::class, 'show']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
