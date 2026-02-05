<?php

declare(strict_types=1);

namespace App\middleware;

use App\core\Request;
use App\services\JwtService;

/**
 * Middleware de autenticación JWT.
 * Verifica el token Bearer y puebla $request->user().
 */
final class AuthMiddleware
{
    public function __invoke(Request $request, callable $next): void
    {
        $token = $request->bearerToken();

        if ($token === null) {
            jsonError('Token de autenticación requerido.', 401);
        }

        $jwt = new JwtService();
        $payload = $jwt->verifyAccessToken($token);

        if ($payload === null) {
            jsonError('Token inválido o expirado.', 401);
        }

        $request->setUser($payload);

        $next($request);
    }
}
