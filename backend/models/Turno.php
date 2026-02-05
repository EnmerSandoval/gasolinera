<?php

declare(strict_types=1);

namespace App\models;

/**
 * Modelo de Turnos de trabajo.
 */
final class Turno extends BaseModel
{
    protected string $table = 'turnos';

    /**
     * Busca turno abierto para un usuario en una sucursal.
     */
    public function turnoAbierto(int $usuarioId, int $sucursalId): ?array
    {
        $rows = $this->query(
            'SELECT * FROM turnos
             WHERE usuario_id = :uid AND sucursal_id = :sid AND estado = "abierto"
             ORDER BY hora_inicio DESC LIMIT 1',
            [':uid' => $usuarioId, ':sid' => $sucursalId]
        );
        return $rows[0] ?? null;
    }

    /**
     * Abre un nuevo turno.
     */
    public function abrir(int $sucursalId, int $usuarioId, float $efectivoInicial): int
    {
        // Verificar que no tenga otro turno abierto
        $existente = $this->turnoAbierto($usuarioId, $sucursalId);
        if ($existente) {
            throw new \RuntimeException('Ya tiene un turno abierto en esta sucursal.');
        }

        return $this->create([
            'sucursal_id'      => $sucursalId,
            'usuario_id'       => $usuarioId,
            'fecha'            => date('Y-m-d'),
            'hora_inicio'      => date('Y-m-d H:i:s'),
            'estado'           => 'abierto',
            'efectivo_inicial' => $efectivoInicial,
        ]);
    }

    /**
     * Cierra un turno.
     */
    public function cerrar(int $turnoId, float $efectivoFinal, ?string $notas = null): bool
    {
        return $this->update($turnoId, [
            'hora_fin'        => date('Y-m-d H:i:s'),
            'estado'          => 'cerrado',
            'efectivo_final'  => $efectivoFinal,
            'notas'           => $notas,
        ]);
    }
}
