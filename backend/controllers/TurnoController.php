<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Request;
use App\models\Turno;

/**
 * Controlador de Turnos de trabajo.
 */
final class TurnoController
{
    /**
     * GET /turnos/activo
     */
    public function activo(Request $request): void
    {
        $sucursalId = $request->sucursalId();
        if ($sucursalId === null) {
            jsonError('Debe especificar una sucursal.', 400);
        }

        $model = new Turno();
        $turno = $model->turnoAbierto($request->userId(), $sucursalId);

        if (!$turno) {
            jsonResponse(['data' => null, 'message' => 'No hay turno abierto.']);
            return;
        }

        jsonResponse(['data' => $turno]);
    }

    /**
     * POST /turnos/abrir
     * Body: { "efectivo_inicial": 500.00 }
     */
    public function abrir(Request $request): void
    {
        $sucursalId = $request->sucursalId();
        if ($sucursalId === null) {
            jsonError('Debe especificar una sucursal.', 400);
        }

        $efectivoInicial = (float) $request->input('efectivo_inicial', 0);

        try {
            $model = new Turno();
            $id = $model->abrir($sucursalId, $request->userId(), $efectivoInicial);
            $turno = $model->find($id);

            jsonResponse(['data' => $turno, 'message' => 'Turno abierto.'], 201);
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage(), 422);
        }
    }

    /**
     * POST /turnos/{id}/cerrar
     * Body: { "efectivo_final": 5000.00, "notas": "" }
     */
    public function cerrar(Request $request): void
    {
        $id = (int) $request->param('id');
        $model = new Turno();
        $turno = $model->find($id);

        if (!$turno) {
            jsonError('Turno no encontrado.', 404);
        }

        if ($turno['estado'] !== 'abierto') {
            jsonError('El turno no está abierto.', 422);
        }

        // Verificar que el turno pertenece a la sucursal del usuario
        $sucursalId = $request->sucursalId();
        if ($sucursalId !== null && (int) $turno['sucursal_id'] !== $sucursalId) {
            jsonError('No tiene acceso a este turno.', 403);
        }

        $efectivoFinal = (float) $request->input('efectivo_final', 0);
        $notas = sanitize($request->input('notas', ''));

        $model->cerrar($id, $efectivoFinal, $notas);

        jsonResponse([
            'data'    => $model->find($id),
            'message' => 'Turno cerrado correctamente.',
        ]);
    }
}
