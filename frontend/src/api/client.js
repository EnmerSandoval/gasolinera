/**
 * Cliente HTTP para la API del backend.
 * Maneja autenticación JWT, refresh automático y header X-Sucursal-Id.
 */

const API_BASE = import.meta.env.VITE_API_URL || '/api';

class ApiClient {
  constructor() {
    this.baseUrl = API_BASE;
    this.accessToken = localStorage.getItem('access_token');
    this.refreshToken = localStorage.getItem('refresh_token');
    this.sucursalId = localStorage.getItem('sucursal_id');
    this.onUnauthorized = null; // Callback cuando el token expira sin remedio
  }

  setTokens(accessToken, refreshToken) {
    this.accessToken = accessToken;
    this.refreshToken = refreshToken;
    localStorage.setItem('access_token', accessToken);
    localStorage.setItem('refresh_token', refreshToken);
  }

  clearTokens() {
    this.accessToken = null;
    this.refreshToken = null;
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
  }

  setSucursalId(id) {
    this.sucursalId = id;
    if (id) {
      localStorage.setItem('sucursal_id', id);
    } else {
      localStorage.removeItem('sucursal_id');
    }
  }

  /**
   * Ejecuta un request a la API.
   */
  async request(method, path, { body, params, skipAuth } = {}) {
    let url = `${this.baseUrl}${path}`;

    // Query params
    if (params) {
      const qs = new URLSearchParams(
        Object.fromEntries(
          Object.entries(params).filter(([, v]) => v != null)
        )
      ).toString();
      if (qs) url += `?${qs}`;
    }

    const headers = {
      'Content-Type': 'application/json',
    };

    if (!skipAuth && this.accessToken) {
      headers['Authorization'] = `Bearer ${this.accessToken}`;
    }

    if (this.sucursalId) {
      headers['X-Sucursal-Id'] = this.sucursalId;
    }

    const config = { method, headers };
    if (body && method !== 'GET') {
      config.body = JSON.stringify(body);
    }

    let response = await fetch(url, config);

    // Si 401, intentar refresh
    if (response.status === 401 && !skipAuth && this.refreshToken) {
      const refreshed = await this.tryRefreshToken();
      if (refreshed) {
        headers['Authorization'] = `Bearer ${this.accessToken}`;
        response = await fetch(url, { ...config, headers });
      } else {
        this.clearTokens();
        if (this.onUnauthorized) this.onUnauthorized();
        throw new ApiError('Sesión expirada. Inicie sesión nuevamente.', 401);
      }
    }

    const data = await response.json();

    if (!response.ok) {
      throw new ApiError(
        data.message || 'Error en la solicitud.',
        response.status,
        data.errors
      );
    }

    return data;
  }

  async tryRefreshToken() {
    try {
      const response = await fetch(`${this.baseUrl}/auth/refresh`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: this.refreshToken }),
      });

      if (!response.ok) return false;

      const data = await response.json();
      this.setTokens(data.access_token, data.refresh_token);
      return true;
    } catch {
      return false;
    }
  }

  // Métodos de conveniencia
  get(path, params) {
    return this.request('GET', path, { params });
  }

  post(path, body) {
    return this.request('POST', path, { body });
  }

  put(path, body) {
    return this.request('PUT', path, { body });
  }

  patch(path, body) {
    return this.request('PATCH', path, { body });
  }

  delete(path) {
    return this.request('DELETE', path);
  }
}

export class ApiError extends Error {
  constructor(message, status, errors = []) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }
}

// Instancia singleton
const api = new ApiClient();
export default api;
