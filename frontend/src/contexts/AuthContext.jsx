import React, { createContext, useContext, useState, useCallback, useEffect } from 'react';
import api from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // Al montar, verificar si hay token guardado
  useEffect(() => {
    const token = localStorage.getItem('access_token');
    if (token) {
      api.get('/auth/me')
        .then((data) => setUser(data.user))
        .catch(() => {
          api.clearTokens();
          setUser(null);
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, []);

  // Callback cuando el token no se puede renovar
  useEffect(() => {
    api.onUnauthorized = () => {
      setUser(null);
    };
    return () => { api.onUnauthorized = null; };
  }, []);

  const login = useCallback(async (email, password) => {
    const data = await api.request('POST', '/auth/login', {
      body: { email, password },
      skipAuth: true,
    });

    api.setTokens(data.access_token, data.refresh_token);
    setUser(data.user);
    return data.user;
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } catch {
      // Ignorar errores de logout
    }
    api.clearTokens();
    api.setSucursalId(null);
    setUser(null);
  }, []);

  const isAdmin = user?.rol === 'admin';
  const isGerente = user?.rol === 'gerente' || isAdmin;
  const hasMultiSucursal = user?.sucursal_id === null; // Admin puede ver todas

  const value = {
    user,
    loading,
    login,
    logout,
    isAdmin,
    isGerente,
    hasMultiSucursal,
    isAuthenticated: !!user,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth debe usarse dentro de AuthProvider');
  }
  return context;
}
