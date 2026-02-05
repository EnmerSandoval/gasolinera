import React from 'react';
import { useSucursal } from '../../contexts/SucursalContext';

/**
 * Selector de sucursal para el topbar.
 *
 * - Admin/Gerente sin sucursal fija: puede elegir cualquiera o "Todas (Consolidado)"
 * - Islero con sucursal fija: muestra solo su sucursal (sin cambiar)
 */
export default function SucursalSelector() {
  const {
    sucursales,
    sucursalActiva,
    cambiarSucursal,
    hasMultiSucursal,
  } = useSucursal();

  if (!hasMultiSucursal) {
    // Usuario con sucursal fija
    return (
      <div className="sucursal-selector">
        <span style={{ fontSize: '0.85rem', color: 'var(--color-text-muted)' }}>
          Sucursal:
        </span>
        <strong>{sucursalActiva?.nombre || 'Sin asignar'}</strong>
      </div>
    );
  }

  // Admin: selector dropdown
  return (
    <div className="sucursal-selector">
      <label
        htmlFor="sucursal-select"
        style={{ fontSize: '0.85rem', color: 'var(--color-text-muted)' }}
      >
        Sucursal:
      </label>
      <select
        id="sucursal-select"
        value={sucursalActiva?.id || ''}
        onChange={(e) => cambiarSucursal(e.target.value || null)}
      >
        <option value="">Todas (Consolidado)</option>
        {sucursales.map((s) => (
          <option key={s.id} value={s.id}>
            {s.codigo} - {s.nombre}
          </option>
        ))}
      </select>
    </div>
  );
}
