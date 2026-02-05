import React from 'react';
import { Outlet, NavLink } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import SucursalSelector from '../dashboard/SucursalSelector';

export default function Layout() {
  const { user, logout, isAdmin } = useAuth();

  return (
    <div className="app-layout">
      {/* Sidebar */}
      <aside className="sidebar">
        <div className="sidebar-brand">Gasolinera ERP</div>
        <ul className="sidebar-nav">
          <li>
            <NavLink to="/" end>
              Dashboard
            </NavLink>
          </li>
          <li>
            <NavLink to="/pos">
              Punto de Venta
            </NavLink>
          </li>
          <li>
            <NavLink to="/inventario">
              Inventario
            </NavLink>
          </li>
          <li>
            <NavLink to="/vales">
              Vales / Creditos
            </NavLink>
          </li>
          <li>
            <NavLink to="/compras">
              Compras
            </NavLink>
          </li>
        </ul>
        <div style={{ padding: '1.5rem', borderTop: '1px solid rgba(255,255,255,0.1)', marginTop: 'auto' }}>
          <div style={{ fontSize: '0.85rem', opacity: 0.7, marginBottom: '0.25rem' }}>{user?.rol}</div>
          <div style={{ fontSize: '0.9rem' }}>{user?.nombre}</div>
        </div>
      </aside>

      {/* Main content */}
      <div className="main-content">
        <header className="topbar">
          <SucursalSelector />
          <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
            <span style={{ fontSize: '0.9rem', color: 'var(--color-text-muted)' }}>
              {user?.email}
            </span>
            <button className="btn btn-outline" onClick={logout}>
              Cerrar sesion
            </button>
          </div>
        </header>

        <main className="page-content">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
