<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;

/**
 * Modelo de Ventas (Tickets POS).
 */
final class Venta extends BaseModel
{
    protected string $table = 'ventas';

    /**
     * Crea una venta completa con sus detalles (combustible y/o productos)
     * dentro de una transacción.
     *
     * @param array $ventaData       Datos del header de venta
     * @param array $combustibles    Array de líneas de combustible
     * @param array $productos       Array de líneas de productos de pista
     * @return int ID de la venta creada
     */
    public function crearVentaCompleta(
        array $ventaData,
        array $combustibles = [],
        array $productos = []
    ): int {
        $db = Database::getInstance();

        return $db->transaction(function (\PDO $pdo) use ($ventaData, $combustibles, $productos) {
            // 1. Insertar header de venta
            $ventaId = $this->create($ventaData);

            // 2. Insertar líneas de combustible y descontar del tanque
            $tanqueModel = new Tanque();
            foreach ($combustibles as $linea) {
                $linea['venta_id'] = $ventaId;
                $linea['sucursal_id'] = $ventaData['sucursal_id'];

                // Verificar stock antes de descontar
                if (!$tanqueModel->hasStock($linea['tanque_id'], (float) $linea['galones'])) {
                    throw new \RuntimeException(
                        "Stock insuficiente en tanque ID {$linea['tanque_id']}."
                    );
                }

                // Insertar detalle
                $stmt = $pdo->prepare(
                    'INSERT INTO venta_combustible
                     (venta_id, sucursal_id, manguera_id, tipo_combustible_id, tanque_id,
                      galones, precio_unitario, idp_unitario, subtotal, idp_total,
                      lectura_inicial, lectura_final)
                     VALUES (:venta_id, :sucursal_id, :manguera_id, :tipo_combustible_id, :tanque_id,
                             :galones, :precio_unitario, :idp_unitario, :subtotal, :idp_total,
                             :lectura_inicial, :lectura_final)'
                );
                $stmt->execute([
                    ':venta_id'            => $ventaId,
                    ':sucursal_id'         => $linea['sucursal_id'],
                    ':manguera_id'         => $linea['manguera_id'],
                    ':tipo_combustible_id' => $linea['tipo_combustible_id'],
                    ':tanque_id'           => $linea['tanque_id'],
                    ':galones'             => $linea['galones'],
                    ':precio_unitario'     => $linea['precio_unitario'],
                    ':idp_unitario'        => $linea['idp_unitario'] ?? 0,
                    ':subtotal'            => $linea['subtotal'],
                    ':idp_total'           => $linea['idp_total'] ?? 0,
                    ':lectura_inicial'     => $linea['lectura_inicial'] ?? null,
                    ':lectura_final'       => $linea['lectura_final'] ?? null,
                ]);

                // Descontar del tanque
                $tanqueModel->updateStock($linea['tanque_id'], (float) $linea['galones'], 'restar');

                // Registrar movimiento de inventario
                $stockAntes = (float) ($pdo->query(
                    "SELECT stock_actual FROM tanques WHERE id = {$linea['tanque_id']}"
                )->fetchColumn());

                $stmt2 = $pdo->prepare(
                    'INSERT INTO movimientos_combustible
                     (sucursal_id, tanque_id, tipo_combustible_id, tipo_movimiento,
                      galones, stock_antes, stock_despues, referencia_id, usuario_id, fecha)
                     VALUES (:sucursal_id, :tanque_id, :tipo_combustible_id, :tipo_mov,
                             :galones, :stock_antes, :stock_despues, :ref_id, :usuario_id, NOW())'
                );
                $stmt2->execute([
                    ':sucursal_id'         => $linea['sucursal_id'],
                    ':tanque_id'           => $linea['tanque_id'],
                    ':tipo_combustible_id' => $linea['tipo_combustible_id'],
                    ':tipo_mov'            => 'venta',
                    ':galones'             => $linea['galones'],
                    ':stock_antes'         => $stockAntes + (float) $linea['galones'],
                    ':stock_despues'       => $stockAntes,
                    ':ref_id'              => $ventaId,
                    ':usuario_id'          => $ventaData['usuario_id'],
                ]);
            }

            // 3. Insertar líneas de productos
            foreach ($productos as $linea) {
                $stmt = $pdo->prepare(
                    'INSERT INTO venta_productos
                     (venta_id, sucursal_id, producto_id, cantidad, precio_unitario, subtotal)
                     VALUES (:venta_id, :sucursal_id, :producto_id, :cantidad, :precio_unitario, :subtotal)'
                );
                $stmt->execute([
                    ':venta_id'        => $ventaId,
                    ':sucursal_id'     => $ventaData['sucursal_id'],
                    ':producto_id'     => $linea['producto_id'],
                    ':cantidad'        => $linea['cantidad'],
                    ':precio_unitario' => $linea['precio_unitario'],
                    ':subtotal'        => $linea['subtotal'],
                ]);

                // Descontar inventario producto
                $stmt3 = $pdo->prepare(
                    'UPDATE inventario_productos
                     SET stock = stock - :cantidad, updated_at = NOW()
                     WHERE sucursal_id = :sucursal_id AND producto_id = :producto_id'
                );
                $stmt3->execute([
                    ':cantidad'    => $linea['cantidad'],
                    ':sucursal_id' => $ventaData['sucursal_id'],
                    ':producto_id' => $linea['producto_id'],
                ]);
            }

            return $ventaId;
        });
    }

    /**
     * Lista ventas de una sucursal con paginación.
     */
    public function listBySucursal(
        int $sucursalId,
        string $fechaDesde,
        string $fechaHasta,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->query(
            'SELECT v.*, u.nombre_completo AS usuario_nombre
             FROM ventas v
             JOIN usuarios u ON u.id = v.usuario_id
             WHERE v.sucursal_id = :sucursal_id
               AND v.fecha BETWEEN :desde AND :hasta
               AND v.estado = "completada"
             ORDER BY v.fecha DESC
             LIMIT :lim OFFSET :off',
            [
                ':sucursal_id' => $sucursalId,
                ':desde'       => $fechaDesde,
                ':hasta'       => $fechaHasta . ' 23:59:59',
                ':lim'         => $limit,
                ':off'         => $offset,
            ]
        );
    }

    /**
     * Totales de ventas por sucursal (para dashboard).
     */
    public function totalsBySucursal(int $sucursalId, string $fecha): array
    {
        $rows = $this->query(
            'SELECT
                COUNT(*) AS total_tickets,
                COALESCE(SUM(total), 0) AS total_vendido,
                COALESCE(SUM(monto_efectivo), 0) AS total_efectivo,
                COALESCE(SUM(monto_tarjeta), 0) AS total_tarjeta,
                COALESCE(SUM(monto_vale), 0) AS total_vales,
                COALESCE(SUM(idp_total), 0) AS total_idp,
                COALESCE(SUM(iva_total), 0) AS total_iva
             FROM ventas
             WHERE sucursal_id = :sucursal_id
               AND DATE(fecha) = :fecha
               AND estado = "completada"',
            [':sucursal_id' => $sucursalId, ':fecha' => $fecha]
        );
        return $rows[0] ?? [];
    }

    /**
     * Totales consolidados de todas las sucursales de una empresa.
     */
    public function totalsConsolidados(int $empresaId, string $fecha): array
    {
        $rows = $this->query(
            'SELECT
                s.id AS sucursal_id,
                s.nombre AS sucursal_nombre,
                COUNT(v.id) AS total_tickets,
                COALESCE(SUM(v.total), 0) AS total_vendido,
                COALESCE(SUM(v.monto_efectivo), 0) AS total_efectivo,
                COALESCE(SUM(v.monto_tarjeta), 0) AS total_tarjeta,
                COALESCE(SUM(v.monto_vale), 0) AS total_vales
             FROM sucursales s
             LEFT JOIN ventas v ON v.sucursal_id = s.id
                AND DATE(v.fecha) = :fecha
                AND v.estado = "completada"
             WHERE s.empresa_id = :empresa_id AND s.activo = 1
             GROUP BY s.id, s.nombre
             ORDER BY s.nombre',
            [':empresa_id' => $empresaId, ':fecha' => $fecha]
        );
        return $rows;
    }

    /**
     * Genera el siguiente número de ticket para una sucursal.
     */
    public function nextTicketNumber(int $sucursalId): string
    {
        $today = date('Ymd');
        $count = $this->scalar(
            'SELECT COUNT(*) FROM ventas WHERE sucursal_id = :sid AND DATE(fecha) = CURDATE()',
            [':sid' => $sucursalId]
        );
        $seq = ((int) $count) + 1;
        return sprintf('T%d-%s-%04d', $sucursalId, $today, $seq);
    }
}
