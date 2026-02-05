<?php

declare(strict_types=1);

namespace App\core;

/**
 * Router HTTP ligero con soporte para:
 * - Parámetros dinámicos: /ventas/{id}
 * - Grupos con prefijo
 * - Middleware por ruta o grupo
 */
final class Router
{
    /** @var array<string, array> Rutas registradas agrupadas por método HTTP */
    private array $routes = [];

    /** @var array<callable> Middleware global */
    private array $globalMiddleware = [];

    /** @var string Prefijo actual del grupo */
    private string $groupPrefix = '';

    /** @var array<callable> Middleware del grupo actual */
    private array $groupMiddleware = [];

    /**
     * Registra middleware global que se ejecuta en TODAS las rutas.
     */
    public function use(callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Agrupa rutas bajo un prefijo y/o middleware comunes.
     *
     * @param string   $prefix     Prefijo de URL, ej: '/ventas'
     * @param callable $callback   Closure que define las rutas del grupo
     * @param array    $middleware  Middleware específicos del grupo
     */
    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        $previousPrefix     = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix     .= $prefix;
        $this->groupMiddleware  = array_merge($this->groupMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix     = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;

        return $this;
    }

    // --- Métodos de registro de rutas ---

    public function get(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Resuelve y ejecuta la ruta correspondiente al request.
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = rtrim($request->path(), '/') ?: '/';

        // Buscar coincidencia
        $methodRoutes = $this->routes[$method] ?? [];

        foreach ($methodRoutes as $route) {
            $params = $this->matchRoute($route['pattern'], $path);
            if ($params !== false) {
                $request->setParams($params);

                // Construir pipeline: global -> grupo -> ruta -> handler
                $allMiddleware = array_merge(
                    $this->globalMiddleware,
                    $route['middleware']
                );

                $this->executePipeline($allMiddleware, $route['handler'], $request);
                return;
            }
        }

        // 404
        jsonError('Recurso no encontrado.', 404);
    }

    // --- Internals ---

    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): self
    {
        $fullPath = $this->groupPrefix . $path;
        $pattern  = $this->compilePattern($fullPath);

        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware'  => array_merge($this->groupMiddleware, $middleware),
        ];

        return $this;
    }

    /**
     * Convierte /ventas/{id} en un regex con named groups.
     */
    private function compilePattern(string $path): string
    {
        $path = rtrim($path, '/') ?: '/';
        // Escapar /
        $pattern = preg_replace_callback('/\{(\w+)\}/', function ($m) {
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $path);

        return '#^' . $pattern . '$#';
    }

    /**
     * Intenta hacer match de una ruta. Devuelve los params o false.
     */
    private function matchRoute(string $pattern, string $path): array|false
    {
        if (preg_match($pattern, $path, $matches)) {
            // Filtrar solo named groups
            return array_filter($matches, fn ($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
        }
        return false;
    }

    /**
     * Ejecuta middleware en cadena (pipeline) y finalmente el handler.
     */
    private function executePipeline(array $middleware, callable|array $handler, Request $request): void
    {
        $stack = array_reverse($middleware);

        $next = function (Request $req) use ($handler): void {
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $controller = new $class();
                $controller->$method($req);
            } else {
                $handler($req);
            }
        };

        foreach ($stack as $mw) {
            $current = $next;
            $next = function (Request $req) use ($mw, $current): void {
                $mw($req, $current);
            };
        }

        $next($request);
    }
}
