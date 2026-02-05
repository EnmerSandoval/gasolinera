<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Request;
use App\models\Precio;
use App\models\Turno;
use App\models\Vale;
use App\models\Venta;

/**
 * Controlador de Ventas (POS).
 *
 * Toda venta se vincula a:
 * - Una sucursal (via middleware)
 * - Un turno abierto del usuario
 * - Validación de stock, precios y crédito en tiempo real
 */
final class VentaController
{
    /**
     * GET /ventas
     * Params: ?fecha_desde=YYYY-MM-DD&fecha_hasta=YYYY-MM-DD&limit=50&offset=0
     */
    public function index(Request $request): void
    {
        $sucursalId = $request->sucursalId();
        if ($sucursalId === null) {
            jsonError('Debe especificar una sucursal.', 400);
        }

        $fechaDesde = $request->query('fecha_desde', date('Y-m-d'));
        $fechaHasta = $request->query('fecha_hasta', date('Y-m-d'));
        $limit      = (int) $request->query('limit', 50);
        $offset     = (int) $request->query('offset', 0);

        $model = new Venta();
        $ventas = $model->listBySucursal($sucursalId, $fechaDesde, $fechaHasta, $limit, $offset);

        jsonResponse(['data' => $ventas]);
    }

    /**
     * GET /ventas/{id}
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $model = new Venta();
        $venta = $model->find($id);

        if (!$venta) {
            jsonError('Venta no encontrada.', 404);
        }

        // Verificar que pertenece a una sucursal accesible
        $sucursalId = $request->sucursalId();
        if ($sucursalId !== null && (int) $venta['sucursal_id'] !== $sucursalId) {
            jsonError('No tiene acceso a esta venta.', 403);
        }

        // Obtener detalles
        $pdo = \App\core\Database::getInstance()->getConnection();

        $stmtComb = $pdo->prepare(
            'SELECT vc.*, tc.nombre AS combustible_nombre
             FROM venta_combustible vc
             JOIN tipos_combustible tc ON tc.id = vc.tipo_combustible_id
             WHERE vc.venta_id = :id'
        );
        $stmtComb->execute([':id' => $id]);

        $stmtProd = $pdo->prepare(
            'SELECT vp.*, p.nombre AS producto_nombre
             FROM venta_productos vp
             JOIN productos p ON p.id = vp.producto_id
             WHERE vp.venta_id = :id'
        );
        $stmtProd->execute([':id' => $id]);

        jsonResponse([
            'data' => [
                'venta'        => $venta,
                'combustibles' => $stmtComb->fetchAll(),
                'productos'    => $stmtProd->fetchAll(),
            ],
        ]);
    }

    /**
     * POST /ventas
     *
     * Body esperado:
     * {
     *   "forma_pago": "efectivo|tarjeta|vale|mixto",
     *   "monto_efectivo": 0,
     *   "monto_tarjeta": 0,
     *   "monto_vale": 0,
     *   "referencia_tarjeta": null,
     *   "vale_codigo": null,
     *   "cliente_id": null,
     *   "combustibles": [
     *     { "manguera_id": 1, "galones": 5.5, "lectura_inicial": 1000, "lectura_final": 1005.5 }
     *   ],
     *   "productos": [
     *     { "producto_id": 1, "cantidad": 2 }
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

        $userId = $request->userId();

        // Verificar turno abierto
        $turnoModel = new Turno();
        $turno = $turnoModel->turnoAbierto($userId, $sucursalId);
        if (!$turno) {
            jsonError('No tiene un turno abierto. Abra turno primero.', 400);
        }

        $formaPago = $request->input('forma_pago', 'efectivo');
        $combustiblesInput = $request->input('combustibles', []);
        $productosInput    = $request->input('productos', []);

        if (empty($combustiblesInput) && empty($productosInput)) {
            jsonError('La venta debe incluir al menos un combustible o producto.', 422);
        }

        // Calcular totales de combustible
        $precioModel  = new Precio();
        $config       = require dirname(__DIR__) . '/config/app.php';
        $ivaRate      = $config['iva_rate'];
        $combustibles = [];
        $subtotalComb = 0;
        $idpTotalComb = 0;

        $pdo = \App\core\Database::getInstance()->getConnection();

        foreach ($combustiblesInput as $linea) {
            $mangueraId = (int) ($linea['manguera_id'] ?? 0);
            $galones    = (float) ($linea['galones'] ?? 0);

            if ($mangueraId <= 0 || $galones <= 0) {
                jsonError('Datos de combustible inválidos.', 422);
            }

            // Obtener datos de la manguera (tanque, tipo combustible)
            $stmt = $pdo->prepare(
                'SELECT m.*, b.sucursal_id
                 FROM mangueras m
                 JOIN bombas b ON b.id = m.bomba_id
                 WHERE m.id = :id AND m.activo = 1'
            );
            $stmt->execute([':id' => $mangueraId]);
            $manguera = $stmt->fetch();

            if (!$manguera || (int) $manguera['sucursal_id'] !== $sucursalId) {
                jsonError("Manguera {$mangueraId} no encontrada o no pertenece a esta sucursal.", 422);
            }

            // Obtener precio vigente
            $precio = $precioModel->precioVigente($sucursalId, (int) $manguera['tipo_combustible_id']);
            if (!$precio) {
                jsonError('No hay precio vigente para este combustible.', 422);
            }

            $precioUnitario = (float) $precio['precio_unitario'];
            $idpUnitario    = (float) $precio['idp_por_galon'];
            $subtotal       = round($galones * $precioUnitario, 2);
            $idpTotal       = round($galones * $idpUnitario, 2);

            $combustibles[] = [
                'manguera_id'         => $mangueraId,
                'tipo_combustible_id' => (int) $manguera['tipo_combustible_id'],
                'tanque_id'           => (int) $manguera['tanque_id'],
                'galones'             => $galones,
                'precio_unitario'     => $precioUnitario,
                'idp_unitario'        => $idpUnitario,
                'subtotal'            => $subtotal,
                'idp_total'           => $idpTotal,
                'lectura_inicial'     => $linea['lectura_inicial'] ?? null,
                'lectura_final'       => $linea['lectura_final'] ?? null,
            ];

            $subtotalComb += $subtotal;
            $idpTotalComb += $idpTotal;
        }

        // Calcular totales de productos
        $productos     = [];
        $subtotalProd  = 0;

        foreach ($productosInput as $linea) {
            $productoId = (int) ($linea['producto_id'] ?? 0);
            $cantidad   = (float) ($linea['cantidad'] ?? 0);

            if ($productoId <= 0 || $cantidad <= 0) {
                jsonError('Datos de producto inválidos.', 422);
            }

            $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = :id AND activo = 1');
            $stmt->execute([':id' => $productoId]);
            $producto = $stmt->fetch();

            if (!$producto) {
                jsonError("Producto {$productoId} no encontrado.", 422);
            }

            $precioUnit = (float) $producto['precio_venta'];
            $subtotal   = round($cantidad * $precioUnit, 2);

            $productos[] = [
                'producto_id'     => $productoId,
                'cantidad'        => $cantidad,
                'precio_unitario' => $precioUnit,
                'subtotal'        => $subtotal,
            ];

            $subtotalProd += $subtotal;
        }

        // Totales del ticket
        $subtotal = $subtotalComb + $subtotalProd;
        $ivaTotal = round($subtotal * $ivaRate / (1 + $ivaRate), 2); // IVA incluido en precio Guatemala
        $total    = $subtotal + $idpTotalComb;

        // Validar forma de pago con vale
        $valeId    = null;
        $clienteId = $request->input('cliente_id') !== null ? (int) $request->input('cliente_id') : null;

        if ($formaPago === 'vale' || $formaPago === 'mixto') {
            $valeCodigo = $request->input('vale_codigo');
            if (!$valeCodigo) {
                jsonError('Código de vale requerido.', 422);
            }

            $valeModel  = new Vale();
            $montoVale  = (float) $request->input('monto_vale', $total);
            $validacion = $valeModel->validarVale($valeCodigo, $request->empresaId(), $sucursalId, $montoVale);

            if (!$validacion['valid']) {
                jsonError($validacion['error'], 422);
            }

            $valeId    = (int) $validacion['vale']['id'];
            $clienteId = (int) $validacion['vale']['cliente_id'];
        }

        // Armar datos de venta
        $ventaModel = new Venta();
        $ventaData = [
            'sucursal_id'        => $sucursalId,
            'turno_id'           => (int) $turno['id'],
            'usuario_id'         => $userId,
            'numero_ticket'      => $ventaModel->nextTicketNumber($sucursalId),
            'fecha'              => date('Y-m-d H:i:s'),
            'subtotal'           => $subtotal,
            'idp_total'          => $idpTotalComb,
            'iva_total'          => $ivaTotal,
            'total'              => $total,
            'forma_pago'         => $formaPago,
            'monto_efectivo'     => (float) $request->input('monto_efectivo', 0),
            'monto_tarjeta'      => (float) $request->input('monto_tarjeta', 0),
            'monto_vale'         => (float) $request->input('monto_vale', 0),
            'referencia_tarjeta' => sanitize($request->input('referencia_tarjeta', '')),
            'vale_id'            => $valeId,
            'cliente_id'         => $clienteId,
            'estado'             => 'completada',
            'notas'              => sanitize($request->input('notas', '')),
        ];

        try {
            $ventaId = $ventaModel->crearVentaCompleta($ventaData, $combustibles, $productos);

            // Si pagó con vale, consumir saldo
            if ($valeId) {
                $totalGalones = array_sum(array_column($combustibles, 'galones'));
                (new Vale())->consumir($valeId, $clienteId, (float) $request->input('monto_vale', 0), $totalGalones);
            }

            $venta = $ventaModel->find($ventaId);
            jsonResponse([
                'data'    => $venta,
                'message' => 'Venta registrada correctamente.',
            ], 201);
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage(), 422);
        }
    }

    /**
     * GET /ventas/totales
     * Params: ?fecha=YYYY-MM-DD
     */
    public function totales(Request $request): void
    {
        $fecha = $request->query('fecha', date('Y-m-d'));
        $model = new Venta();

        $sucursalId = $request->sucursalId();

        if ($sucursalId !== null) {
            $totales = $model->totalsBySucursal($sucursalId, $fecha);
            jsonResponse(['data' => $totales]);
        }

        // Consolidado (admin)
        $consolidado = $model->totalsConsolidados($request->empresaId(), $fecha);
        jsonResponse(['data' => $consolidado]);
    }

    /**
     * POST /ventas/{id}/anular
     */
    public function anular(Request $request): void
    {
        $id = (int) $request->param('id');
        $model = new Venta();
        $venta = $model->find($id);

        if (!$venta) {
            jsonError('Venta no encontrada.', 404);
        }

        $sucursalId = $request->sucursalId();
        if ($sucursalId !== null && (int) $venta['sucursal_id'] !== $sucursalId) {
            jsonError('No tiene acceso a esta venta.', 403);
        }

        if ($venta['estado'] === 'anulada') {
            jsonError('La venta ya está anulada.', 422);
        }

        $model->update($id, ['estado' => 'anulada']);

        jsonResponse(['message' => 'Venta anulada correctamente.']);
    }
}
