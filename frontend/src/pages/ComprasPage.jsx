import React, { useEffect, useState } from 'react';
import { useApi, formatGTQ, formatGalones } from '../hooks/useApi';
import { useSucursal } from '../contexts/SucursalContext';

export default function ComprasPage() {
  const { sucursalActiva, sucursalId } = useSucursal();
  const { get, post, loading, error, clearError } = useApi();

  const [compras, setCompras] = useState([]);
  const [tanques, setTanques] = useState([]);
  const [showForm, setShowForm] = useState(false);
  const [mensaje, setMensaje] = useState('');

  // Formulario
  const [form, setForm] = useState({
    proveedor_id: '',
    numero_factura: '',
    fecha_factura: new Date().toISOString().slice(0, 10),
    notas: '',
  });
  const [detalle, setDetalle] = useState([]);
  const [lineaForm, setLineaForm] = useState({
    tanque_id: '',
    tipo_combustible_id: '',
    galones: '',
    precio_unitario: '',
    idp_unitario: '4.70',
  });

  useEffect(() => {
    if (!sucursalId) return;
    get('/compras').then((d) => setCompras(d.data || [])).catch(() => {});
    get('/inventario/tanques').then((d) => setTanques(d.data || [])).catch(() => {});
  }, [get, sucursalId]);

  const agregarLinea = () => {
    const tanque = tanques.find((t) => t.id === Number(lineaForm.tanque_id));
    if (!tanque || !lineaForm.galones || !lineaForm.precio_unitario) return;

    setDetalle((prev) => [
      ...prev,
      {
        ...lineaForm,
        tanque_id: Number(lineaForm.tanque_id),
        tipo_combustible_id: Number(tanque.tipo_combustible_id),
        galones: Number(lineaForm.galones),
        precio_unitario: Number(lineaForm.precio_unitario),
        idp_unitario: Number(lineaForm.idp_unitario),
        tanque_codigo: tanque.codigo,
        combustible: tanque.combustible_nombre,
      },
    ]);
    setLineaForm({ tanque_id: '', tipo_combustible_id: '', galones: '', precio_unitario: '', idp_unitario: '4.70' });
  };

  const registrarCompra = async () => {
    clearError();
    setMensaje('');
    if (detalle.length === 0) {
      setMensaje('Agregue al menos una linea de detalle.');
      return;
    }
    try {
      const body = {
        ...form,
        proveedor_id: Number(form.proveedor_id),
        detalle: detalle.map(({ tanque_id, tipo_combustible_id, galones, precio_unitario, idp_unitario }) => ({
          tanque_id, tipo_combustible_id, galones, precio_unitario, idp_unitario,
        })),
      };
      const data = await post('/compras', body);
      setMensaje(`Compra registrada. Factura: ${form.numero_factura}. Total: ${formatGTQ(data.data.total)}`);
      setShowForm(false);
      setDetalle([]);
      get('/compras').then((d) => setCompras(d.data || [])).catch(() => {});
    } catch {}
  };

  if (!sucursalId) {
    return (
      <div className="alert alert-warning">
        Seleccione una sucursal para registrar compras.
      </div>
    );
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '1.5rem' }}>
        <h2>Compras - {sucursalActiva?.nombre}</h2>
        <button className="btn btn-primary" onClick={() => setShowForm(!showForm)}>
          {showForm ? 'Cancelar' : 'Nueva Compra (Pipa)'}
        </button>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {mensaje && <div className="alert alert-success">{mensaje}</div>}

      {/* Form compra */}
      {showForm && (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-header">
            <h3 className="card-title">Registrar Recepcion de Pipa</h3>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem', marginBottom: '1rem' }}>
            <div className="form-group">
              <label className="form-label">ID Proveedor</label>
              <input type="number" className="form-input" value={form.proveedor_id}
                onChange={(e) => setForm({ ...form, proveedor_id: e.target.value })} />
            </div>
            <div className="form-group">
              <label className="form-label">No. Factura</label>
              <input type="text" className="form-input" value={form.numero_factura}
                onChange={(e) => setForm({ ...form, numero_factura: e.target.value })} placeholder="FAC-001" />
            </div>
            <div className="form-group">
              <label className="form-label">Fecha Factura</label>
              <input type="date" className="form-input" value={form.fecha_factura}
                onChange={(e) => setForm({ ...form, fecha_factura: e.target.value })} />
            </div>
          </div>

          {/* Agregar linea de detalle */}
          <h4 style={{ marginBottom: '0.5rem' }}>Detalle de Combustible</h4>
          <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', alignItems: 'end', marginBottom: '1rem' }}>
            <div className="form-group" style={{ minWidth: 180 }}>
              <label className="form-label">Tanque</label>
              <select className="form-select" value={lineaForm.tanque_id}
                onChange={(e) => setLineaForm({ ...lineaForm, tanque_id: e.target.value })}>
                <option value="">Seleccionar...</option>
                {tanques.map((t) => (
                  <option key={t.id} value={t.id}>{t.codigo} - {t.combustible_nombre}</option>
                ))}
              </select>
            </div>
            <div className="form-group" style={{ width: 120 }}>
              <label className="form-label">Galones</label>
              <input type="number" step="0.01" className="form-input" value={lineaForm.galones}
                onChange={(e) => setLineaForm({ ...lineaForm, galones: e.target.value })} />
            </div>
            <div className="form-group" style={{ width: 120 }}>
              <label className="form-label">Precio/Gal (Q)</label>
              <input type="number" step="0.01" className="form-input" value={lineaForm.precio_unitario}
                onChange={(e) => setLineaForm({ ...lineaForm, precio_unitario: e.target.value })} />
            </div>
            <div className="form-group" style={{ width: 120 }}>
              <label className="form-label">IDP/Gal (Q)</label>
              <input type="number" step="0.01" className="form-input" value={lineaForm.idp_unitario}
                onChange={(e) => setLineaForm({ ...lineaForm, idp_unitario: e.target.value })} />
            </div>
            <button className="btn btn-primary" onClick={agregarLinea}>Agregar</button>
          </div>

          {/* Tabla de detalle */}
          {detalle.length > 0 && (
            <table style={{ marginBottom: '1rem' }}>
              <thead>
                <tr>
                  <th>Tanque</th>
                  <th>Combustible</th>
                  <th>Galones</th>
                  <th>Precio/Gal</th>
                  <th>IDP/Gal</th>
                  <th>Subtotal</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {detalle.map((d, i) => (
                  <tr key={i}>
                    <td>{d.tanque_codigo}</td>
                    <td>{d.combustible}</td>
                    <td>{formatGalones(d.galones)}</td>
                    <td>{formatGTQ(d.precio_unitario)}</td>
                    <td>{formatGTQ(d.idp_unitario)}</td>
                    <td>{formatGTQ(d.galones * d.precio_unitario)}</td>
                    <td>
                      <button className="btn btn-danger" style={{ padding: '0.2rem 0.4rem', fontSize: '0.75rem' }}
                        onClick={() => setDetalle((prev) => prev.filter((_, j) => j !== i))}>X</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          <button className="btn btn-success" onClick={registrarCompra} disabled={loading}>
            Registrar Compra
          </button>
        </div>
      )}

      {/* Lista de compras */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title">Compras Registradas</h3>
        </div>
        <div className="table-container">
          <table>
            <thead>
              <tr>
                <th>Factura</th>
                <th>Proveedor</th>
                <th>Fecha</th>
                <th>Subtotal</th>
                <th>IDP</th>
                <th>IVA</th>
                <th>Total</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              {compras.map((c) => (
                <tr key={c.id}>
                  <td><strong>{c.numero_factura}</strong></td>
                  <td>{c.proveedor_nombre}</td>
                  <td>{c.fecha_factura}</td>
                  <td>{formatGTQ(c.subtotal)}</td>
                  <td>{formatGTQ(c.idp_total)}</td>
                  <td>{formatGTQ(c.iva_total)}</td>
                  <td><strong>{formatGTQ(c.total)}</strong></td>
                  <td>
                    <span className={`badge ${c.estado === 'pagada' ? 'badge-success' : 'badge-warning'}`}>
                      {c.estado}
                    </span>
                  </td>
                </tr>
              ))}
              {compras.length === 0 && (
                <tr><td colSpan="8" style={{ textAlign: 'center', color: 'var(--color-text-muted)' }}>
                  No hay compras registradas.
                </td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
