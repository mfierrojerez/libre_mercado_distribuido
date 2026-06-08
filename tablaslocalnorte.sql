-- ¡ATENCIÓN! Cambia estas dos líneas según la sucursal:
-- Para Norte (3307): CREATE DATABASE IF NOT EXISTS db_norte; USE db_norte;
-- Para Sur (3308):   CREATE DATABASE IF NOT EXISTS db_sur; USE db_sur;
-- Para Centro (3309): CREATE DATABASE IF NOT EXISTS db_centro; USE db_centro;

CREATE DATABASE IF NOT EXISTS db_centro;
USE db_centro; 

-- === MÓDULO DE INVENTARIO Y RESERVAS ===
CREATE TABLE stock (
    id CHAR(36) PRIMARY KEY,
    sucursal_id CHAR(36) NOT NULL, 
    producto_id CHAR(36) NOT NULL, 
    cantidad_real INT DEFAULT 0,
    UNIQUE (sucursal_id, producto_id)
);

CREATE TABLE reservas_temporales_stock (
    id CHAR(36) PRIMARY KEY,
    producto_id CHAR(36) NOT NULL, 
    sucursal_id CHAR(36) NOT NULL, 
    cantidad INT NOT NULL,
    token_pago VARCHAR(100),
    expira_at TIMESTAMP
);

-- === MÓDULO DE CARRITO DE COMPRAS ===
CREATE TABLE carritos (
    id CHAR(36) PRIMARY KEY,
    session_token VARCHAR(100) UNIQUE,
    cliente_id CHAR(36), 
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE carrito_items (
    id CHAR(36) PRIMARY KEY,
    carrito_id CHAR(36) NOT NULL,
    producto_id CHAR(36) NOT NULL, 
    sucursal_id CHAR(36) NOT NULL, 
    cantidad INT NOT NULL
);

-- === MÓDULO DE PEDIDOS Y VENTAS ===
CREATE TABLE pedidos (
    id CHAR(36) PRIMARY KEY,
    cliente_id CHAR(36) NOT NULL, 
    numero_orden VARCHAR(50) UNIQUE NOT NULL,
    sucursal_origen_id CHAR(36) NOT NULL, 
    direccion_despacho_id CHAR(36), 
    tipo_entrega VARCHAR(50),
    estado_pedido VARCHAR(50),
    total_productos INT,
    total_despacho INT,
    total_pagado INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE detalle_pedidos (
    id CHAR(36) PRIMARY KEY,
    pedido_id CHAR(36) NOT NULL,
    producto_id CHAR(36) NOT NULL, 
    cantidad INT NOT NULL,
    precio_unitario_pagado INT NOT NULL
);