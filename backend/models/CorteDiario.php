<?php

declare(strict_types=1);

namespace App\models;

/**
 * Modelo de Cortes Diarios - Control Volumétrico y Mermas.
 *
 * Fórmula: Stock Inicial + Compras - Ventas = Stock Teórico
 *          Stock Físico - Stock Teórico = Variación (negativo = merma)
 */
final class CorteDiario extends BaseModel
{
    protected string $table = 'cortes_diarios';

    /**
     * Calcula el corte para un tanque específico en una fecha.
     *
     * @param int    $sucursalId
     * @param int    $tanqueId
     * @param string $fecha         Formato YYYY-MM-DD
     * @param float  $stockInicial  Lectura física al inicio
     * @param float  $stockFinal    Lectura física al final del día
     * @param int    $usuarioId
     * @return array Datos del corte calculado
     */
    public function calcularCorte(
        int $sucursalId,
        int $tanqueId,
        string $fecha,
        float $stockInicial,
        float $stockFinal,
        int $usuarioId
    ): array {
        // Obtener tipo de combustible del tanque
        $tanque = (new Tanque())->find($tanqueId);
        if (!$tanque || (int) $tanque['sucursal_id'] !== $sucursalId) {
            throw new \RuntimeException('Tanque no encontrado o no pertenece a la sucursal.');
        }

        $tipoCombustibleId = (int) $tanque['tipo_combustible_id'];

        // Sumar compras del día para este tanque
        $comprasDia = (float) $this->scalar(
            'SELECT COALESCE(SUM(cd.galones), 0)
             FROM compra_detalle cd
             JOIN compras c ON c.id = cd.compra_id
             WHERE cd.tanque_id = :tanque_id
               AND DATE(c.fecha_recepcion) = :fecha
               AND c.estado != "anulada"',
            [':tanque_id' => $tanqueId, ':fecha' => $fecha]
        );

        // Sumar ventas del día para este tanque
        $ventasDia = (float) $this->scalar(
            'SELECT COALESCE(SUM(vc.galones), 0)
             FROM venta_combustible vc
             JOIN ventas v ON v.id = vc.venta_id
             WHERE vc.tanque_id = :tanque_id
               AND DATE(v.fecha) = :fecha
               AND v.estado = "completada"',
            [':tanque_id' => $tanqueId, ':fecha' => $fecha]
        );

        // Cálculo volumétrico
        $stockTeoricoFinal = $stockInicial + $comprasDia - $ventasDia;
        $variacion = $stockFinal - $stockTeoricoFinal;
        $porcentajeVariacion = $stockTeoricoFinal > 0
            ? ($variacion / $stockTeoricoFinal) * 100
            : 0;

        $data = [
            'sucursal_id'          => $sucursalId,
            'tanque_id'            => $tanqueId,
            'tipo_combustible_id'  => $tipoCombustibleId,
            'fecha'                => $fecha,
            'stock_inicial'        => $stockInicial,
            'compras_dia'          => $comprasDia,
            'ventas_dia'           => $ventasDia,
            'stock_final_teorico'  => round($stockTeoricoFinal, 4),
            'stock_final_fisico'   => $stockFinal,
            'variacion'            => round($variacion, 4),
            'porcentaje_variacion' => round($porcentajeVariacion, 4),
            'usuario_id'           => $usuarioId,
        ];

        return $data;
    }

    /**
     * Guarda o actualiza un corte diario.
     */
    public function guardarCorte(array $data): int
    {
        // Verificar si ya existe corte para ese tanque y fecha
        $existing = $this->query(
            'SELECT id FROM cortes_diarios
             WHERE sucursal_id = :sid AND tanque_id = :tid AND fecha = :fecha LIMIT 1',
            [':sid' => $data['sucursal_id'], ':tid' => $data['tanque_id'], ':fecha' => $data['fecha']]
        );

        if (!empty($existing)) {
            $this->update((int) $existing[0]['id'], $data);
            return (int) $existing[0]['id'];
        }

        return $this->create($data);
    }

    /**
     * Reporte de mermas por sucursal en un rango de fechas.
     */
    public function reporteMermas(int $sucursalId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->query(
            'SELECT cd.*, tc.nombre AS combustible_nombre, t.codigo AS tanque_codigo
             FROM cortes_diarios cd
             JOIN tipos_combustible tc ON tc.id = cd.tipo_combustible_id
             JOIN tanques t ON t.id = cd.tanque_id
             WHERE cd.sucursal_id = :sucursal_id
               AND cd.fecha BETWEEN :desde AND :hasta
             ORDER BY cd.fecha DESC, t.codigo',
            [':sucursal_id' => $sucursalId, ':desde' => $fechaDesde, ':hasta' => $fechaHasta]
        );
    }

    /**
     * Reporte consolidado de mermas de todas las sucursales.
     */
    public function reporteMermasConsolidado(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        return $this->query(
            'SELECT s.nombre AS sucursal_nombre, tc.nombre AS combustible_nombre,
                    SUM(cd.ventas_dia) AS total_ventas_galones,
                    SUM(cd.compras_dia) AS total_compras_galones,
                    SUM(cd.variacion) AS total_variacion,
                    AVG(cd.porcentaje_variacion) AS promedio_variacion_pct
             FROM cortes_diarios cd
             JOIN sucursales s ON s.id = cd.sucursal_id
             JOIN tipos_combustible tc ON tc.id = cd.tipo_combustible_id
             WHERE s.empresa_id = :empresa_id
               AND cd.fecha BETWEEN :desde AND :hasta
             GROUP BY s.id, s.nombre, tc.id, tc.nombre
             ORDER BY s.nombre, tc.nombre',
            [':empresa_id' => $empresaId, ':desde' => $fechaDesde, ':hasta' => $fechaHasta]
        );
    }
}
