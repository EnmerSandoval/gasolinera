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

// Forzar Content-Type JSON desde el inicio para que errores de PHP no salgan como HTML
header('Content-Type: application/json; charset=utf-8');

// Capturar errores fatales y warnings como JSON (no HTML)
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'error'   => true,
            'message' => $error['message'],
            'file'    => $error['file'],
            'line'    => $error['line'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

// Servidor built-in de PHP: servir archivos estáticos directamente
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        header('Content-Type: '); // Reset para archivos estáticos
        return false;
    }
}

// Bootstrap
try {
    $config = require dirname(__DIR__) . '/config/bootstrap.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => true,
        'message' => 'Error en bootstrap: ' . $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
