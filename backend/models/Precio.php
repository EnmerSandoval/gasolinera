<?php

declare(strict_types=1);

namespace App\models;

/**
 * Modelo de Precios de Combustible.
 */
final class Precio extends BaseModel
{
    protected string $table = 'precios_combustible';

    /**
     * Obtiene el precio vigente de un tipo de combustible en una sucursal.
     */
    public function precioVigente(int $sucursalId, int $tipoCombustibleId): ?array
    {
        $rows = $this->query(
            'SELECT * FROM precios_combustible
             WHERE sucursal_id = :sid
               AND tipo_combustible_id = :tid
               AND activo = 1
             ORDER BY vigente_desde DESC
             LIMIT 1',
            [':sid' => $sucursalId, ':tid' => $tipoCombustibleId]
        );
        return $rows[0] ?? null;
    }

    /**
     * Lista todos los precios vigentes de una sucursal.
     */
    public function preciosVigentesBySucursal(int $sucursalId): array
    {
        return $this->query(
            'SELECT pc.*, tc.nombre AS combustible_nombre, tc.codigo AS combustible_codigo
             FROM precios_combustible pc
             JOIN tipos_combustible tc ON tc.id = pc.tipo_combustible_id
             WHERE pc.sucursal_id = :sid AND pc.activo = 1
             ORDER BY tc.nombre',
            [':sid' => $sucursalId]
        );
    }
}
