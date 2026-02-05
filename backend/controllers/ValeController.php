<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Request;
use App\models\Cliente;
use App\models\Vale;

/**
 * Controlador de Vales y Créditos Corporativos.
 */
final class ValeController
{
    /**
     * GET /vales
     * Params: ?estado=activo
     */
    public function index(Request $request): void
    {
        $estado = $request->query('estado');
        $model = new Vale();
        $vales = $model->listByEmpresa($request->empresaId(), $estado);

        jsonResponse(['data' => $vales]);
    }

    /**
     * GET /vales/validar/{codigo}
     * Valida en tiempo real si un vale puede ser usado.
     * Params: ?monto=100.00
     */
    public function validar(Request $request): void
    {
        $codigo = $request->param('codigo');
        $monto  = (float) $request->query('monto', 0);

        if (!$codigo || $monto <= 0) {
            jsonError('Código de vale y monto requeridos.', 422);
        }

        $model = new Vale();
        $result = $model->validarVale(
            $codigo,
            $request->empresaId(),
            $request->sucursalId(),
            $monto
        );

        if ($result['valid']) {
            $vale = $result['vale'];
            jsonResponse([
                'valid'   => true,
                'vale'    => [
                    'id'                => $vale['id'],
                    'codigo'            => $vale['codigo'],
                    'cliente'           => $vale['cliente_nombre'],
                    'monto_autorizado'  => (float) $vale['monto_autorizado'],
                    'monto_consumido'   => (float) $vale['monto_consumido'],
                    'saldo_disponible'  => (float) $vale['monto_autorizado'] - (float) $vale['monto_consumido'],
                    'placa_vehiculo'    => $vale['placa_vehiculo'],
                    'piloto'            => $vale['piloto'],
                    'fecha_vencimiento' => $vale['fecha_vencimiento'],
                ],
            ]);
        } else {
            jsonResponse([
                'valid' => false,
                'error' => $result['error'],
            ]);
        }
    }

    /**
     * POST /vales
     */
    public function store(Request $request): void
    {
        $errors = $request->validate([
            'cliente_id'       => 'int',
            'monto_autorizado' => 'float',
            'fecha_vencimiento'=> 'string',
        ]);
        if ($errors) {
            jsonError('Datos inválidos.', 422, $errors);
        }

        $model = new Vale();
        $codigo = 'V-' . strtoupper(substr(uuid4(), 0, 8));

        $id = $model->create([
            'empresa_id'          => $request->empresaId(),
            'cliente_id'          => (int) $request->input('cliente_id'),
            'codigo'              => $codigo,
            'monto_autorizado'    => (float) $request->input('monto_autorizado'),
            'tipo_combustible_id' => $request->input('tipo_combustible_id') !== null
                ? (int) $request->input('tipo_combustible_id') : null,
            'galones_autorizados' => $request->input('galones_autorizados') !== null
                ? (float) $request->input('galones_autorizados') : null,
            'placa_vehiculo'      => sanitize($request->input('placa_vehiculo', '')),
            'piloto'              => sanitize($request->input('piloto', '')),
            'fecha_emision'       => date('Y-m-d'),
            'fecha_vencimiento'   => sanitize($request->input('fecha_vencimiento')),
            'estado'              => 'activo',
            'sucursal_valida'     => $request->input('sucursal_valida') !== null
                ? (int) $request->input('sucursal_valida') : null,
        ]);

        jsonResponse([
            'data'    => $model->find($id),
            'message' => 'Vale creado correctamente.',
        ], 201);
    }

    /**
     * POST /vales/{id}/anular
     */
    public function anular(Request $request): void
    {
        $id = (int) $request->param('id');
        $model = new Vale();
        $vale = $model->find($id);

        if (!$vale || (int) $vale['empresa_id'] !== $request->empresaId()) {
            jsonError('Vale no encontrado.', 404);
        }

        $model->update($id, ['estado' => 'anulado']);

        jsonResponse(['message' => 'Vale anulado correctamente.']);
    }

    // --- Clientes ---

    /**
     * GET /clientes
     */
    public function clientes(Request $request): void
    {
        $model = new Cliente();
        $clientes = $model->listByEmpresa($request->empresaId());

        jsonResponse(['data' => $clientes]);
    }

    /**
     * GET /clientes/{id}/estado-cuenta
     */
    public function estadoCuenta(Request $request): void
    {
        $id = (int) $request->param('id');
        $model = new Cliente();
        $data = $model->estadoCuenta($id);

        if (empty($data)) {
            jsonError('Cliente no encontrado.', 404);
        }

        // Verificar empresa
        if ((int) $data['cliente']['empresa_id'] !== $request->empresaId()) {
            jsonError('No tiene acceso a este cliente.', 403);
        }

        jsonResponse(['data' => $data]);
    }
}
