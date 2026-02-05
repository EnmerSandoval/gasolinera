<?php

declare(strict_types=1);

namespace App\models;

/**
 * Modelo de Clientes Corporativos.
 */
final class Cliente extends BaseModel
{
    protected string $table = 'clientes';

    /**
     * Lista clientes activos de una empresa.
     */
    public function listByEmpresa(int $empresaId): array
    {
        return $this->findWhere(
            ['empresa_id' => $empresaId, 'activo' => 1],
            'razon_social ASC'
        );
    }

    /**
     * Estado de cuenta de un cliente.
     */
    public function estadoCuenta(int $clienteId): array
    {
        $cliente = $this->find($clienteId);
        if (!$cliente) {
            return [];
        }

        // Ventas a crédito pendientes
        $ventas = $this->query(
            'SELECT v.numero_ticket, v.fecha, v.total, s.nombre AS sucursal
             FROM ventas v
             JOIN sucursales s ON s.id = v.sucursal_id
             WHERE v.cliente_id = :cid AND v.estado = "completada"
             ORDER BY v.fecha DESC
             LIMIT 50',
            [':cid' => $clienteId]
        );

        // Pagos recibidos
        $pagos = $this->query(
            'SELECT fecha, monto, forma_pago, referencia
             FROM pagos_clientes
             WHERE cliente_id = :cid
             ORDER BY fecha DESC
             LIMIT 50',
            [':cid' => $clienteId]
        );

        return [
            'cliente'           => $cliente,
            'credito_disponible' => (float) $cliente['limite_credito'] - (float) $cliente['saldo_actual'],
            'ventas_recientes'  => $ventas,
            'pagos_recientes'   => $pagos,
        ];
    }
}
