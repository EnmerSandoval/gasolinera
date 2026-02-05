import React, { useEffect, useState } from 'react';
import { useApi, formatGTQ } from '../hooks/useApi';
import { useAuth } from '../contexts/AuthContext';

export default function ValesPage() {
  const { isGerente } = useAuth();
  const { get, post, loading, error, clearError } = useApi();

  const [vales, setVales] = useState([]);
  const [clientes, setClientes] = useState([]);
  const [filtroEstado, setFiltroEstado] = useState('activo');
  const [showForm, setShowForm] = useState(false);
  const [mensaje, setMensaje] = useState('');

  // Form nuevo vale
  const [form, setForm] = useState({
    cliente_id: '',
    monto_autorizado: '',
    fecha_vencimiento: '',
    placa_vehiculo: '',
    piloto: '',
  });

  useEffect(() => {
    get('/vales', { estado: filtroEstado }).then((d) => setVales(d.data || [])).catch(() => {});
    get('/clientes').then((d) => setClientes(d.data || [])).catch(() => {});
  }, [get, filtroEstado]);

  const crearVale = async () => {
    clearError();
    setMensaje('');
    try {
      const data = await post('/vales', {
        ...form,
        cliente_id: Number(form.cliente_id),
        monto_autorizado: Number(form.monto_autorizado),
      });
      setMensaje(`Vale creado: ${data.data.codigo}`);
      setShowForm(false);
      setForm({ cliente_id: '', monto_autorizado: '', fecha_vencimiento: '', placa_vehiculo: '', piloto: '' });
      // Refrescar
      get('/vales', { estado: filtroEstado }).then((d) => setVales(d.data || [])).catch(() => {});
    } catch {}
  };

  const anularVale = async (id) => {
    if (!window.confirm('Anular este vale?')) return;
    try {
      await post(`/vales/${id}/anular`);
      get('/vales', { estado: filtroEstado }).then((d) => setVales(d.data || [])).catch(() => {});
    } catch {}
  };

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '1.5rem' }}>
        <h2>Vales y Creditos Corporativos</h2>
        {isGerente && (
          <button className="btn btn-primary" onClick={() => setShowForm(!showForm)}>
            {showForm ? 'Cancelar' : 'Nuevo Vale'}
          </button>
        )}
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {mensaje && <div className="alert alert-success">{mensaje}</div>}

      {/* Form nuevo vale */}
      {showForm && (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-header">
            <h3 className="card-title">Emitir Nuevo Vale</h3>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
            <div className="form-group">
              <label className="form-label">Cliente</label>
              <select className="form-select" value={form.cliente_id} onChange={(e) => setForm({ ...form, cliente_id: e.target.value })}>
                <option value="">Seleccionar...</option>
                {clientes.map((c) => (
                  <option key={c.id} value={c.id}>{c.razon_social} (NIT: {c.nit})</option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label className="form-label">Monto Autorizado (Q)</label>
              <input type="number" step="0.01" className="form-input" value={form.monto_autorizado}
                onChange={(e) => setForm({ ...form, monto_autorizado: e.target.value })} />
            </div>
            <div className="form-group">
              <label className="form-label">Fecha Vencimiento</label>
              <input type="date" className="form-input" value={form.fecha_vencimiento}
                onChange={(e) => setForm({ ...form, fecha_vencimiento: e.target.value })} />
            </div>
            <div className="form-group">
              <label className="form-label">Placa Vehiculo</label>
              <input type="text" className="form-input" value={form.placa_vehiculo}
                onChange={(e) => setForm({ ...form, placa_vehiculo: e.target.value })} placeholder="P-123ABC" />
            </div>
            <div className="form-group">
              <label className="form-label">Piloto</label>
              <input type="text" className="form-input" value={form.piloto}
                onChange={(e) => setForm({ ...form, piloto: e.target.value })} />
            </div>
          </div>
          <button className="btn btn-success" onClick={crearVale} disabled={loading} style={{ marginTop: '0.5rem' }}>
            Crear Vale
          </button>
        </div>
      )}

      {/* Filtros */}
      <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1rem' }}>
        {['activo', 'agotado', 'vencido', 'anulado'].map((e) => (
          <button key={e} className={`btn ${filtroEstado === e ? 'btn-primary' : 'btn-outline'}`}
            onClick={() => setFiltroEstado(e)}>
            {e.charAt(0).toUpperCase() + e.slice(1)}
          </button>
        ))}
      </div>

      {/* Tabla de vales */}
      <div className="card">
        <div className="table-container">
          <table>
            <thead>
              <tr>
                <th>Codigo</th>
                <th>Cliente</th>
                <th>Monto Autorizado</th>
                <th>Consumido</th>
                <th>Disponible</th>
                <th>Placa</th>
                <th>Vencimiento</th>
                <th>Estado</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {vales.map((v) => {
                const disponible = Number(v.monto_autorizado) - Number(v.monto_consumido);
                return (
                  <tr key={v.id}>
                    <td><strong>{v.codigo}</strong></td>
                    <td>{v.cliente_nombre}</td>
                    <td>{formatGTQ(v.monto_autorizado)}</td>
                    <td>{formatGTQ(v.monto_consumido)}</td>
                    <td><strong>{formatGTQ(disponible)}</strong></td>
                    <td>{v.placa_vehiculo || '-'}</td>
                    <td>{v.fecha_vencimiento}</td>
                    <td>
                      <span className={`badge ${
                        v.estado === 'activo' ? 'badge-success' :
                        v.estado === 'agotado' ? 'badge-warning' : 'badge-danger'
                      }`}>
                        {v.estado}
                      </span>
                    </td>
                    <td>
                      {v.estado === 'activo' && isGerente && (
                        <button className="btn btn-danger" style={{ padding: '0.25rem 0.5rem', fontSize: '0.75rem' }}
                          onClick={() => anularVale(v.id)}>
                          Anular
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
              {vales.length === 0 && (
                <tr><td colSpan="9" style={{ textAlign: 'center', color: 'var(--color-text-muted)' }}>
                  No hay vales con estado "{filtroEstado}".
                </td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
