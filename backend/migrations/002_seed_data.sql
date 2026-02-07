-- ============================================================
-- Datos de prueba para desarrollo
-- ============================================================

-- Empresa
INSERT INTO empresas (id, nombre, nit, direccion_fiscal, telefono, email) VALUES
(1, 'Combustibles del Sur S.A.', '12345678-9', '6a Avenida 10-20, Zona 1, Guatemala', '2222-3333', 'admin@combustiblesdelsur.com.gt');

-- Roles
INSERT INTO roles (id, empresa_id, nombre, descripcion, permisos) VALUES
(1, 1, 'admin', 'Administrador del sistema', '["*"]'),
(2, 1, 'gerente', 'Gerente de sucursal', '["ventas.*","inventario.*","reportes.*","turnos.*","vales.ver","vales.crear"]'),
(3, 1, 'islero', 'Despachador de combustible', '["ventas.crear","turnos.abrir","turnos.cerrar","vales.validar"]'),
(4, 1, 'contador', 'Contabilidad', '["reportes.*","compras.*","vales.ver","clientes.ver"]');

-- Sucursales (3 gasolineras)
INSERT INTO sucursales (id, empresa_id, codigo, nombre, direccion, municipio, departamento) VALUES
(1, 1, 'GAS-01', 'Gasolinera Central', 'Km 15 Carretera al Pacifico', 'Amatitlan', 'Guatemala'),
(2, 1, 'GAS-02', 'Gasolinera Norte', 'Km 25 Carretera al Atlantico', 'San Jose Pinula', 'Guatemala'),
(3, 1, 'GAS-03', 'Gasolinera Sur', 'Km 45 Ruta a Escuintla', 'Palin', 'Escuintla');

-- Tipos de combustible
INSERT INTO tipos_combustible (id, empresa_id, nombre, codigo, color_hex) VALUES
(1, 1, 'Regular', 'REG', '#EF4444'),
(2, 1, 'Super', 'SUP', '#3B82F6'),
(3, 1, 'Diesel', 'DSL', '#F59E0B'),
(4, 1, 'V-Power', 'VPW', '#8B5CF6');

-- Usuarios (password: Admin123!)
-- Hash generado con password_hash('Admin123!', PASSWORD_BCRYPT)
INSERT INTO usuarios (id, empresa_id, sucursal_id, rol_id, username, email, password_hash, nombre_completo) VALUES
(1, 1, NULL, 1, 'admin', 'admin@combustiblesdelsur.com.gt', '$2y$12$P1D9aKHijDFG6gdUj5DF8uuYH34P1mc2V4dXeiAk4T0przqKTJ4OO', 'Carlos Rodriguez (Admin)'),
(2, 1, 1, 2, 'gerente1', 'gerente1@combustiblesdelsur.com.gt', '$2y$12$P1D9aKHijDFG6gdUj5DF8uuYH34P1mc2V4dXeiAk4T0przqKTJ4OO', 'Maria Lopez (Gerente Central)'),
(3, 1, 1, 3, 'islero1', 'islero1@combustiblesdelsur.com.gt', '$2y$12$P1D9aKHijDFG6gdUj5DF8uuYH34P1mc2V4dXeiAk4T0przqKTJ4OO', 'Juan Perez (Islero)'),
(4, 1, 2, 3, 'islero2', 'islero2@combustiblesdelsur.com.gt', '$2y$12$P1D9aKHijDFG6gdUj5DF8uuYH34P1mc2V4dXeiAk4T0przqKTJ4OO', 'Pedro Garcia (Islero Norte)');

-- Tanques por sucursal
-- Sucursal 1: Central
INSERT INTO tanques (id, sucursal_id, tipo_combustible_id, codigo, capacidad_galones, nivel_minimo, stock_actual) VALUES
(1, 1, 1, 'TK-01', 10000, 2000, 7500),
(2, 1, 2, 'TK-02', 8000, 1500, 5200),
(3, 1, 3, 'TK-03', 12000, 2500, 9800);

-- Sucursal 2: Norte
INSERT INTO tanques (id, sucursal_id, tipo_combustible_id, codigo, capacidad_galones, nivel_minimo, stock_actual) VALUES
(4, 2, 1, 'TK-01', 8000, 1500, 3200),
(5, 2, 2, 'TK-02', 6000, 1200, 4100),
(6, 2, 3, 'TK-03', 10000, 2000, 8000);

-- Sucursal 3: Sur
INSERT INTO tanques (id, sucursal_id, tipo_combustible_id, codigo, capacidad_galones, nivel_minimo, stock_actual) VALUES
(7, 3, 1, 'TK-01', 10000, 2000, 6800),
(8, 3, 3, 'TK-02', 15000, 3000, 12000);

-- Islas
INSERT INTO islas (id, sucursal_id, numero, descripcion) VALUES
(1, 1, 1, 'Isla 1 - Entrada'),
(2, 1, 2, 'Isla 2 - Centro'),
(3, 2, 1, 'Isla 1'),
(4, 3, 1, 'Isla 1');

-- Bombas
INSERT INTO bombas (id, isla_id, sucursal_id, numero, marca) VALUES
(1, 1, 1, 1, 'Gilbarco'),
(2, 1, 1, 2, 'Gilbarco'),
(3, 2, 1, 3, 'Wayne'),
(4, 3, 2, 1, 'Gilbarco'),
(5, 4, 3, 1, 'Wayne');

-- Mangueras
INSERT INTO mangueras (id, bomba_id, tanque_id, tipo_combustible_id, numero) VALUES
(1, 1, 1, 1, 1),
(2, 1, 2, 2, 2),
(3, 2, 1, 1, 1),
(4, 2, 3, 3, 2),
(5, 3, 2, 2, 1),
(6, 3, 3, 3, 2),
(7, 4, 4, 1, 1),
(8, 4, 6, 3, 2),
(9, 5, 7, 1, 1),
(10, 5, 8, 3, 2);

-- Precios vigentes
INSERT INTO precios_combustible (sucursal_id, tipo_combustible_id, precio_unitario, precio_compra, idp_por_galon, vigente_desde, activo) VALUES
(1, 1, 32.50, 25.00, 4.70, '2025-01-01 00:00:00', 1),
(1, 2, 38.90, 30.50, 4.70, '2025-01-01 00:00:00', 1),
(1, 3, 28.75, 22.00, 4.70, '2025-01-01 00:00:00', 1),
(2, 1, 32.50, 25.00, 4.70, '2025-01-01 00:00:00', 1),
(2, 2, 38.90, 30.50, 4.70, '2025-01-01 00:00:00', 1),
(2, 3, 28.75, 22.00, 4.70, '2025-01-01 00:00:00', 1),
(3, 1, 33.00, 25.50, 4.70, '2025-01-01 00:00:00', 1),
(3, 3, 29.00, 22.50, 4.70, '2025-01-01 00:00:00', 1);

-- Proveedores
INSERT INTO proveedores (id, empresa_id, nit, razon_social, tipo) VALUES
(1, 1, '98765432-1', 'Petroleos de Guatemala S.A.', 'combustible'),
(2, 1, '11223344-5', 'Shell Guatemala', 'combustible');

-- Clientes corporativos
INSERT INTO clientes (id, empresa_id, nit, razon_social, nombre_comercial, limite_credito, saldo_actual, dias_credito) VALUES
(1, 1, '55667788-9', 'Transportes del Sur S.A.', 'TransSur', 50000.00, 12500.00, 30),
(2, 1, '99887766-5', 'Distribuidora Nacional S.A.', 'DistriNac', 100000.00, 35000.00, 15);

-- Productos de pista
INSERT INTO productos (id, empresa_id, codigo, nombre, categoria, precio_venta, precio_compra) VALUES
(1, 1, 'ACE-01', 'Aceite Motor 10W-40 (1L)', 'Aceites', 85.00, 55.00),
(2, 1, 'ACE-02', 'Aceite Motor 20W-50 (1L)', 'Aceites', 75.00, 48.00),
(3, 1, 'ADI-01', 'Aditivo Limpiador Inyectores', 'Aditivos', 45.00, 28.00),
(4, 1, 'LIQ-01', 'Liquido de Frenos DOT4', 'Liquidos', 55.00, 35.00);
