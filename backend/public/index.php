<?php

declare(strict_types=1);

/**
 * Punto de entrada de la API REST.
 *
 * Funciona con:
 * - Apache (.htaccess con mod_rewrite)
 * - php -S localhost:8080 public/router.php
 * - WampServer (VirtualHost apuntando aquí)
 */

// Servidor built-in de PHP: servir archivos estáticos directamente
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

// Bootstrap
$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\core\Request;
use App\core\Router;

// Manejo global de excepciones
set_exception_handler(function (\Throwable $e) {
    $debug = env('APP_DEBUG', false);
    $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;

    jsonError(
        $debug ? $e->getMessage() : 'Error interno del servidor.',
        $code,
        $debug ? ['file' => $e->getFile(), 'line' => $e->getLine()] : []
    );
});

// Crear instancias
$request = new Request();
$router  = new Router();

// Cargar definición de rutas
require dirname(__DIR__) . '/config/routes.php';

// Despachar
$router->dispatch($request);
