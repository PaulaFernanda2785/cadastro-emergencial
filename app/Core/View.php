<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $viewPath = BASE_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($viewPath)) {
            throw new RuntimeException("View não encontrada: {$view}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutPath = BASE_PATH . '/resources/views/layouts/' . $layout . '.php';

        if (!is_file($layoutPath)) {
            throw new RuntimeException("Layout não encontrado: {$layout}");
        }

        require $layoutPath;
    }
}
