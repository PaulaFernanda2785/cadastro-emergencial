<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[$method][$this->normalizePath($path)] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalizePath(parse_url($uri, PHP_URL_PATH) ?: '/');
        $basePath = $this->basePath();

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
            $path = $this->normalizePath($path);
        }

        $route = $this->routes[$method][$path] ?? null;
        $params = [];

        if ($route === null) {
            [$route, $params] = $this->matchDynamicRoute($method, $path);
        }

        if ($route === null) {
            http_response_code(404);
            View::render('errors.404', ['title' => 'Pagina nao encontrada']);
            return;
        }

        Middleware::handle($route['middleware']);

        [$controllerClass, $action] = $route['handler'];
        $controller = new $controllerClass();
        $controller->{$action}(...array_values($params));
    }

    private function matchDynamicRoute(string $method, string $path): array
    {
        foreach ($this->routes[$method] ?? [] as $routePath => $route) {
            if (!str_contains($routePath, '{')) {
                continue;
            }

            $paramNames = [];
            $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];

                return '__PARAM__';
            }, $routePath);

            $pattern = preg_quote($pattern, '#');
            $pattern = str_replace('__PARAM__', '([^/]+)', $pattern);

            if (preg_match('#^' . $pattern . '$#', $path, $matches) !== 1) {
                continue;
            }

            array_shift($matches);
            $params = [];

            foreach ($paramNames as $index => $name) {
                $params[$name] = urldecode($matches[$index] ?? '');
            }

            return [$route, $params];
        }

        return [null, []];
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function basePath(): string
    {
        $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($scriptName === '/' || $scriptName === '\\' || $scriptName === '.') {
            return '';
        }

        return rtrim($scriptName, '/');
    }
}
