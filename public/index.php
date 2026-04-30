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

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
