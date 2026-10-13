<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    public function get(string $path, callable $handler): void { $this->routes[] = ['GET', $path, $handler]; }
    public function post(string $path, callable $handler): void { $this->routes[] = ['POST', $path, $handler]; }

    public function dispatch(string $method, string $uri): void
    {
        if ($method === 'HEAD') $method = 'GET';
        $path = rawurldecode('/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/'));
        if ($path === '//') $path = '/';
        foreach ($this->routes as [$verb, $route, $handler]) {
            if ($verb !== $method) continue;
            $pattern = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[\p{L}\p{M}\p{N}_-]+)', $route) . '/?$#u';
            if (preg_match($pattern, $path, $matches)) {
                $handler(...array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)));
                return;
            }
        }
        abort(404, 'The page you requested could not be found.');
    }
}
