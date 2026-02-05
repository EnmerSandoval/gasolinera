<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Request;
use App\models\CorteDiario;
use App\models\Tanque;

/**
 * Controlador de Inventario y Control Volumétrico.
 */
final class InventarioController
{
    /**
     * GET /inventario/tanques
     */
    public function tanques(Request $request): void
    {
        $sucursalId = $request->sucursalId();
        if ($sucursalId === null) {
            jsonError('Debe especificar una sucursal.', 400);
        }

        $model = new Tanque();
        $tanques = $model->listBySucursal($sucursalId);

        jsonResponse(['data' => $tanques]);
    }

    /**
     * POST /inventario/corte-diario
     *
     * Body:
     * {
     *   "tanque_id": 1,
     *   "fecha": "2025-01-15",
     *   "stock_inicial": 5000.00,
     *   "stock_final_fisico": 4200.50,
     *   "notas": ""
     * }
     */
    public function registrarCorte(Request $request): void
    {
        $sucursalId = $request->sucursalId();
        if ($sucursalId === null) {
            jsonError('Debe especificar una sucursal.', 400);
        }

        $errors = $request->validate([
            'tanque_id'          => 'int',
            'fecha'              => 'string',
            'stock_inicial'      => 'float',
            'stock_final_fisico' => 'float',
        ]);
        if ($errors) {
            jsonError('Datos inválidos.', 422, $errors);
        }

        $tanqueId       = (int) $request->input('tanque_id');
        $fecha          = sanitize($request->input('fecha'));
        $stockInicial   = (float) $request->input('stock_inicial');
        $stockFinal     = (float) $request->input('stock_final_fisico');

        try {
            $corteModel = new CorteDiario();
            $data = $corteModel->calcularCorte(
                $sucursalId,
                $tanqueId,
                $fecha,
                $stockInicial,
                $stockFinal,
                $request->userId()
            );

            $data['notas'] = sanitize($request->input('notas', ''));
            $id = $corteModel->guardarCorte($data);

            // Actualizar stock del tanque
            $tanqueModel = new Tanque();
            $tanqueModel->update($tanqueId, ['stock_actual' => $stockFinal]);

            jsonResponse([
                'data' => array_merge(['id' => $id], $data),
                'message' => 'Corte diario registrado.',
                'resumen' => [
                    'stock_inicial'       => $stockInicial,
                    'compras_dia'         => $data['compras_dia'],
                    'ventas_dia'          => $data['ventas_dia'],
                    'stock_final_teorico' => $data['stock_final_teorico'],
                    'stock_final_fisico'  => $stockFinal,
                    'variacion_galones'   => $data['variacion'],
                    'variacion_porcentaje'=> $data['porcentaje_variacion'],
                    'tipo' => $data['variacion'] < 0 ? 'MERMA' : ($data['variacion'] > 0 ? 'SOBRANTE' : 'EXACTO'),
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage(), 422);
        }
    }

    /**
     * GET /inventario/mermas
     * Params: ?fecha_desde=YYYY-MM-DD&fecha_hasta=YYYY-MM-DD
     */
    public function reporteMermas(Request $request): void
    {
        $fechaDesde = $request->query('fecha_desde', date('Y-m-01'));
        $fechaHasta = $request->query('fecha_hasta', date('Y-m-d'));

        $corteModel = new CorteDiario();
        $sucursalId = $request->sucursalId();

        if ($sucursalId !== null) {
            $data = $corteModel->reporteMermas($sucursalId, $fechaDesde, $fechaHasta);
            jsonResponse(['data' => $data]);
        }

        // Consolidado
        $data = $corteModel->reporteMermasConsolidado($request->empresaId(), $fechaDesde, $fechaHasta);
        jsonResponse(['data' => $data]);
    }

    /**
     * GET /inventario/movimientos
     * Params: ?tanque_id=1&fecha_desde=YYYY-MM-DD&fecha_hasta=YYYY-MM-DD
     */
    public function movimientos(Request $request): void
    {
        $sucursalId = $request->sucursalId();
        if ($sucursalId === null) {
            jsonError('Debe especificar una sucursal.', 400);
        }

        $tanqueId   = $request->query('tanque_id');
        $fechaDesde = $request->query('fecha_desde', date('Y-m-d'));
        $fechaHasta = $request->query('fecha_hasta', date('Y-m-d'));

        $pdo = \App\core\Database::getInstance()->getConnection();

        $sql = 'SELECT mc.*, tc.nombre AS combustible_nombre, t.codigo AS tanque_codigo,
                       u.nombre_completo AS usuario_nombre
                FROM movimientos_combustible mc
                JOIN tipos_combustible tc ON tc.id = mc.tipo_combustible_id
                JOIN tanques t ON t.id = mc.tanque_id
                JOIN usuarios u ON u.id = mc.usuario_id
                WHERE mc.sucursal_id = :sid
                  AND DATE(mc.fecha) BETWEEN :desde AND :hasta';
        $params = [':sid' => $sucursalId, ':desde' => $fechaDesde, ':hasta' => $fechaHasta];

        if ($tanqueId !== null) {
            $sql .= ' AND mc.tanque_id = :tid';
            $params[':tid'] = (int) $tanqueId;
        }

        $sql .= ' ORDER BY mc.fecha DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['data' => $stmt->fetchAll()]);
    }
}
