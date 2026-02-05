<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Request;
use App\models\Sucursal;

/**
 * Controlador de Sucursales.
 */
final class SucursalController
{
    /**
     * GET /sucursales
     */
    public function index(Request $request): void
    {
        $model = new Sucursal();
        $sucursales = $model->listByEmpresa($request->empresaId());

        jsonResponse(['data' => $sucursales]);
    }

    /**
     * GET /sucursales/{id}
     */
    public function show(Request $request): void
    {
        $id = (int) $request->param('id');
        $model = new Sucursal();

        $sucursal = $model->findForEmpresa($id, $request->empresaId());
        if (!$sucursal) {
            jsonError('Sucursal no encontrada.', 404);
        }

        $summary = $model->getSummary($id);

        jsonResponse([
            'data'    => $sucursal,
            'summary' => $summary,
        ]);
    }

    /**
     * POST /sucursales
     */
    public function store(Request $request): void
    {
        $errors = $request->validate([
            'codigo'    => 'string',
            'nombre'    => 'string',
            'direccion' => 'string',
        ]);
        if ($errors) {
            jsonError('Datos inválidos.', 422, $errors);
        }

        $model = new Sucursal();
        $id = $model->create([
            'empresa_id'   => $request->empresaId(),
            'codigo'       => sanitize($request->input('codigo')),
            'nombre'       => sanitize($request->input('nombre')),
            'direccion'    => sanitize($request->input('direccion')),
            'municipio'    => sanitize($request->input('municipio', '')),
            'departamento' => sanitize($request->input('departamento', '')),
            'telefono'     => sanitize($request->input('telefono', '')),
            'responsable'  => sanitize($request->input('responsable', '')),
        ]);

        $sucursal = $model->find($id);
        jsonResponse(['data' => $sucursal, 'message' => 'Sucursal creada.'], 201);
    }

    /**
     * PUT /sucursales/{id}
     */
    public function update(Request $request): void
    {
        $id = (int) $request->param('id');
        $model = new Sucursal();

        $existing = $model->findForEmpresa($id, $request->empresaId());
        if (!$existing) {
            jsonError('Sucursal no encontrada.', 404);
        }

        $data = array_filter([
            'nombre'       => sanitize($request->input('nombre', '')),
            'direccion'    => sanitize($request->input('direccion', '')),
            'municipio'    => sanitize($request->input('municipio', '')),
            'departamento' => sanitize($request->input('departamento', '')),
            'telefono'     => sanitize($request->input('telefono', '')),
            'responsable'  => sanitize($request->input('responsable', '')),
        ], fn ($v) => $v !== '');

        if ($data) {
            $model->update($id, $data);
        }

        jsonResponse(['data' => $model->find($id), 'message' => 'Sucursal actualizada.']);
    }
}
