import React, { useEffect, useState } from 'react';
import { useApi, formatGTQ } from '../hooks/useApi';
import { useSucursal } from '../contexts/SucursalContext';

export default function DashboardPage() {
  const { get, loading, error } = useApi();
  const { sucursalActiva } = useSucursal();
  const [dashboard, setDashboard] = useState(null);
  const [fecha, setFecha] = useState(new Date().toISOString().slice(0, 10));

  useEffect(() => {
    get('/dashboard', { fecha }).then((data) => setDashboard(data.data)).catch(() => {});
  }, [get, fecha, sucursalActiva]);

  if (loading && !dashboard) {
    return <div>Cargando dashboard...</div>;
  }

  if (error) {
    return <div className="alert alert-danger">{error}</div>;
  }

  if (!dashboard) return null;

  const isConsolidado = dashboard.tipo === 'consolidado';

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' }}>
        <h2>{isConsolidado ? 'Dashboard Consolidado' : `Dashboard - ${sucursalActiva?.nombre}`}</h2>
        <input
          type="date"
          className="form-input"
          style={{ width: 'auto' }}
          value={fecha}
          onChange={(e) => setFecha(e.target.value)}
        />
      </div>

      {isConsolidado ? (
        <ConsolidadoView data={dashboard} />
      ) : (
        <SucursalView data={dashboard} />
      )}
    </div>
  );
}

function ConsolidadoView({ data }) {
  return (
    <>
      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-label">Venta Total del Dia</div>
          <div className="stat-value success">{formatGTQ(data.total_vendido)}</div>
        </div>
        <div className="stat-card">
          <div className="stat-label">Total Tickets</div>
          <div className="stat-value">{data.total_tickets}</div>
        </div>
        <div className="stat-card">
          <div className="stat-label">Sucursales Activas</div>
          <div className="stat-value">{data.por_sucursal?.length || 0}</div>
        </div>
      </div>

      {/* Ventas por sucursal */}
      <div className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-header">
          <h3 className="card-title">Ventas por Sucursal</h3>
        </div>
        <div className="table-container">
          <table>
            <thead>
              <tr>
                <th>Sucursal</th>
                <th>Tickets</th>
                <th>Efectivo</th>
                <th>Tarjeta</th>
                <th>Vales</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              {(data.por_sucursal || []).map((s) => (
                <tr key={s.sucursal_id}>
                  <td><strong>{s.sucursal_nombre}</strong></td>
                  <td>{s.total_tickets}</td>
                  <td>{formatGTQ(s.total_efectivo)}</td>
                  <td>{formatGTQ(s.total_tarjeta)}</td>
                  <td>{formatGTQ(s.total_vales)}</td>
                  <td><strong>{formatGTQ(s.total_vendido)}</strong></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Alertas de stock */}
      {data.alertas_stock?.length > 0 && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Alertas de Stock Bajo</h3>
          </div>
          {data.alertas_stock.map((a, i) => (
            <div key={i} className="alert alert-warning">
              <strong>{a.sucursal}</strong> - Tanque {a.tanque}: {a.combustible} -
              Stock: {Number(a.stock_actual).toFixed(0)} gal (Min: {Number(a.nivel_minimo).toFixed(0)} gal)
            </div>
          ))}
        </div>
      )}
    </>
  );
}

function SucursalView({ data }) {
  const ventas = data.ventas || {};

  return (
    <>
      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-label">Venta del Dia</div>
          <div className="stat-value success">{formatGTQ(ventas.total_vendido)}</div>
        </div>
        <div className="stat-card">
          <div className="stat-label">Tickets</div>
          <div className="stat-value">{ventas.total_tickets || 0}</div>
        </div>
        <div className="stat-card">
          <div className="stat-label">Efectivo</div>
          <div className="stat-value">{formatGTQ(ventas.total_efectivo)}</div>
        </div>
        <div className="stat-card">
          <div className="stat-label">IDP Cobrado</div>
          <div className="stat-value warning">{formatGTQ(ventas.total_idp)}</div>
        </div>
      </div>

      {/* Tanques */}
      <div className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-header">
          <h3 className="card-title">Niveles de Tanques</h3>
        </div>
        <div style={{ display: 'flex', gap: '2rem', flexWrap: 'wrap', padding: '1rem 0' }}>
          {(data.tanques || []).map((t, i) => (
            <div key={i} style={{ textAlign: 'center' }}>
              <div className="tank-gauge">
                <div
                  className="tank-level"
                  style={{
                    height: `${Math.min(t.porcentaje, 100)}%`,
                    background: t.porcentaje < 25 ? '#ef4444' : t.porcentaje < 50 ? '#f59e0b' : '#22c55e',
                  }}
                />
              </div>
              <div style={{ marginTop: '0.5rem', fontWeight: 600 }}>{t.codigo}</div>
              <div style={{ fontSize: '0.8rem', color: 'var(--color-text-muted)' }}>
                {t.combustible}
              </div>
              <div style={{ fontSize: '0.85rem' }}>
                {Number(t.stock_actual).toFixed(0)} / {Number(t.capacidad_galones).toFixed(0)} gal
              </div>
              <div style={{ fontSize: '0.8rem', fontWeight: 600 }}>
                {Number(t.porcentaje).toFixed(1)}%
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Alertas */}
      {data.alertas_stock?.length > 0 && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Alertas</h3>
          </div>
          {data.alertas_stock.map((a, i) => (
            <div key={i} className="alert alert-danger">
              Tanque {a.codigo}: {a.combustible} - Stock bajo ({Number(a.stock_actual).toFixed(0)} gal).
              Reabastecer pronto.
            </div>
          ))}
        </div>
      )}
    </>
  );
}
