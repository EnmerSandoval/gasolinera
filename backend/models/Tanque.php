<?php

declare(strict_types=1);

namespace App\models;

/**
 * Modelo de Tanques de almacenamiento.
 */
final class Tanque extends BaseModel
{
    protected string $table = 'tanques';

    /**
     * Lista tanques de una sucursal con tipo de combustible.
     */
    public function listBySucursal(int $sucursalId): array
    {
        return $this->query(
            'SELECT t.*, tc.nombre AS combustible_nombre, tc.codigo AS combustible_codigo
             FROM tanques t
             JOIN tipos_combustible tc ON tc.id = t.tipo_combustible_id
             WHERE t.sucursal_id = :sucursal_id AND t.activo = 1
             ORDER BY t.codigo',
            [':sucursal_id' => $sucursalId]
        );
    }

    /**
     * Actualiza el stock del tanque (después de venta o compra).
     */
    public function updateStock(int $tanqueId, float $galones, string $operacion = 'restar'): bool
    {
        $operator = $operacion === 'sumar' ? '+' : '-';
        $stmt = $this->pdo->prepare(
            "UPDATE tanques SET stock_actual = stock_actual {$operator} :galones, updated_at = NOW()
             WHERE id = :id AND activo = 1"
        );
        return $stmt->execute([':galones' => $galones, ':id' => $tanqueId]);
    }

    /**
     * Verifica que el tanque tenga suficiente stock.
     */
    public function hasStock(int $tanqueId, float $galones): bool
    {
        $stock = $this->scalar(
            'SELECT stock_actual FROM tanques WHERE id = :id',
            [':id' => $tanqueId]
        );
        return $stock !== false && (float) $stock >= $galones;
    }
}
