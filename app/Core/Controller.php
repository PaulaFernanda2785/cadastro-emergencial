<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    protected function abort(int $statusCode = 404): void
    {
        http_response_code($statusCode);
        View::render('errors.' . $statusCode, ['title' => 'Erro ' . $statusCode]);
        exit;
    }
}
