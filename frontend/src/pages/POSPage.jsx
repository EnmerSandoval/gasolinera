import React, { useEffect, useState, useCallback } from 'react';
import { useApi, formatGTQ } from '../hooks/useApi';
import { useSucursal } from '../contexts/SucursalContext';

/**
 * Punto de Venta (POS).
 *
 * Flujo: Seleccionar Bomba -> Tipo Combustible -> Galones/Monto -> Agregar productos -> Pagar
 */
export default function POSPage() {
  const { sucursalActiva, sucursalId } = useSucursal();
  const { get, post, loading, error, clearError } = useApi();

  // Estado del turno
  const [turno, setTurno] = useState(null);
  const [efectivoInicial, setEfectivoInicial] = useState('');

  // Datos de config
  const [tanques, setTanques] = useState([]);

  // Ticket actual
  const [lineasCombustible, setLineasCombustible] = useState([]);
  const [lineasProducto, setLineasProducto] = useState([]);
  const [formaPago, setFormaPago] = useState('efectivo');
  const [valeCodigo, setValeCodigo] = useState('');
  const [mensaje, setMensaje] = useState('');

  // Formulario nueva linea combustible
  const [mangueraId, setMangueraId] = useState('');
  const [galones, setGalones] = useState('');

  // Cargar turno activo y datos
  useEffect(() => {
    if (!sucursalId) return;

    get('/turnos/activo').then((data) => {
      setTurno(data.data);
    }).catch(() => {});

    get('/inventario/tanques').then((data) => {
      setTanques(data.data || []);
    }).catch(() => {});
  }, [get, sucursalId]);

  const abrirTurno = async () => {
    clearError();
    try {
      const data = await post('/turnos/abrir', {
        efectivo_inicial: Number(efectivoInicial) || 0,
      });
      setTurno(data.data);
      setEfectivoInicial('');
    } catch {}
  };

  const agregarCombustible = () => {
    if (!mangueraId || !galones) return;
    setLineasCombustible((prev) => [
      ...prev,
      {
        manguera_id: Number(mangueraId),
        galones: Number(galones),
      },
    ]);
    setMangueraId('');
    setGalones('');
  };

  const removerLineaComb = (index) => {
    setLineasCombustible((prev) => prev.filter((_, i) => i !== index));
  };

  const registrarVenta = async () => {
    clearError();
    setMensaje('');

    if (lineasCombustible.length === 0 && lineasProducto.length === 0) {
      setMensaje('Agregue al menos un combustible o producto.');
      return;
    }

    try {
      const body = {
        forma_pago: formaPago,
        monto_efectivo: formaPago === 'efectivo' ? 999999 : 0, // Se calcula en backend
        monto_tarjeta: formaPago === 'tarjeta' ? 999999 : 0,
        monto_vale: formaPago === 'vale' ? 999999 : 0,
        vale_codigo: formaPago === 'vale' ? valeCodigo : null,
        combustibles: lineasCombustible,
        productos: lineasProducto,
      };

      const data = await post('/ventas', body);

      setMensaje(`Venta registrada: ${data.data.numero_ticket} - Total: ${formatGTQ(data.data.total)}`);
      setLineasCombustible([]);
      setLineasProducto([]);
      setFormaPago('efectivo');
      setValeCodigo('');
    } catch {}
  };

  if (!sucursalId) {
    return (
      <div className="alert alert-warning">
        Seleccione una sucursal para usar el Punto de Venta.
      </div>
    );
  }

  // Si no hay turno abierto
  if (!turno) {
    return (
      <div>
        <h2>Punto de Venta - {sucursalActiva?.nombre}</h2>
        <div className="card" style={{ maxWidth: 400, marginTop: '2rem' }}>
          <h3 style={{ marginBottom: '1rem' }}>Abrir Turno</h3>
          <p style={{ color: 'var(--color-text-muted)', marginBottom: '1rem' }}>
            Debe abrir un turno antes de registrar ventas.
          </p>
          {error && <div className="alert alert-danger">{error}</div>}
          <div className="form-group">
            <label className="form-label">Efectivo Inicial (Q)</label>
            <input
              type="number"
              className="form-input"
              value={efectivoInicial}
              onChange={(e) => setEfectivoInicial(e.target.value)}
              placeholder="0.00"
            />
          </div>
          <button className="btn btn-primary" onClick={abrirTurno} disabled={loading}>
            Abrir Turno
          </button>
        </div>
      </div>
    );
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '1.5rem' }}>
        <h2>Punto de Venta - {sucursalActiva?.nombre}</h2>
        <span className="badge badge-success">Turno Abierto #{turno.id}</span>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {mensaje && <div className="alert alert-success">{mensaje}</div>}

      <div className="pos-layout">
        {/* Panel izquierdo: agregar items */}
        <div className="pos-products">
          {/* Agregar combustible */}
          <div className="card" style={{ marginBottom: '1.5rem' }}>
            <div className="card-header">
              <h3 className="card-title">Despacho de Combustible</h3>
            </div>

            {/* Tanques disponibles */}
            <div style={{ marginBottom: '1rem', fontSize: '0.85rem', color: 'var(--color-text-muted)' }}>
              Tanques disponibles: {tanques.map((t) => `${t.codigo} (${t.combustible_nombre}: ${Number(t.stock_actual).toFixed(0)} gal)`).join(' | ')}
            </div>

            <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'end' }}>
              <div className="form-group" style={{ flex: 1 }}>
                <label className="form-label">ID Manguera</label>
                <input
                  type="number"
                  className="form-input"
                  value={mangueraId}
                  onChange={(e) => setMangueraId(e.target.value)}
                  placeholder="Ej: 1"
                />
              </div>
              <div className="form-group" style={{ flex: 1 }}>
                <label className="form-label">Galones</label>
                <input
                  type="number"
                  step="0.01"
                  className="form-input"
                  value={galones}
                  onChange={(e) => setGalones(e.target.value)}
                  placeholder="0.00"
                />
              </div>
              <button className="btn btn-primary" onClick={agregarCombustible}>
                Agregar
              </button>
            </div>
          </div>

          {/* Forma de pago */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Forma de Pago</h3>
            </div>
            <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem' }}>
              {['efectivo', 'tarjeta', 'vale'].map((fp) => (
                <button
                  key={fp}
                  className={`btn ${formaPago === fp ? 'btn-primary' : 'btn-outline'}`}
                  onClick={() => setFormaPago(fp)}
                >
                  {fp.charAt(0).toUpperCase() + fp.slice(1)}
                </button>
              ))}
            </div>

            {formaPago === 'vale' && (
              <div className="form-group">
                <label className="form-label">Codigo de Vale</label>
                <input
                  type="text"
                  className="form-input"
                  value={valeCodigo}
                  onChange={(e) => setValeCodigo(e.target.value)}
                  placeholder="V-XXXXXXXX"
                />
              </div>
            )}
          </div>
        </div>

        {/* Panel derecho: ticket */}
        <div className="pos-ticket">
          <div className="pos-ticket-header">
            Ticket de Venta
          </div>
          <div className="pos-ticket-items">
            {lineasCombustible.length === 0 && lineasProducto.length === 0 ? (
              <p style={{ color: 'var(--color-text-muted)', textAlign: 'center', marginTop: '2rem' }}>
                Sin items. Agregue combustible o productos.
              </p>
            ) : (
              <>
                {lineasCombustible.map((l, i) => (
                  <div
                    key={i}
                    style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      padding: '0.5rem 0',
                      borderBottom: '1px solid var(--color-border)',
                    }}
                  >
                    <div>
                      <div style={{ fontWeight: 500 }}>Manguera #{l.manguera_id}</div>
                      <div style={{ fontSize: '0.8rem', color: 'var(--color-text-muted)' }}>
                        {l.galones} galones
                      </div>
                    </div>
                    <button
                      className="btn btn-danger"
                      style={{ padding: '0.25rem 0.5rem', fontSize: '0.75rem' }}
                      onClick={() => removerLineaComb(i)}
                    >
                      X
                    </button>
                  </div>
                ))}
              </>
            )}
          </div>
          <div className="pos-ticket-footer">
            <div className="pos-total">
              {lineasCombustible.length + lineasProducto.length} items
            </div>
            <button
              className="btn btn-success"
              style={{ width: '100%', justifyContent: 'center', padding: '0.75rem' }}
              onClick={registrarVenta}
              disabled={loading}
            >
              {loading ? 'Procesando...' : 'Cobrar'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
