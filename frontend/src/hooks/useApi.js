import { useState, useCallback } from 'react';
import api, { ApiError } from '../api/client';

/**
 * Hook genérico para llamadas a la API con estado de carga y error.
 */
export function useApi() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const execute = useCallback(async (method, path, options = {}) => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.request(method, path, options);
      return data;
    } catch (err) {
      const message = err instanceof ApiError ? err.message : 'Error de conexión.';
      setError(message);
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  const get = useCallback((path, params) => execute('GET', path, { params }), [execute]);
  const post = useCallback((path, body) => execute('POST', path, { body }), [execute]);
  const put = useCallback((path, body) => execute('PUT', path, { body }), [execute]);

  const clearError = useCallback(() => setError(null), []);

  return { loading, error, get, post, put, execute, clearError };
}

/**
 * Formatea un número como moneda GTQ (Quetzales).
 */
export function formatGTQ(amount) {
  return new Intl.NumberFormat('es-GT', {
    style: 'currency',
    currency: 'GTQ',
  }).format(Number(amount) || 0);
}

/**
 * Formatea galones.
 */
export function formatGalones(amount) {
  return `${Number(amount || 0).toFixed(2)} gal`;
}
