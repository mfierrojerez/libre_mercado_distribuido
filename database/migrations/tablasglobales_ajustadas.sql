CREATE DATABASE IF NOT EXISTS db_matriz;
USE db_matriz;

-- === MÓDULO DE GEOGRAFÍA Y SUCURSALES ===
CREATE TABLE regiones (
    id CHAR(36) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

CREATE TABLE ciudades (
    id CHAR(36) PRIMARY KEY,
    region_id CHAR(36) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    CONSTRAINT fk_ciudad_region FOREIGN KEY (region_id) REFERENCES regiones(id)
);

CREATE TABLE sucursales (
    id CHAR(36) PRIMARY KEY,
    ciudad_id CHAR(36) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    codigo_nodo VARCHAR(20) NOT NULL UNIQUE,
    es_bodega_central BOOLEAN DEFAULT FALSE,
    activa BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_sucursal_ciudad FOREIGN KEY (ciudad_id) REFERENCES ciudades(id)
);

-- === MÓDULO DE USUARIOS Y CLIENTES ===
CREATE TABLE usuarios (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clientes (
    id CHAR(36) PRIMARY KEY,
    usuario_id CHAR(36) UNIQUE NOT NULL,
    rut VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    CONSTRAINT fk_cliente_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE direcciones_despacho (
    id CHAR(36) PRIMARY KEY,
    cliente_id CHAR(36) NOT NULL,
    ciudad_id CHAR(36) NOT NULL,
    calle VARCHAR(150) NOT NULL,
    numero VARCHAR(20) NOT NULL,
    depto_block VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dir_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT fk_dir_ciudad FOREIGN KEY (ciudad_id) REFERENCES ciudades(id)
);

-- === MÓDULO DE CATÁLOGO Y PRECIOS ===
CREATE TABLE productos (
    id CHAR(36) PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    peso_gramos INT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE precios_sucursal (
    id CHAR(36) PRIMARY KEY,
    producto_id CHAR(36) NOT NULL,
    sucursal_id CHAR(36) NOT NULL,
    precio_efectivo INT NOT NULL,
    precio_tarjeta INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_precio_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
    CONSTRAINT fk_precio_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id),
    UNIQUE (producto_id, sucursal_id)
);

-- === CARRITO GLOBAL / AGREGADOR ===
-- El carrito vive en matriz para permitir una sola sesión y múltiples sucursales.
CREATE TABLE carritos (
    id CHAR(36) PRIMARY KEY,
    session_token VARCHAR(100) UNIQUE,
    cliente_id CHAR(36) UNIQUE,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_carrito_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE carrito_items (
    id CHAR(36) PRIMARY KEY,
    carrito_id CHAR(36) NOT NULL,
    producto_id CHAR(36) NOT NULL,
    sucursal_id CHAR(36) NOT NULL,
    cantidad INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_carrito_item_carrito FOREIGN KEY (carrito_id) REFERENCES carritos(id) ON DELETE CASCADE,
    CONSTRAINT fk_carrito_item_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
    CONSTRAINT fk_carrito_item_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id),
    CONSTRAINT chk_carrito_item_cantidad CHECK (cantidad > 0),
    UNIQUE (carrito_id, producto_id, sucursal_id)
);

-- === MÓDULO DE ABASTECIMIENTO ===
CREATE TABLE proveedores (
    id CHAR(36) PRIMARY KEY,
    rut VARCHAR(20) UNIQUE NOT NULL,
    razon_social VARCHAR(150) NOT NULL
);

CREATE TABLE ordenes_compra (
    id CHAR(36) PRIMARY KEY,
    proveedor_id CHAR(36) NOT NULL,
    sucursal_destino_id CHAR(36) NOT NULL,
    fecha_ingreso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_oc_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    CONSTRAINT fk_oc_sucursal FOREIGN KEY (sucursal_destino_id) REFERENCES sucursales(id)
);

CREATE TABLE detalle_compras (
    id CHAR(36) PRIMARY KEY,
    orden_compra_id CHAR(36) NOT NULL,
    producto_id CHAR(36) NOT NULL,
    cantidad INT NOT NULL,
    costo_unitario INT NOT NULL,
    CONSTRAINT fk_detcompra_oc FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra(id) ON DELETE CASCADE,
    CONSTRAINT fk_detcompra_prod FOREIGN KEY (producto_id) REFERENCES productos(id)
);
