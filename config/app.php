<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'Cadastro Emergencial'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => filter_var(Env::get('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => Env::get('APP_URL', 'http://localhost/cadastro-emergencial/public'),
    'timezone' => Env::get('APP_TIMEZONE', 'America/Fortaleza'),
];
