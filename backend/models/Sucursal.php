<?php

declare(strict_types=1);

namespace App\models;

/**
 * Modelo de Sucursales (Gasolineras).
 */
final class Sucursal extends BaseModel
{
    protected string $table = 'sucursales';

    /**
     * Lista todas las sucursales activas de una empresa.
     */
    public function listByEmpresa(int $empresaId): array
    {
        return $this->findWhere(
            ['empresa_id' => $empresaId, 'activo' => 1],
            'nombre ASC'
        );
    }

    /**
     * Obtiene una sucursal verificando que pertenezca a la empresa.
     */
    public function findForEmpresa(int $id, int $empresaId): ?array
    {
        $rows = $this->query(
            'SELECT * FROM sucursales WHERE id = :id AND empresa_id = :empresa_id LIMIT 1',
            [':id' => $id, ':empresa_id' => $empresaId]
        );
        return $rows[0] ?? null;
    }

    /**
     * Resumen rápido: total de bombas, tanques e islas por sucursal.
     */
    public function getSummary(int $sucursalId): array
    {
        return $this->query(
            'SELECT
                (SELECT COUNT(*) FROM islas WHERE sucursal_id = :s1 AND activo = 1) AS total_islas,
                (SELECT COUNT(*) FROM bombas WHERE sucursal_id = :s2 AND activo = 1) AS total_bombas,
                (SELECT COUNT(*) FROM tanques WHERE sucursal_id = :s3 AND activo = 1) AS total_tanques',
            [':s1' => $sucursalId, ':s2' => $sucursalId, ':s3' => $sucursalId]
        )[0] ?? ['total_islas' => 0, 'total_bombas' => 0, 'total_tanques' => 0];
    }
}
