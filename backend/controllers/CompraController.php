<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Request;
use App\models\Compra;

/**
 * Controlador de Compras (Facturas de pipas/cisterna).
 * Maneja el registro de compras y asignación a tanques.
 */
final class CompraController
{
    /**
     * GET /compras
     * Params: ?fecha_desde=YYYY-MM-DD&fecha_hasta=YYYY-MM-DD
     */
    public function index(Request $request): void
    {
        $sucursalId = $request->sucursalId();
        if ($sucursalId === null) {
            jsonError('Debe especificar una sucursal.', 400);
        }

        $fechaDesde = $request->query('fecha_desde', date('Y-m-01'));
        $fechaHasta = $request->query('fecha_hasta', date('Y-m-d'));

        $model = new Compra();
        $compras = $model->listBySucursal($sucursalId, $fechaDesde, $fechaHasta);

        jsonResponse(['data' => $compras]);
    }

    /**
     * POST /compras
     *
     * Body:
     * {
     *   "proveedor_id": 1,
     *   "numero_factura": "FAC-001",
     *   "fecha_factura": "2025-01-15",
     *   "detalle": [
     *     { "tanque_id": 1, "tipo_combustible_id": 1, "galones": 5000, "precio_unitario": 18.50, "idp_unitario": 4.70 }
     *   ],
     *   "notas": ""
     * }
     */
    public function store(Request $request): void
    {
        $sucursalId = $request->sucursalId();
        if ($sucursalId === null) {
            jsonError('Debe especificar una sucursal.', 400);
        }

        $errors = $request->validate([
            'proveedor_id'   => 'int',
            'numero_factura' => 'string',
            'fecha_factura'  => 'string',
        ]);
        if ($errors) {
            jsonError('Datos inválidos.', 422, $errors);
        }

        $detalleInput = $request->input('detalle', []);
        if (empty($detalleInput)) {
            jsonError('Debe incluir al menos una línea de detalle.', 422);
        }

        // Calcular totales
        $config   = require dirname(__DIR__) . '/config/app.php';
        $ivaRate  = $config['iva_rate'];
        $detalles = [];
        $subtotal = 0;
        $idpTotal = 0;

        foreach ($detalleInput as $linea) {
            $galones        = (float) ($linea['galones'] ?? 0);
            $precioUnitario = (float) ($linea['precio_unitario'] ?? 0);
            $idpUnitario    = (float) ($linea['idp_unitario'] ?? 0);

            if ($galones <= 0 || $precioUnitario <= 0) {
                jsonError('Galones y precio unitario deben ser mayores a cero.', 422);
            }

            $lineaSubtotal = round($galones * $precioUnitario, 2);
            $lineaIdp      = round($galones * $idpUnitario, 2);

            $detalles[] = [
                'tanque_id'           => (int) $linea['tanque_id'],
                'tipo_combustible_id' => (int) $linea['tipo_combustible_id'],
                'galones'             => $galones,
                'precio_unitario'     => $precioUnitario,
                'idp_unitario'        => $idpUnitario,
                'subtotal'            => $lineaSubtotal,
                'idp_total'           => $lineaIdp,
            ];

            $subtotal += $lineaSubtotal;
            $idpTotal += $lineaIdp;
        }

        $ivaTotal = round($subtotal * $ivaRate, 2);
        $total    = $subtotal + $idpTotal + $ivaTotal;

        $compraData = [
            'sucursal_id'    => $sucursalId,
            'proveedor_id'   => (int) $request->input('proveedor_id'),
            'numero_factura' => sanitize($request->input('numero_factura')),
            'fecha_factura'  => sanitize($request->input('fecha_factura')),
            'fecha_recepcion'=> date('Y-m-d H:i:s'),
            'subtotal'       => $subtotal,
            'idp_total'      => $idpTotal,
            'iva_total'      => $ivaTotal,
            'total'          => $total,
            'estado'         => 'pendiente',
            'usuario_id'     => $request->userId(),
            'notas'          => sanitize($request->input('notas', '')),
        ];

        try {
            $model = new Compra();
            $id = $model->crearCompraCompleta($compraData, $detalles);
            $compra = $model->find($id);

            jsonResponse([
                'data'    => $compra,
                'message' => 'Compra registrada. Tanques actualizados.',
            ], 201);
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage(), 422);
        }
    }

    /**
     * GET /compras/{id}
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $model = new Compra();
        $compra = $model->find($id);

        if (!$compra) {
            jsonError('Compra no encontrada.', 404);
        }

        // Obtener detalle
        $pdo = \App\core\Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT cd.*, tc.nombre AS combustible_nombre, t.codigo AS tanque_codigo
             FROM compra_detalle cd
             JOIN tipos_combustible tc ON tc.id = cd.tipo_combustible_id
             JOIN tanques t ON t.id = cd.tanque_id
             WHERE cd.compra_id = :id'
        );
        $stmt->execute([':id' => $id]);

        jsonResponse([
            'data' => [
                'compra'  => $compra,
                'detalle' => $stmt->fetchAll(),
            ],
        ]);
    }
}
