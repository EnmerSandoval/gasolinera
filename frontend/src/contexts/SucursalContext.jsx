import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import api from '../api/client';
import { useAuth } from './AuthContext';

const SucursalContext = createContext(null);

/**
 * Contexto de Sucursal Activa.
 *
 * Permite al admin cambiar entre sucursales.
 * Los usuarios vinculados a una sucursal fija no pueden cambiar.
 */
export function SucursalProvider({ children }) {
  const { user, hasMultiSucursal, isAuthenticated } = useAuth();
  const [sucursales, setSucursales] = useState([]);
  const [sucursalActiva, setSucursalActiva] = useState(null);
  const [loading, setLoading] = useState(false);

  // Cargar lista de sucursales al autenticarse
  useEffect(() => {
    if (!isAuthenticated) {
      setSucursales([]);
      setSucursalActiva(null);
      return;
    }

    setLoading(true);
    api.get('/sucursales')
      .then((data) => {
        setSucursales(data.data || []);

        if (hasMultiSucursal) {
          // Admin: restaurar última sucursal seleccionada o usar la primera
          const savedId = localStorage.getItem('sucursal_id');
          const found = (data.data || []).find(
            (s) => s.id === Number(savedId)
          );
          if (found) {
            setSucursalActiva(found);
            api.setSucursalId(String(found.id));
          }
          // Si no hay guardada, dejar null (vista consolidada)
        } else {
          // Usuario de sucursal fija
          const fija = (data.data || []).find(
            (s) => s.id === user.sucursal_id
          );
          if (fija) {
            setSucursalActiva(fija);
            api.setSucursalId(String(fija.id));
          }
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [isAuthenticated, user, hasMultiSucursal]);

  const cambiarSucursal = useCallback(
    (sucursalId) => {
      if (!hasMultiSucursal) return; // No permitir cambio

      if (sucursalId === null || sucursalId === '') {
        // Vista consolidada
        setSucursalActiva(null);
        api.setSucursalId(null);
        return;
      }

      const sucursal = sucursales.find((s) => s.id === Number(sucursalId));
      if (sucursal) {
        setSucursalActiva(sucursal);
        api.setSucursalId(String(sucursal.id));
      }
    },
    [sucursales, hasMultiSucursal]
  );

  const value = {
    sucursales,
    sucursalActiva,
    sucursalId: sucursalActiva?.id || null,
    cambiarSucursal,
    hasMultiSucursal,
    loading,
  };

  return (
    <SucursalContext.Provider value={value}>
      {children}
    </SucursalContext.Provider>
  );
}

export function useSucursal() {
  const context = useContext(SucursalContext);
  if (!context) {
    throw new Error('useSucursal debe usarse dentro de SucursalProvider');
  }
  return context;
}
