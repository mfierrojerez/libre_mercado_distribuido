-- Ajusta estas dos líneas según la sucursal a inicializar:
-- Norte  : CREATE DATABASE IF NOT EXISTS db_norte;  USE db_norte;
-- Sur    : CREATE DATABASE IF NOT EXISTS db_sur;    USE db_sur;
-- Centro : CREATE DATABASE IF NOT EXISTS db_centro; USE db_centro;

CREATE DATABASE IF NOT EXISTS db_norte;
USE db_norte;

-- === MÓDULO DE INVENTARIO Y RESERVAS (LOCAL) ===
CREATE TABLE stock (
    id CHAR(36) PRIMARY KEY,
    sucursal_id CHAR(36) NOT NULL,
    producto_id CHAR(36) NOT NULL,
    cantidad_real INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_stock_cantidad_real CHECK (cantidad_real >= 0),
    UNIQUE (sucursal_id, producto_id)
);

CREATE TABLE reservas_temporales_stock (
    id CHAR(36) PRIMARY KEY,
    producto_id CHAR(36) NOT NULL,
    sucursal_id CHAR(36) NOT NULL,
    cantidad INT NOT NULL,
    carrito_item_id CHAR(36),
    token_pago VARCHAR(100),
    expira_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_reserva_cantidad CHECK (cantidad > 0)
);

-- === MÓDULO DE PEDIDOS Y VENTAS (LOCAL) ===
-- Cada nodo guarda solo los pedidos que debe preparar.
CREATE TABLE pedidos (
    id CHAR(36) PRIMARY KEY,
    cliente_id CHAR(36) NOT NULL,
    numero_orden VARCHAR(50) UNIQUE NOT NULL,
    sucursal_origen_id CHAR(36) NOT NULL,
    direccion_despacho_id CHAR(36),
    tipo_entrega VARCHAR(50) NOT NULL,
    estado_pedido VARCHAR(50) NOT NULL,
    total_productos INT NOT NULL DEFAULT 0,
    total_despacho INT NOT NULL DEFAULT 0,
    total_pagado INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE detalle_pedidos (
    id CHAR(36) PRIMARY KEY,
    pedido_id CHAR(36) NOT NULL,
    producto_id CHAR(36) NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario_pagado INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_detalle_precio CHECK (precio_unitario_pagado >= 0)
);

-- === ÍNDICES OPERATIVOS ===
CREATE INDEX idx_stock_producto ON stock (producto_id);
CREATE INDEX idx_stock_sucursal ON stock (sucursal_id);
CREATE INDEX idx_reservas_expira_at ON reservas_temporales_stock (expira_at);
CREATE INDEX idx_pedidos_cliente ON pedidos (cliente_id);
CREATE INDEX idx_pedidos_estado ON pedidos (estado_pedido);
CREATE INDEX idx_pedidos_sucursal ON pedidos (sucursal_origen_id);
CREATE INDEX idx_detalle_pedidos_producto ON detalle_pedidos (producto_id);
