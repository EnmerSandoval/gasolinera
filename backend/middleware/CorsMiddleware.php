<?php

declare(strict_types=1);

namespace App\middleware;

use App\core\Request;

/**
 * Middleware CORS - Maneja preflight y headers de acceso cruzado.
 */
final class CorsMiddleware
{
    public function __invoke(Request $request, callable $next): void
    {
        $config = require dirname(__DIR__) . '/config/app.php';
        $cors = $config['cors'];

        $origin = $request->header('origin') ?? '';

        // Verificar si el origen está permitido
        $allowed = in_array($origin, $cors['allowed_origins'], true) || in_array('*', $cors['allowed_origins'], true);

        if ($allowed && $origin !== '') {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $cors['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $cors['allowed_headers']));
        header('Access-Control-Max-Age: ' . $cors['max_age']);

        // Preflight (OPTIONS) - responder inmediatamente
        if ($request->method() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $next($request);
    }
}
