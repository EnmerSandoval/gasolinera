<?php

declare(strict_types=1);

namespace App\models;

use App\core\Database;

/**
 * Modelo de Compras de combustible (facturas de pipas/cisterna).
 */
final class Compra extends BaseModel
{
    protected string $table = 'compras';

    /**
     * Crea una compra completa con detalle y actualiza inventario del tanque.
     *
     * @param array $compraData  Datos del header
     * @param array $detalles    Líneas de detalle [{tanque_id, tipo_combustible_id, galones, precio_unitario, ...}]
     * @return int ID de la compra
     */
    public function crearCompraCompleta(array $compraData, array $detalles): int
    {
        $db = Database::getInstance();

        return $db->transaction(function (\PDO $pdo) use ($compraData, $detalles) {
            $compraId = $this->create($compraData);
            $tanqueModel = new Tanque();

            foreach ($detalles as $linea) {
                // Insertar detalle
                $stmt = $pdo->prepare(
                    'INSERT INTO compra_detalle
                     (compra_id, tanque_id, tipo_combustible_id, galones,
                      precio_unitario, idp_unitario, subtotal, idp_total)
                     VALUES (:compra_id, :tanque_id, :tipo_combustible_id, :galones,
                             :precio_unitario, :idp_unitario, :subtotal, :idp_total)'
                );
                $stmt->execute([
                    ':compra_id'           => $compraId,
                    ':tanque_id'           => $linea['tanque_id'],
                    ':tipo_combustible_id' => $linea['tipo_combustible_id'],
                    ':galones'             => $linea['galones'],
                    ':precio_unitario'     => $linea['precio_unitario'],
                    ':idp_unitario'        => $linea['idp_unitario'] ?? 0,
                    ':subtotal'            => $linea['subtotal'],
                    ':idp_total'           => $linea['idp_total'] ?? 0,
                ]);

                // Sumar al tanque
                $tanqueModel->updateStock($linea['tanque_id'], (float) $linea['galones'], 'sumar');

                // Movimiento de inventario
                $stockDespues = (float) $pdo->query(
                    "SELECT stock_actual FROM tanques WHERE id = {$linea['tanque_id']}"
                )->fetchColumn();

                $stmt2 = $pdo->prepare(
                    'INSERT INTO movimientos_combustible
                     (sucursal_id, tanque_id, tipo_combustible_id, tipo_movimiento,
                      galones, stock_antes, stock_despues, referencia_id, usuario_id, fecha)
                     VALUES (:sucursal_id, :tanque_id, :tipo_combustible_id, "compra",
                             :galones, :stock_antes, :stock_despues, :ref_id, :usuario_id, NOW())'
                );
                $stmt2->execute([
                    ':sucursal_id'         => $compraData['sucursal_id'],
                    ':tanque_id'           => $linea['tanque_id'],
                    ':tipo_combustible_id' => $linea['tipo_combustible_id'],
                    ':galones'             => $linea['galones'],
                    ':stock_antes'         => $stockDespues - (float) $linea['galones'],
                    ':stock_despues'       => $stockDespues,
                    ':ref_id'              => $compraId,
                    ':usuario_id'          => $compraData['usuario_id'],
                ]);
            }

            return $compraId;
        });
    }

    /**
     * Lista compras de una sucursal.
     */
    public function listBySucursal(int $sucursalId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->query(
            'SELECT c.*, p.razon_social AS proveedor_nombre,
                    u.nombre_completo AS usuario_nombre
             FROM compras c
             JOIN proveedores p ON p.id = c.proveedor_id
             JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.sucursal_id = :sucursal_id
               AND c.fecha_factura BETWEEN :desde AND :hasta
             ORDER BY c.fecha_factura DESC',
            [':sucursal_id' => $sucursalId, ':desde' => $fechaDesde, ':hasta' => $fechaHasta]
        );
    }
}
