<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;

/**
 * Modelo de Vales de Crédito Corporativo.
 * Los vales son centralizados: un cliente puede usarlos en cualquier sucursal.
 */
final class Vale extends BaseModel
{
    protected string $table = 'vales';

    /**
     * Valida un vale en tiempo real antes de autorizar despacho.
     *
     * @param string   $codigo      Código del vale
     * @param int      $empresaId   Empresa a la que pertenece
     * @param int|null $sucursalId  Sucursal donde se intenta usar
     * @param float    $monto       Monto a consumir
     * @return array{valid: bool, vale?: array, error?: string}
     */
    public function validarVale(string $codigo, int $empresaId, ?int $sucursalId, float $monto): array
    {
        $rows = $this->query(
            'SELECT v.*, c.razon_social AS cliente_nombre, c.limite_credito, c.saldo_actual
             FROM vales v
             JOIN clientes c ON c.id = v.cliente_id
             WHERE v.codigo = :codigo
               AND v.empresa_id = :empresa_id
             LIMIT 1',
            [':codigo' => $codigo, ':empresa_id' => $empresaId]
        );

        if (empty($rows)) {
            return ['valid' => false, 'error' => 'Vale no encontrado.'];
        }

        $vale = $rows[0];

        // Estado
        if ($vale['estado'] !== 'activo') {
            return ['valid' => false, 'error' => "Vale {$vale['estado']}.", 'vale' => $vale];
        }

        // Vencimiento
        if (strtotime($vale['fecha_vencimiento']) < strtotime('today')) {
            return ['valid' => false, 'error' => 'Vale vencido.', 'vale' => $vale];
        }

        // Sucursal válida
        if ($vale['sucursal_valida'] !== null && $sucursalId !== null
            && (int) $vale['sucursal_valida'] !== $sucursalId) {
            return ['valid' => false, 'error' => 'Vale no válido para esta sucursal.', 'vale' => $vale];
        }

        // Saldo del vale
        $saldoDisponible = (float) $vale['monto_autorizado'] - (float) $vale['monto_consumido'];
        if ($monto > $saldoDisponible) {
            return [
                'valid' => false,
                'error' => "Saldo insuficiente en vale. Disponible: Q{$saldoDisponible}",
                'vale'  => $vale,
            ];
        }

        // Límite de crédito del cliente
        $creditoDisponible = (float) $vale['limite_credito'] - (float) $vale['saldo_actual'];
        if ($monto > $creditoDisponible) {
            return [
                'valid' => false,
                'error' => "Límite de crédito excedido. Disponible: Q{$creditoDisponible}",
                'vale'  => $vale,
            ];
        }

        return ['valid' => true, 'vale' => $vale];
    }

    /**
     * Consume saldo de un vale y actualiza saldo del cliente.
     * Ejecutado dentro de la transacción de venta.
     */
    public function consumir(int $valeId, int $clienteId, float $monto, float $galones = 0): void
    {
        // Actualizar vale
        $stmt = $this->pdo->prepare(
            'UPDATE vales
             SET monto_consumido = monto_consumido + :monto,
                 galones_consumidos = galones_consumidos + :galones,
                 estado = CASE
                     WHEN (monto_consumido + :monto2) >= monto_autorizado THEN "agotado"
                     ELSE estado
                 END
             WHERE id = :id'
        );
        $stmt->execute([
            ':monto'   => $monto,
            ':galones' => $galones,
            ':monto2'  => $monto,
            ':id'      => $valeId,
        ]);

        // Actualizar saldo del cliente
        $stmt2 = $this->pdo->prepare(
            'UPDATE clientes SET saldo_actual = saldo_actual + :monto WHERE id = :id'
        );
        $stmt2->execute([':monto' => $monto, ':id' => $clienteId]);
    }

    /**
     * Lista vales activos de un cliente.
     */
    public function listByCliente(int $clienteId): array
    {
        return $this->query(
            'SELECT v.*, tc.nombre AS combustible_nombre
             FROM vales v
             LEFT JOIN tipos_combustible tc ON tc.id = v.tipo_combustible_id
             WHERE v.cliente_id = :cliente_id AND v.estado = "activo"
             ORDER BY v.fecha_vencimiento ASC',
            [':cliente_id' => $clienteId]
        );
    }

    /**
     * Lista todos los vales de una empresa con filtros.
     */
    public function listByEmpresa(int $empresaId, ?string $estado = null): array
    {
        $sql = 'SELECT v.*, c.razon_social AS cliente_nombre
                FROM vales v
                JOIN clientes c ON c.id = v.cliente_id
                WHERE v.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresaId];

        if ($estado !== null) {
            $sql .= ' AND v.estado = :estado';
            $params[':estado'] = $estado;
        }

        $sql .= ' ORDER BY v.created_at DESC';

        return $this->query($sql, $params);
    }
}
