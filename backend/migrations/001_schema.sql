-- ============================================================
-- ERP Gasolinera Multi-Sucursal - Schema de Base de Datos
-- Diseñado para Guatemala (IDP + IVA)
-- Motor: MySQL 8.0+ / InnoDB
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- 1. EMPRESA (Tenant raíz)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS empresas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(200)    NOT NULL,
    nit             VARCHAR(20)     NOT NULL UNIQUE COMMENT 'NIT Guatemala',
    direccion_fiscal VARCHAR(300)   NULL,
    telefono        VARCHAR(20)     NULL,
    email           VARCHAR(150)    NULL,
    logo_url        VARCHAR(500)    NULL,
    moneda          CHAR(3)         NOT NULL DEFAULT 'GTQ',
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 2. SUCURSALES (Gasolineras)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS sucursales (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED    NOT NULL,
    codigo          VARCHAR(20)     NOT NULL COMMENT 'Código interno ej: GAS-01',
    nombre          VARCHAR(200)    NOT NULL,
    direccion       VARCHAR(300)    NOT NULL,
    municipio       VARCHAR(100)    NULL,
    departamento    VARCHAR(100)    NULL,
    telefono        VARCHAR(20)     NULL,
    responsable     VARCHAR(150)    NULL,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_codigo (empresa_id, codigo),
    CONSTRAINT fk_sucursal_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 3. TIPOS DE COMBUSTIBLE
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_combustible (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED    NOT NULL,
    nombre          VARCHAR(100)    NOT NULL COMMENT 'Regular, Super, Diesel, V-Power',
    codigo          VARCHAR(20)     NOT NULL,
    color_hex       CHAR(7)         NULL COMMENT '#FF0000 para UI',
    unidad_medida   VARCHAR(10)     NOT NULL DEFAULT 'GAL' COMMENT 'GAL o LT',
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    UNIQUE KEY uk_empresa_tipo (empresa_id, codigo),
    CONSTRAINT fk_tipo_comb_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 4. TANQUES DE ALMACENAMIENTO (por sucursal)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tanques (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id         INT UNSIGNED    NOT NULL,
    tipo_combustible_id INT UNSIGNED    NOT NULL,
    codigo              VARCHAR(20)     NOT NULL COMMENT 'TK-01',
    capacidad_galones   DECIMAL(12,4)   NOT NULL,
    nivel_minimo        DECIMAL(12,4)   NOT NULL DEFAULT 0 COMMENT 'Alerta de reorden',
    stock_actual        DECIMAL(12,4)   NOT NULL DEFAULT 0,
    activo              TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sucursal_tanque (sucursal_id, codigo),
    CONSTRAINT fk_tanque_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tanque_tipo FOREIGN KEY (tipo_combustible_id)
        REFERENCES tipos_combustible(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 5. ISLAS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS islas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id     INT UNSIGNED    NOT NULL,
    numero          SMALLINT UNSIGNED NOT NULL,
    descripcion     VARCHAR(100)    NULL,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    UNIQUE KEY uk_sucursal_isla (sucursal_id, numero),
    CONSTRAINT fk_isla_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 6. BOMBAS / DISPENSADORES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS bombas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isla_id         INT UNSIGNED    NOT NULL,
    sucursal_id     INT UNSIGNED    NOT NULL,
    numero          SMALLINT UNSIGNED NOT NULL,
    marca           VARCHAR(50)     NULL,
    modelo          VARCHAR(50)     NULL,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    UNIQUE KEY uk_sucursal_bomba (sucursal_id, numero),
    CONSTRAINT fk_bomba_isla FOREIGN KEY (isla_id)
        REFERENCES islas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_bomba_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 7. MANGUERAS (cada bomba puede despachar N tipos de combustible)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS mangueras (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bomba_id            INT UNSIGNED    NOT NULL,
    tanque_id           INT UNSIGNED    NOT NULL,
    tipo_combustible_id INT UNSIGNED    NOT NULL,
    numero              SMALLINT UNSIGNED NOT NULL,
    activo              TINYINT(1)      NOT NULL DEFAULT 1,
    UNIQUE KEY uk_bomba_manguera (bomba_id, numero),
    CONSTRAINT fk_manguera_bomba FOREIGN KEY (bomba_id)
        REFERENCES bombas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_manguera_tanque FOREIGN KEY (tanque_id)
        REFERENCES tanques(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_manguera_tipo FOREIGN KEY (tipo_combustible_id)
        REFERENCES tipos_combustible(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 8. ROLES Y USUARIOS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED    NOT NULL,
    nombre          VARCHAR(50)     NOT NULL COMMENT 'admin, gerente, islero, contador',
    descripcion     VARCHAR(200)    NULL,
    permisos        JSON            NOT NULL COMMENT 'Array de permisos granulares',
    UNIQUE KEY uk_empresa_rol (empresa_id, nombre),
    CONSTRAINT fk_rol_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED    NOT NULL,
    sucursal_id     INT UNSIGNED    NULL COMMENT 'NULL = acceso a todas las sucursales',
    rol_id          INT UNSIGNED    NOT NULL,
    username        VARCHAR(50)     NOT NULL,
    email           VARCHAR(150)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    nombre_completo VARCHAR(150)    NOT NULL,
    telefono        VARCHAR(20)     NULL,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    ultimo_login    TIMESTAMP       NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_username (empresa_id, username),
    UNIQUE KEY uk_email (email),
    CONSTRAINT fk_usuario_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (rol_id)
        REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 9. TURNOS DE TRABAJO (por sucursal)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS turnos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id     INT UNSIGNED    NOT NULL,
    usuario_id      INT UNSIGNED    NOT NULL COMMENT 'Islero que abre turno',
    fecha           DATE            NOT NULL,
    hora_inicio     DATETIME        NOT NULL,
    hora_fin        DATETIME        NULL,
    estado          ENUM('abierto','cerrado','anulado') NOT NULL DEFAULT 'abierto',
    efectivo_inicial DECIMAL(12,2)  NOT NULL DEFAULT 0,
    efectivo_final  DECIMAL(12,2)   NULL,
    notas           TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sucursal_fecha (sucursal_id, fecha),
    CONSTRAINT fk_turno_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_turno_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 10. PRECIOS DE COMBUSTIBLE (por sucursal, históricos)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS precios_combustible (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id         INT UNSIGNED    NOT NULL,
    tipo_combustible_id INT UNSIGNED    NOT NULL,
    precio_unitario     DECIMAL(10,4)   NOT NULL COMMENT 'Precio por galón',
    precio_compra       DECIMAL(10,4)   NOT NULL DEFAULT 0 COMMENT 'Costo por galón',
    idp_por_galon       DECIMAL(10,4)   NOT NULL DEFAULT 0 COMMENT 'IDP Guatemala Q4.70/gal aprox',
    vigente_desde       DATETIME        NOT NULL,
    vigente_hasta       DATETIME        NULL,
    activo              TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sucursal_tipo_vigente (sucursal_id, tipo_combustible_id, activo),
    CONSTRAINT fk_precio_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_precio_tipo FOREIGN KEY (tipo_combustible_id)
        REFERENCES tipos_combustible(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 11. VENTAS (Tickets de POS)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS ventas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id     INT UNSIGNED    NOT NULL,
    turno_id        INT UNSIGNED    NOT NULL,
    usuario_id      INT UNSIGNED    NOT NULL COMMENT 'Islero que registra',
    numero_ticket   VARCHAR(30)     NOT NULL,
    fecha           DATETIME        NOT NULL,
    subtotal        DECIMAL(12,2)   NOT NULL DEFAULT 0,
    idp_total       DECIMAL(12,2)   NOT NULL DEFAULT 0 COMMENT 'IDP total del ticket',
    iva_total       DECIMAL(12,2)   NOT NULL DEFAULT 0 COMMENT 'IVA 12% Guatemala',
    total           DECIMAL(12,2)   NOT NULL DEFAULT 0,
    forma_pago      ENUM('efectivo','tarjeta','vale','mixto') NOT NULL DEFAULT 'efectivo',
    monto_efectivo  DECIMAL(12,2)   NOT NULL DEFAULT 0,
    monto_tarjeta   DECIMAL(12,2)   NOT NULL DEFAULT 0,
    monto_vale      DECIMAL(12,2)   NOT NULL DEFAULT 0,
    referencia_tarjeta VARCHAR(50)  NULL,
    vale_id         INT UNSIGNED    NULL COMMENT 'Si pago con vale',
    cliente_id      INT UNSIGNED    NULL COMMENT 'Cliente crédito corporativo',
    estado          ENUM('completada','anulada') NOT NULL DEFAULT 'completada',
    notas           TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sucursal_ticket (sucursal_id, numero_ticket),
    INDEX idx_sucursal_fecha (sucursal_id, fecha),
    INDEX idx_turno (turno_id),
    INDEX idx_cliente (cliente_id),
    CONSTRAINT fk_venta_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_venta_turno FOREIGN KEY (turno_id)
        REFERENCES turnos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_venta_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 12. DETALLE DE VENTA - COMBUSTIBLE
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS venta_combustible (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id            INT UNSIGNED    NOT NULL,
    sucursal_id         INT UNSIGNED    NOT NULL,
    manguera_id         INT UNSIGNED    NOT NULL,
    tipo_combustible_id INT UNSIGNED    NOT NULL,
    tanque_id           INT UNSIGNED    NOT NULL,
    galones             DECIMAL(12,4)   NOT NULL,
    precio_unitario     DECIMAL(10,4)   NOT NULL,
    idp_unitario        DECIMAL(10,4)   NOT NULL DEFAULT 0,
    subtotal            DECIMAL(12,2)   NOT NULL,
    idp_total           DECIMAL(12,2)   NOT NULL DEFAULT 0,
    lectura_inicial     DECIMAL(14,4)   NULL COMMENT 'Lectura del dispensador',
    lectura_final       DECIMAL(14,4)   NULL,
    CONSTRAINT fk_vc_venta FOREIGN KEY (venta_id)
        REFERENCES ventas(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_vc_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vc_manguera FOREIGN KEY (manguera_id)
        REFERENCES mangueras(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vc_tipo FOREIGN KEY (tipo_combustible_id)
        REFERENCES tipos_combustible(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vc_tanque FOREIGN KEY (tanque_id)
        REFERENCES tanques(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 13. PRODUCTOS DE PISTA (Aceites, Aditivos, etc.)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED    NOT NULL,
    codigo          VARCHAR(30)     NOT NULL,
    nombre          VARCHAR(200)    NOT NULL,
    categoria       VARCHAR(100)    NULL,
    unidad_medida   VARCHAR(20)     NOT NULL DEFAULT 'UNIDAD',
    precio_venta    DECIMAL(10,2)   NOT NULL,
    precio_compra   DECIMAL(10,2)   NOT NULL DEFAULT 0,
    iva_incluido    TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Guatemala: precio con IVA incluido',
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_producto (empresa_id, codigo),
    CONSTRAINT fk_producto_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 14. INVENTARIO DE PRODUCTOS POR SUCURSAL
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventario_productos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id     INT UNSIGNED    NOT NULL,
    producto_id     INT UNSIGNED    NOT NULL,
    stock           DECIMAL(12,4)   NOT NULL DEFAULT 0,
    stock_minimo    DECIMAL(12,4)   NOT NULL DEFAULT 0,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sucursal_producto (sucursal_id, producto_id),
    CONSTRAINT fk_inv_prod_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inv_prod_producto FOREIGN KEY (producto_id)
        REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 15. DETALLE DE VENTA - PRODUCTOS
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS venta_productos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id        INT UNSIGNED    NOT NULL,
    sucursal_id     INT UNSIGNED    NOT NULL,
    producto_id     INT UNSIGNED    NOT NULL,
    cantidad        DECIMAL(10,4)   NOT NULL,
    precio_unitario DECIMAL(10,2)   NOT NULL,
    subtotal        DECIMAL(12,2)   NOT NULL,
    CONSTRAINT fk_vp_venta FOREIGN KEY (venta_id)
        REFERENCES ventas(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_vp_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vp_producto FOREIGN KEY (producto_id)
        REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 16. CLIENTES CORPORATIVOS (Crédito centralizado)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED    NOT NULL,
    nit             VARCHAR(20)     NOT NULL,
    razon_social    VARCHAR(200)    NOT NULL,
    nombre_comercial VARCHAR(200)   NULL,
    direccion       VARCHAR(300)    NULL,
    telefono        VARCHAR(20)     NULL,
    email           VARCHAR(150)    NULL,
    contacto        VARCHAR(150)    NULL,
    limite_credito  DECIMAL(14,2)   NOT NULL DEFAULT 0,
    saldo_actual    DECIMAL(14,2)   NOT NULL DEFAULT 0 COMMENT 'Saldo pendiente de pago',
    dias_credito    SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_nit (empresa_id, nit),
    CONSTRAINT fk_cliente_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 17. VALES DE CRÉDITO CORPORATIVO (centralizados, multi-sucursal)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS vales (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED    NOT NULL,
    cliente_id      INT UNSIGNED    NOT NULL,
    codigo          VARCHAR(50)     NOT NULL COMMENT 'Código único del vale',
    monto_autorizado DECIMAL(12,2)  NOT NULL,
    monto_consumido DECIMAL(12,2)   NOT NULL DEFAULT 0,
    tipo_combustible_id INT UNSIGNED NULL COMMENT 'NULL = cualquier combustible',
    galones_autorizados DECIMAL(12,4) NULL COMMENT 'NULL = sin límite galones',
    galones_consumidos  DECIMAL(12,4) NOT NULL DEFAULT 0,
    placa_vehiculo  VARCHAR(20)     NULL,
    piloto          VARCHAR(100)    NULL,
    fecha_emision   DATE            NOT NULL,
    fecha_vencimiento DATE          NOT NULL,
    estado          ENUM('activo','agotado','vencido','anulado') NOT NULL DEFAULT 'activo',
    sucursal_valida INT UNSIGNED    NULL COMMENT 'NULL = válido en cualquier sucursal',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_vale (empresa_id, codigo),
    INDEX idx_cliente (cliente_id),
    INDEX idx_estado (estado),
    CONSTRAINT fk_vale_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vale_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vale_tipo FOREIGN KEY (tipo_combustible_id)
        REFERENCES tipos_combustible(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_vale_sucursal FOREIGN KEY (sucursal_valida)
        REFERENCES sucursales(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 18. MOVIMIENTOS DE INVENTARIO COMBUSTIBLE (Compras de pipas, ajustes)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS movimientos_combustible (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id         INT UNSIGNED    NOT NULL,
    tanque_id           INT UNSIGNED    NOT NULL,
    tipo_combustible_id INT UNSIGNED    NOT NULL,
    tipo_movimiento     ENUM('compra','venta','ajuste_positivo','ajuste_negativo','traspaso') NOT NULL,
    galones             DECIMAL(12,4)   NOT NULL,
    stock_antes         DECIMAL(12,4)   NOT NULL,
    stock_despues       DECIMAL(12,4)   NOT NULL,
    referencia_id       INT UNSIGNED    NULL COMMENT 'ID de compra o venta según tipo',
    usuario_id          INT UNSIGNED    NOT NULL,
    notas               TEXT            NULL,
    fecha               DATETIME        NOT NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sucursal_tanque_fecha (sucursal_id, tanque_id, fecha),
    CONSTRAINT fk_mov_comb_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mov_comb_tanque FOREIGN KEY (tanque_id)
        REFERENCES tanques(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mov_comb_tipo FOREIGN KEY (tipo_combustible_id)
        REFERENCES tipos_combustible(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mov_comb_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 19. PROVEEDORES
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS proveedores (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED    NOT NULL,
    nit             VARCHAR(20)     NOT NULL,
    razon_social    VARCHAR(200)    NOT NULL,
    nombre_comercial VARCHAR(200)   NULL,
    direccion       VARCHAR(300)    NULL,
    telefono        VARCHAR(20)     NULL,
    email           VARCHAR(150)    NULL,
    contacto        VARCHAR(150)    NULL,
    tipo            ENUM('combustible','productos','servicios','otro') NOT NULL DEFAULT 'combustible',
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_proveedor_nit (empresa_id, nit),
    CONSTRAINT fk_proveedor_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 20. COMPRAS (Facturas de pipas/cisterna)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS compras (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id         INT UNSIGNED    NOT NULL,
    proveedor_id        INT UNSIGNED    NOT NULL,
    numero_factura      VARCHAR(50)     NOT NULL,
    fecha_factura       DATE            NOT NULL,
    fecha_recepcion     DATETIME        NOT NULL,
    subtotal            DECIMAL(14,2)   NOT NULL DEFAULT 0,
    idp_total           DECIMAL(14,2)   NOT NULL DEFAULT 0,
    iva_total           DECIMAL(14,2)   NOT NULL DEFAULT 0,
    total               DECIMAL(14,2)   NOT NULL DEFAULT 0,
    estado              ENUM('pendiente','pagada','anulada') NOT NULL DEFAULT 'pendiente',
    usuario_id          INT UNSIGNED    NOT NULL,
    notas               TEXT            NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sucursal_fecha (sucursal_id, fecha_factura),
    CONSTRAINT fk_compra_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_compra_proveedor FOREIGN KEY (proveedor_id)
        REFERENCES proveedores(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_compra_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 21. DETALLE DE COMPRA
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS compra_detalle (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    compra_id           INT UNSIGNED    NOT NULL,
    tanque_id           INT UNSIGNED    NOT NULL,
    tipo_combustible_id INT UNSIGNED    NOT NULL,
    galones             DECIMAL(12,4)   NOT NULL,
    precio_unitario     DECIMAL(10,4)   NOT NULL,
    idp_unitario        DECIMAL(10,4)   NOT NULL DEFAULT 0,
    subtotal            DECIMAL(14,2)   NOT NULL,
    idp_total           DECIMAL(14,2)   NOT NULL DEFAULT 0,
    CONSTRAINT fk_cd_compra FOREIGN KEY (compra_id)
        REFERENCES compras(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cd_tanque FOREIGN KEY (tanque_id)
        REFERENCES tanques(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cd_tipo FOREIGN KEY (tipo_combustible_id)
        REFERENCES tipos_combustible(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 22. CORTES / LECTURAS DIARIAS (Control Volumétrico)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS cortes_diarios (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id         INT UNSIGNED    NOT NULL,
    tanque_id           INT UNSIGNED    NOT NULL,
    tipo_combustible_id INT UNSIGNED    NOT NULL,
    fecha               DATE            NOT NULL,
    stock_inicial       DECIMAL(12,4)   NOT NULL COMMENT 'Lectura física al inicio del día',
    compras_dia         DECIMAL(12,4)   NOT NULL DEFAULT 0,
    ventas_dia          DECIMAL(12,4)   NOT NULL DEFAULT 0 COMMENT 'Según dispensadores',
    stock_final_teorico DECIMAL(12,4)   NOT NULL DEFAULT 0 COMMENT 'inicial + compras - ventas',
    stock_final_fisico  DECIMAL(12,4)   NOT NULL COMMENT 'Lectura física real',
    variacion           DECIMAL(12,4)   NOT NULL DEFAULT 0 COMMENT 'Físico - Teórico (negativo = merma)',
    porcentaje_variacion DECIMAL(8,4)   NOT NULL DEFAULT 0,
    usuario_id          INT UNSIGNED    NOT NULL COMMENT 'Quien registró el corte',
    notas               TEXT            NULL,
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_sucursal_tanque_fecha (sucursal_id, tanque_id, fecha),
    INDEX idx_fecha (fecha),
    CONSTRAINT fk_corte_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_corte_tanque FOREIGN KEY (tanque_id)
        REFERENCES tanques(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_corte_tipo FOREIGN KEY (tipo_combustible_id)
        REFERENCES tipos_combustible(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_corte_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 23. LECTURAS DE BOMBAS (control por dispensador)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS lecturas_bomba (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sucursal_id     INT UNSIGNED    NOT NULL,
    bomba_id        INT UNSIGNED    NOT NULL,
    manguera_id     INT UNSIGNED    NOT NULL,
    turno_id        INT UNSIGNED    NOT NULL,
    fecha           DATE            NOT NULL,
    lectura_inicio  DECIMAL(14,4)   NOT NULL,
    lectura_fin     DECIMAL(14,4)   NOT NULL,
    galones_despachados DECIMAL(12,4) NOT NULL COMMENT 'fin - inicio',
    usuario_id      INT UNSIGNED    NOT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sucursal_bomba_fecha (sucursal_id, bomba_id, fecha),
    CONSTRAINT fk_lect_sucursal FOREIGN KEY (sucursal_id)
        REFERENCES sucursales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_lect_bomba FOREIGN KEY (bomba_id)
        REFERENCES bombas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_lect_manguera FOREIGN KEY (manguera_id)
        REFERENCES mangueras(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_lect_turno FOREIGN KEY (turno_id)
        REFERENCES turnos(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_lect_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 24. PAGOS DE CLIENTES (abonos a crédito corporativo)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagos_clientes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED    NOT NULL,
    cliente_id      INT UNSIGNED    NOT NULL,
    monto           DECIMAL(14,2)   NOT NULL,
    forma_pago      ENUM('efectivo','transferencia','cheque','deposito') NOT NULL,
    referencia      VARCHAR(100)    NULL,
    fecha           DATETIME        NOT NULL,
    usuario_id      INT UNSIGNED    NOT NULL,
    notas           TEXT            NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cliente_fecha (cliente_id, fecha),
    CONSTRAINT fk_pago_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pago_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 25. TOKENS DE REFRESCO (para JWT)
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED    NOT NULL,
    token_hash      VARCHAR(255)    NOT NULL,
    expires_at      DATETIME        NOT NULL,
    revoked         TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_hash (token_hash),
    INDEX idx_usuario (usuario_id),
    CONSTRAINT fk_rt_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
