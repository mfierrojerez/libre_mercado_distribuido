CREATE DATABASE IF NOT EXISTS db_centro;
USE db_centro;

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


DELETE FROM detalle_pedidos;
DELETE FROM pedidos;
DELETE FROM reservas_temporales_stock;
DELETE FROM stock;

INSERT INTO stock (id, sucursal_id, producto_id, cantidad_real) VALUES
('stk-cen-esp32----0000000000000301', 'suc-centro-------0000000000000003', 'prod-esp32-------0000000000000001', 16),
('stk-cen-dht22----0000000000000302', 'suc-centro-------0000000000000003', 'prod-dht22-------0000000000000002', 12),
('stk-cen-relay----0000000000000303', 'suc-centro-------0000000000000003', 'prod-relay2------0000000000000003', 6),
('stk-cen-servo----0000000000000304', 'suc-centro-------0000000000000003', 'prod-servo-------0000000000000004', 14),
('stk-cen-mq2------0000000000000305', 'suc-centro-------0000000000000003', 'prod-mq2---------0000000000000005', 3),
('stk-cen-uno------0000000000000306', 'suc-centro-------0000000000000003', 'prod-arduino-----0000000000000006', 10);

INSERT INTO reservas_temporales_stock (id, producto_id, sucursal_id, cantidad, carrito_item_id, token_pago, expira_at) VALUES
('res-cen-1--------0000000000000301', 'prod-esp32-------0000000000000001', 'suc-centro-------0000000000000003', 1, 'cit-demo-cen-1---0000000000000001', 'tok-demo-centro-301', DATE_ADD(NOW(), INTERVAL 15 MINUTE)),
('res-cen-2--------0000000000000302', 'prod-mq2---------0000000000000005', 'suc-centro-------0000000000000003', 2, 'cit-demo-cen-2---0000000000000002', 'tok-demo-centro-302', DATE_ADD(NOW(), INTERVAL 50 MINUTE));

INSERT INTO pedidos (id, cliente_id, numero_orden, sucursal_origen_id, direccion_despacho_id, tipo_entrega, estado_pedido, total_productos, total_despacho, total_pagado) VALUES
('ped-cen-1--------0000000000000301', 'cli-eduardo------0000000000000001', 'ORD-CENTRO301', 'suc-centro-------0000000000000003', NULL, 'retiro_tienda', 'entregado', 8700, 0, 8700),
('ped-cen-2--------0000000000000302', 'cli-maria--------0000000000000002', 'ORD-CENTRO302', 'suc-centro-------0000000000000003', 'dir-maria-1------0000000000000003', 'despacho_domicilio', 'en_camino', 12600, 3990, 16590);

INSERT INTO detalle_pedidos (id, pedido_id, producto_id, cantidad, precio_unitario_pagado) VALUES
('dp-cen-1---------0000000000000301', 'ped-cen-1--------0000000000000301', 'prod-esp32-------0000000000000001', 1, 8700),
('dp-cen-2---------0000000000000302', 'ped-cen-2--------0000000000000302', 'prod-dht22-------0000000000000002', 3, 4200);

INSERT INTO stock (id, sucursal_id, producto_id, cantidad_real) VALUES
('stk-cen-bme280---0000000000000301', 'suc-centro-------0000000000000003', 'prod-bme280------0000000000000007', 10),
('stk-cen-ds18b20--0000000000000302', 'suc-centro-------0000000000000003', 'prod-ds18b20-----0000000000000008', 15),
('stk-cen-hcsr04---0000000000000303', 'suc-centro-------0000000000000003', 'prod-hcsr04------0000000000000009', 13),
('stk-cen-pir------0000000000000304', 'suc-centro-------0000000000000003', 'prod-pir---------0000000000000010', 12),
('stk-cen-ds3231---0000000000000305', 'suc-centro-------0000000000000003', 'prod-ds3231------0000000000000011', 7),
('stk-cen-microsd--0000000000000306', 'suc-centro-------0000000000000003', 'prod-microsd-----0000000000000012', 5),
('stk-cen-5v2a-----0000000000000307', 'suc-centro-------0000000000000003', 'prod-5v2a--------0000000000000013', 4),
('stk-cen-12v3a----0000000000000308', 'suc-centro-------0000000000000003', 'prod-12v3a-------0000000000000014', 3),
('stk-cen-protobo--0000000000000309', 'suc-centro-------0000000000000003', 'prod-protoboard--0000000000000015', 17),
('stk-cen-dupontfh-0000000000000310', 'suc-centro-------0000000000000003', 'prod-dupont-fh---0000000000000016', 26),
('stk-cen-dupontmm-0000000000000311', 'suc-centro-------0000000000000003', 'prod-dupont-mm---0000000000000017', 21),
('stk-cen-node8266-0000000000000312', 'suc-centro-------0000000000000003', 'prod-node8266----0000000000000018', 9),
('stk-cen-esp32cam-0000000000000313', 'suc-centro-------0000000000000003', 'prod-esp32cam----0000000000000019', 6),
('stk-cen-relay4---0000000000000314', 'suc-centro-------0000000000000003', 'prod-relay4------0000000000000020', 10),
('stk-cen-humsoil--0000000000000315', 'suc-centro-------0000000000000003', 'prod-humsoil-----0000000000000021', 8),
('stk-cen-oled096--0000000000000316', 'suc-centro-------0000000000000003', 'prod-oled096-----0000000000000022', 7),
('stk-cen-lcd162---0000000000000317', 'suc-centro-------0000000000000003', 'prod-lcd162------0000000000000023', 4),
('stk-cen-lm2596---0000000000000318', 'suc-centro-------0000000000000003', 'prod-lm2596------0000000000000024', 9),
('stk-cen-l298n----0000000000000319', 'suc-centro-------0000000000000003', 'prod-l298n-------0000000000000025', 5),
('stk-cen-sg90-----0000000000000320', 'suc-centro-------0000000000000003', 'prod-sg90--------0000000000000026', 11),
('stk-cen-mq135---0000000000000321', 'suc-centro-------0000000000000003', 'prod-mq135-------0000000000000027', 6),
('stk-cen-bh1750---0000000000000322', 'suc-centro-------0000000000000003', 'prod-bh1750------0000000000000028', 7),
('stk-cen-mpu6050--0000000000000323', 'suc-centro-------0000000000000003', 'prod-mpu6050-----0000000000000029', 8),
('stk-cen-keypad4x40000000000000324', 'suc-centro-------0000000000000003', 'prod-keypad4x4---0000000000000030', 9),
('stk-cen-rc522----0000000000000325', 'suc-centro-------0000000000000003', 'prod-rc522-------0000000000000031', 4),
('stk-cen-fan5v----0000000000000326', 'suc-centro-------0000000000000003', 'prod-fan5v-------0000000000000032', 11),
('stk-cen-hc05-----0000000000000327', 'suc-centro-------0000000000000003', 'prod-hc05--------0000000000000033', 6),
('stk-cen-reskit---0000000000000328', 'suc-centro-------0000000000000003', 'prod-reskit------0000000000000034', 17),
('stk-cen-capkit---0000000000000329', 'suc-centro-------0000000000000003', 'prod-capkit------0000000000000035', 15),
('stk-cen-transkit-0000000000000330', 'suc-centro-------0000000000000003', 'prod-transkit----0000000000000036', 9),
('stk-cen-pushkit--0000000000000331', 'suc-centro-------0000000000000003', 'prod-pushkit-----0000000000000037', 19),
('stk-cen-7seg-----0000000000000332', 'suc-centro-------0000000000000003', 'prod-7seg--------0000000000000038', 16),
('stk-cen-buzzer---0000000000000333', 'suc-centro-------0000000000000003', 'prod-buzzer------0000000000000039', 21),
('stk-cen-flame----0000000000000334', 'suc-centro-------0000000000000003', 'prod-flame-------0000000000000040', 5),
('stk-cen-waterlvl-0000000000000335', 'suc-centro-------0000000000000003', 'prod-waterlvl----0000000000000041', 8),
('stk-cen-joystick-0000000000000336', 'suc-centro-------0000000000000003', 'prod-joystick----0000000000000042', 4),
('stk-cen-acs712---0000000000000337', 'suc-centro-------0000000000000003', 'prod-acs712------0000000000000043', 6),
('stk-cen-ntc10k---0000000000000338', 'suc-centro-------0000000000000003', 'prod-ntc10k------0000000000000044', 13),
('stk-cen-tp4056---0000000000000339', 'suc-centro-------0000000000000003', 'prod-tp4056------0000000000000045', 10);