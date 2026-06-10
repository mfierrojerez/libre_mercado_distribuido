USE db_matriz;

-- ============================================================
-- DATOS DE PRUEBA - CAPA GLOBAL (db_matriz)
-- Compatible con tablasglobales_ajustadas.sql
-- ============================================================

-- Limpieza opcional para re-ejecutar el script
DELETE FROM detalle_compras;
DELETE FROM ordenes_compra;
DELETE FROM proveedores;

DELETE FROM carrito_items;
DELETE FROM carritos;

DELETE FROM precios_sucursal;
DELETE FROM productos;

DELETE FROM direcciones_despacho;
DELETE FROM clientes;
DELETE FROM usuarios;

DELETE FROM sucursales;
DELETE FROM ciudades;
DELETE FROM regiones;

-- === GEOGRAFÍA ===
INSERT INTO regiones (id, nombre) VALUES
('reg-metropolitana-0000000000000001', 'Región Metropolitana'),
('reg-valparaiso----0000000000000002', 'Región de Valparaíso'),
('reg-biobio--------0000000000000003', 'Región del Biobío');

INSERT INTO ciudades (id, region_id, nombre) VALUES
('ciu-santiago------0000000000000001', 'reg-metropolitana-0000000000000001', 'Santiago'),
('ciu-valparaiso----0000000000000002', 'reg-valparaiso----0000000000000002', 'Valparaíso'),
('ciu-concepcion----0000000000000003', 'reg-biobio--------0000000000000003', 'Concepción');

-- === SUCURSALES ===
INSERT INTO sucursales (id, ciudad_id, nombre, codigo_nodo, es_bodega_central, activa) VALUES
('suc-norte--------0000000000000001', 'ciu-valparaiso----0000000000000002', 'Sucursal Norte',  'norte',  FALSE, TRUE),
('suc-sur----------0000000000000002', 'ciu-concepcion----0000000000000003', 'Sucursal Sur',    'sur',    FALSE, TRUE),
('suc-centro-------0000000000000003', 'ciu-santiago------0000000000000001', 'Sucursal Centro', 'centro', TRUE,  TRUE);

-- === USUARIOS / CLIENTES ===
-- password de ejemplo: hash placeholder, reemplazable por uno real si tu login valida password_hash
INSERT INTO usuarios (id, email, password, rol) VALUES
('usr-admin--------0000000000000001', 'admin@sisdist.cl',   '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMN1234567890abcd', 'admin'),
('usr-eduardo------0000000000000002', 'eduardo@test.cl',    '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMN1234567890abce', 'cliente'),
('usr-maria--------0000000000000003', 'maria@test.cl',      '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMN1234567890abcf', 'cliente');

INSERT INTO clientes (id, usuario_id, rut, nombre, apellido, telefono) VALUES
('cli-eduardo------0000000000000001', 'usr-eduardo------0000000000000002', '20.123.456-7', 'Eduardo', 'Cortés', '+56911111111'),
('cli-maria--------0000000000000002', 'usr-maria--------0000000000000003', '18.765.432-1', 'María', 'Pérez', '+56922222222');

INSERT INTO direcciones_despacho (id, cliente_id, ciudad_id, calle, numero, depto_block) VALUES
('dir-eduardo-1----0000000000000001', 'cli-eduardo------0000000000000001', 'ciu-santiago------0000000000000001', 'Av. Providencia', '1234', 'Depto 501'),
('dir-eduardo-2----0000000000000002', 'cli-eduardo------0000000000000001', 'ciu-valparaiso----0000000000000002', 'Calle Prat', '456', NULL),
('dir-maria-1------0000000000000003', 'cli-maria--------0000000000000002', 'ciu-concepcion----0000000000000003', 'Av. O Higgins', '789', 'Casa B');

-- === PRODUCTOS ===
INSERT INTO productos (id, sku, nombre, descripcion, peso_gramos, activo) VALUES
('prod-esp32-------0000000000000001', 'ESP32-DEVKIT-V1', 'ESP32 DevKit V1', 'Microcontrolador WiFi/Bluetooth para proyectos IoT.', 120, TRUE),
('prod-dht22-------0000000000000002', 'DHT22-SENSOR', 'Sensor DHT22', 'Sensor de temperatura y humedad digital.', 40, TRUE),
('prod-relay2------0000000000000003', 'RELAY-2CH-5V', 'Módulo Relay 2 Canales 5V', 'Módulo de relés para automatización y control.', 95, TRUE),
('prod-servo-------0000000000000004', 'SERVO-SG90', 'Servo Motor SG90', 'Servo de 9g para robótica y prototipos.', 55, TRUE),
('prod-mq2---------0000000000000005', 'SENSOR-MQ2', 'Sensor de Gas MQ-2', 'Sensor para detección de humo y gas.', 60, TRUE),
('prod-arduino-----0000000000000006', 'ARDUINO-UNO-R3', 'Arduino Uno R3', 'Placa de desarrollo compatible con Arduino.', 180, TRUE);

-- === PRECIOS POR SUCURSAL ===
INSERT INTO precios_sucursal (id, producto_id, sucursal_id, precio_efectivo, precio_tarjeta) VALUES
('pre-nor-esp32----0000000000000001', 'prod-esp32-------0000000000000001', 'suc-norte--------0000000000000001',  8900,  9500),
('pre-sur-esp32----0000000000000002', 'prod-esp32-------0000000000000001', 'suc-sur----------0000000000000002',  9100,  9700),
('pre-cen-esp32----0000000000000003', 'prod-esp32-------0000000000000001', 'suc-centro-------0000000000000003', 8700,  9300),

('pre-nor-dht22----0000000000000004', 'prod-dht22-------0000000000000002', 'suc-norte--------0000000000000001',  4200,  4600),
('pre-sur-dht22----0000000000000005', 'prod-dht22-------0000000000000002', 'suc-sur----------0000000000000002',  4300,  4700),
('pre-cen-dht22----0000000000000006', 'prod-dht22-------0000000000000002', 'suc-centro-------0000000000000003', 4100,  4500),

('pre-nor-relay----0000000000000007', 'prod-relay2------0000000000000003', 'suc-norte--------0000000000000001',  3500,  3900),
('pre-sur-relay----0000000000000008', 'prod-relay2------0000000000000003', 'suc-sur----------0000000000000002',  3600,  4000),
('pre-cen-relay----0000000000000009', 'prod-relay2------0000000000000003', 'suc-centro-------0000000000000003', 3400,  3800),

('pre-nor-servo----0000000000000010', 'prod-servo-------0000000000000004', 'suc-norte--------0000000000000001',  2800,  3200),
('pre-sur-servo----0000000000000011', 'prod-servo-------0000000000000004', 'suc-sur----------0000000000000002',  2900,  3300),
('pre-cen-servo----0000000000000012', 'prod-servo-------0000000000000004', 'suc-centro-------0000000000000003', 2700,  3100),

('pre-nor-mq2------0000000000000013', 'prod-mq2---------0000000000000005', 'suc-norte--------0000000000000001',  3900,  4300),
('pre-sur-mq2------0000000000000014', 'prod-mq2---------0000000000000005', 'suc-sur----------0000000000000002',  4000,  4400),
('pre-cen-mq2------0000000000000015', 'prod-mq2---------0000000000000005', 'suc-centro-------0000000000000003', 3850,  4250),

('pre-nor-uno------0000000000000016', 'prod-arduino-----0000000000000006', 'suc-norte--------0000000000000001', 10900, 11500),
('pre-sur-uno------0000000000000017', 'prod-arduino-----0000000000000006', 'suc-sur----------0000000000000002', 11100, 11700),
('pre-cen-uno------0000000000000018', 'prod-arduino-----0000000000000006', 'suc-centro-------0000000000000003', 10700, 11300);

-- === CARRITO DE PRUEBA ===
INSERT INTO carritos (id, session_token, cliente_id, estado) VALUES
('car-eduardo------0000000000000001', 'sess-eduardo-demo-001', 'cli-eduardo------0000000000000001', 'activo'),
('car-invitado-----0000000000000002', 'sess-guest-demo-001', NULL, 'activo');

INSERT INTO carrito_items (id, carrito_id, producto_id, sucursal_id, cantidad) VALUES
('cit-eduardo-1----0000000000000001', 'car-eduardo------0000000000000001', 'prod-esp32-------0000000000000001', 'suc-centro-------0000000000000003', 1),
('cit-eduardo-2----0000000000000002', 'car-eduardo------0000000000000001', 'prod-dht22-------0000000000000002', 'suc-norte--------0000000000000001', 2),
('cit-guest-1------0000000000000003', 'car-invitado-----0000000000000002', 'prod-relay2------0000000000000003', 'suc-sur----------0000000000000002', 1);

-- === ABASTECIMIENTO ===
INSERT INTO proveedores (id, rut, razon_social) VALUES
('prov-microtech---0000000000000001', '76.123.456-0', 'MicroTech SpA'),
('prov-automatiza--0000000000000002', '77.987.654-3', 'Automatiza Chile Ltda.');

INSERT INTO ordenes_compra (id, proveedor_id, sucursal_destino_id) VALUES
('oc-norte---------0000000000000001', 'prov-microtech---0000000000000001', 'suc-norte--------0000000000000001'),
('oc-centro--------0000000000000002', 'prov-automatiza--0000000000000002', 'suc-centro-------0000000000000003');

INSERT INTO detalle_compras (id, orden_compra_id, producto_id, cantidad, costo_unitario) VALUES
('doc-norte-1------0000000000000001', 'oc-norte---------0000000000000001', 'prod-dht22-------0000000000000002', 50, 2500),
('doc-centro-1-----0000000000000002', 'oc-centro--------0000000000000002', 'prod-esp32-------0000000000000001', 30, 6200);
