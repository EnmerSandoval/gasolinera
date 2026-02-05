<?php

declare(strict_types=1);

/**
 * Punto de entrada de la API REST.
 *
 * Todas las peticiones pasan por aquí vía .htaccess o configuración de nginx.
 */

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
