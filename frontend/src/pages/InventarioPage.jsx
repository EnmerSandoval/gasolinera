import React, { useEffect, useState } from 'react';
import { useApi, formatGalones } from '../hooks/useApi';
import { useSucursal } from '../contexts/SucursalContext';

export default function InventarioPage() {
  const { sucursalActiva, sucursalId } = useSucursal();
  const { get, post, loading, error, clearError } = useApi();

  const [tanques, setTanques] = useState([]);
  const [mermas, setMermas] = useState([]);
  const [showCorteForm, setShowCorteForm] = useState(false);

  // Form corte diario
  const [corteTanqueId, setCorteTanqueId] = useState('');
  const [corteFecha, setCorteFecha] = useState(new Date().toISOString().slice(0, 10));
  const [corteStockInicial, setCorteStockInicial] = useState('');
  const [corteStockFinal, setCorteStockFinal] = useState('');
  const [corteResult, setCorteResult] = useState(null);
  const [mensaje, setMensaje] = useState('');

  useEffect(() => {
    if (!sucursalId) return;
    get('/inventario/tanques').then((d) => setTanques(d.data || [])).catch(() => {});
    get('/inventario/mermas').then((d) => setMermas(d.data || [])).catch(() => {});
  }, [get, sucursalId]);

  const registrarCorte = async () => {
    clearError();
    setMensaje('');
    setCorteResult(null);

    try {
      const data = await post('/inventario/corte-diario', {
        tanque_id: Number(corteTanqueId),
        fecha: corteFecha,
        stock_inicial: Number(corteStockInicial),
        stock_final_fisico: Number(corteStockFinal),
      });

      setCorteResult(data.resumen);
      setMensaje(data.message);

      // Refrescar tanques
      get('/inventario/tanques').then((d) => setTanques(d.data || [])).catch(() => {});
    } catch {}
  };

  if (!sucursalId) {
    return (
      <div className="alert alert-warning">
        Seleccione una sucursal para ver el inventario.
      </div>
    );
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '1.5rem' }}>
        <h2>Inventario - {sucursalActiva?.nombre}</h2>
        <button className="btn btn-primary" onClick={() => setShowCorteForm(!showCorteForm)}>
          {showCorteForm ? 'Cerrar' : 'Registrar Corte Diario'}
        </button>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {mensaje && <div className="alert alert-success">{mensaje}</div>}

      {/* Formulario de corte diario */}
      {showCorteForm && (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-header">
            <h3 className="card-title">Corte Diario - Control Volumetrico</h3>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
            <div className="form-group">
              <label className="form-label">Tanque</label>
              <select className="form-select" value={corteTanqueId} onChange={(e) => setCorteTanqueId(e.target.value)}>
                <option value="">Seleccionar...</option>
                {tanques.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.codigo} - {t.combustible_nombre}
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label className="form-label">Fecha</label>
              <input type="date" className="form-input" value={corteFecha} onChange={(e) => setCorteFecha(e.target.value)} />
            </div>
            <div className="form-group">
              <label className="form-label">Lectura Inicial (gal)</label>
              <input type="number" step="0.01" className="form-input" value={corteStockInicial} onChange={(e) => setCorteStockInicial(e.target.value)} />
            </div>
            <div className="form-group">
              <label className="form-label">Lectura Final Fisica (gal)</label>
              <input type="number" step="0.01" className="form-input" value={corteStockFinal} onChange={(e) => setCorteStockFinal(e.target.value)} />
            </div>
          </div>
          <button className="btn btn-success" onClick={registrarCorte} disabled={loading} style={{ marginTop: '0.5rem' }}>
            Calcular y Guardar
          </button>

          {/* Resultado del corte */}
          {corteResult && (
            <div style={{ marginTop: '1rem', padding: '1rem', background: 'var(--color-bg)', borderRadius: 'var(--radius)' }}>
              <h4 style={{ marginBottom: '0.5rem' }}>Resultado del Corte</h4>
              <p>Stock Inicial: <strong>{formatGalones(corteResult.stock_inicial)}</strong></p>
              <p>+ Compras del Dia: <strong>{formatGalones(corteResult.compras_dia)}</strong></p>
              <p>- Ventas del Dia: <strong>{formatGalones(corteResult.ventas_dia)}</strong></p>
              <p>= Stock Teorico: <strong>{formatGalones(corteResult.stock_final_teorico)}</strong></p>
              <p>Lectura Fisica: <strong>{formatGalones(corteResult.stock_final_fisico)}</strong></p>
              <hr style={{ margin: '0.5rem 0' }} />
              <p style={{ fontSize: '1.1rem' }}>
                Variacion: <strong className={`stat-value ${corteResult.tipo === 'MERMA' ? 'danger' : 'success'}`}>
                  {formatGalones(corteResult.variacion_galones)} ({Number(corteResult.variacion_porcentaje).toFixed(2)}%)
                </strong>
                {' '}<span className={`badge ${corteResult.tipo === 'MERMA' ? 'badge-danger' : 'badge-success'}`}>
                  {corteResult.tipo}
                </span>
              </p>
            </div>
          )}
        </div>
      )}

      {/* Tabla de tanques */}
      <div className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-header">
          <h3 className="card-title">Tanques de Almacenamiento</h3>
        </div>
        <div className="table-container">
          <table>
            <thead>
              <tr>
                <th>Codigo</th>
                <th>Combustible</th>
                <th>Capacidad</th>
                <th>Stock Actual</th>
                <th>Nivel</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              {tanques.map((t) => {
                const pct = ((t.stock_actual / t.capacidad_galones) * 100).toFixed(1);
                return (
                  <tr key={t.id}>
                    <td><strong>{t.codigo}</strong></td>
                    <td>{t.combustible_nombre}</td>
                    <td>{formatGalones(t.capacidad_galones)}</td>
                    <td>{formatGalones(t.stock_actual)}</td>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                        <div style={{ width: 100, height: 8, background: '#e2e8f0', borderRadius: 4, overflow: 'hidden' }}>
                          <div style={{
                            width: `${Math.min(pct, 100)}%`,
                            height: '100%',
                            background: pct < 25 ? '#ef4444' : pct < 50 ? '#f59e0b' : '#22c55e',
                            borderRadius: 4,
                          }} />
                        </div>
                        <span style={{ fontSize: '0.85rem' }}>{pct}%</span>
                      </div>
                    </td>
                    <td>
                      <span className={`badge ${t.stock_actual <= t.nivel_minimo ? 'badge-danger' : 'badge-success'}`}>
                        {t.stock_actual <= t.nivel_minimo ? 'Bajo' : 'Normal'}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Historial de mermas */}
      {mermas.length > 0 && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Historial de Mermas (Mes Actual)</h3>
          </div>
          <div className="table-container">
            <table>
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Tanque</th>
                  <th>Combustible</th>
                  <th>Ventas</th>
                  <th>Variacion</th>
                  <th>%</th>
                </tr>
              </thead>
              <tbody>
                {mermas.map((m, i) => (
                  <tr key={i}>
                    <td>{m.fecha}</td>
                    <td>{m.tanque_codigo}</td>
                    <td>{m.combustible_nombre}</td>
                    <td>{formatGalones(m.ventas_dia)}</td>
                    <td>
                      <span style={{ color: m.variacion < 0 ? '#dc2626' : '#059669' }}>
                        {formatGalones(m.variacion)}
                      </span>
                    </td>
                    <td>
                      <span className={`badge ${m.variacion < 0 ? 'badge-danger' : 'badge-success'}`}>
                        {Number(m.porcentaje_variacion).toFixed(2)}%
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
