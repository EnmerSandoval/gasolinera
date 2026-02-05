<?php

declare(strict_types=1);

namespace App\middleware;

use App\core\Database;
use App\core\Request;

/**
 * Middleware de aislamiento por sucursal.
 *
 * Reglas:
 * - Si el usuario está vinculado a una sucursal (sucursal_id != null en JWT),
 *   SOLO puede operar en esa sucursal (seguridad de "Gasolinera A no ve Gasolinera B").
 * - Si el usuario es admin/gerente (sucursal_id == null), puede elegir sucursal
 *   vía header X-Sucursal-Id o query param sucursal_id.
 * - La sucursal elegida debe pertenecer a la misma empresa del usuario.
 */
final class SucursalMiddleware
{
    public function __invoke(Request $request, callable $next): void
    {
        $userSucursalId = $request->userSucursalId();
        $empresaId      = $request->empresaId();

        if ($userSucursalId !== null) {
            // Usuario vinculado a sucursal fija
            $request->setSucursalId($userSucursalId);
        } else {
            // Admin: intentar obtener sucursal del header o query
            $sucursalId = $request->sucursalId();

            if ($sucursalId !== null) {
                // Validar que la sucursal pertenece a la empresa
                if (!$this->sucursalPerteneceAEmpresa($sucursalId, $empresaId)) {
                    jsonError('No tiene acceso a esta sucursal.', 403);
                }
                $request->setSucursalId($sucursalId);
            }
            // Si no se especifica, el controlador puede manejar reportes consolidados
        }

        $next($request);
    }

    private function sucursalPerteneceAEmpresa(int $sucursalId, int $empresaId): bool
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM sucursales WHERE id = :id AND empresa_id = :empresa_id AND activo = 1'
        );
        $stmt->execute([':id' => $sucursalId, ':empresa_id' => $empresaId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
