<?php

declare(strict_types=1);

namespace App\middleware;

use App\core\Request;

/**
 * Middleware de autorización por rol.
 * Verifica que el usuario tenga uno de los roles permitidos.
 *
 * Uso:
 *   new RoleMiddleware('admin', 'gerente')
 */
final class RoleMiddleware
{
    /** @var string[] */
    private array $allowedRoles;

    public function __construct(string ...$roles)
    {
        $this->allowedRoles = $roles;
    }

    public function __invoke(Request $request, callable $next): void
    {
        $userRole = $request->rolNombre();

        if (!in_array($userRole, $this->allowedRoles, true)) {
            jsonError(
                'No tiene permisos para realizar esta acción.',
                403
            );
        }

        $next($request);
    }
}
