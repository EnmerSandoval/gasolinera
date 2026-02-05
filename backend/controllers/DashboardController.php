<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Database;
use App\core\Request;
use App\models\Venta;

/**
 * Controlador del Dashboard.
 * Provee datos consolidados e individuales para el panel principal.
 */
final class DashboardController
{
    /**
     * GET /dashboard
     * Params: ?fecha=YYYY-MM-DD
     *
     * Si el usuario es admin (sin sucursal fija), devuelve consolidado.
     * Si es islero/gerente de sucursal, devuelve datos de su sucursal.
     */
    public function index(Request $request): void
    {
        $fecha = $request->query('fecha', date('Y-m-d'));
        $empresaId  = $request->empresaId();
        $sucursalId = $request->sucursalId();

        $pdo = Database::getInstance()->getConnection();
        $ventaModel = new Venta();

        if ($sucursalId !== null) {
            // Dashboard de sucursal individual
            $ventasTotales = $ventaModel->totalsBySucursal($sucursalId, $fecha);

            // Niveles de tanques
            $stmtTanques = $pdo->prepare(
                'SELECT t.codigo, t.stock_actual, t.capacidad_galones,
                        ROUND((t.stock_actual / t.capacidad_galones) * 100, 1) AS porcentaje,
                        tc.nombre AS combustible
                 FROM tanques t
                 JOIN tipos_combustible tc ON tc.id = t.tipo_combustible_id
                 WHERE t.sucursal_id = :sid AND t.activo = 1
                 ORDER BY t.codigo'
            );
            $stmtTanques->execute([':sid' => $sucursalId]);

            // Alertas: tanques con stock bajo
            $stmtAlertas = $pdo->prepare(
                'SELECT t.codigo, t.stock_actual, t.nivel_minimo, tc.nombre AS combustible
                 FROM tanques t
                 JOIN tipos_combustible tc ON tc.id = t.tipo_combustible_id
                 WHERE t.sucursal_id = :sid AND t.activo = 1
                   AND t.stock_actual <= t.nivel_minimo'
            );
            $stmtAlertas->execute([':sid' => $sucursalId]);

            jsonResponse([
                'data' => [
                    'tipo'           => 'sucursal',
                    'fecha'          => $fecha,
                    'ventas'         => $ventasTotales,
                    'tanques'        => $stmtTanques->fetchAll(),
                    'alertas_stock'  => $stmtAlertas->fetchAll(),
                ],
            ]);
        } else {
            // Dashboard consolidado (admin)
            $consolidado = $ventaModel->totalsConsolidados($empresaId, $fecha);

            // Totales generales
            $totalVendido  = array_sum(array_column($consolidado, 'total_vendido'));
            $totalTickets  = array_sum(array_column($consolidado, 'total_tickets'));

            // Alertas globales
            $stmtAlertas = $pdo->prepare(
                'SELECT s.nombre AS sucursal, t.codigo AS tanque, t.stock_actual,
                        t.nivel_minimo, tc.nombre AS combustible
                 FROM tanques t
                 JOIN sucursales s ON s.id = t.sucursal_id
                 JOIN tipos_combustible tc ON tc.id = t.tipo_combustible_id
                 WHERE s.empresa_id = :eid AND t.activo = 1
                   AND t.stock_actual <= t.nivel_minimo'
            );
            $stmtAlertas->execute([':eid' => $empresaId]);

            jsonResponse([
                'data' => [
                    'tipo'              => 'consolidado',
                    'fecha'             => $fecha,
                    'total_vendido'     => $totalVendido,
                    'total_tickets'     => $totalTickets,
                    'por_sucursal'      => $consolidado,
                    'alertas_stock'     => $stmtAlertas->fetchAll(),
                ],
            ]);
        }
    }
}
