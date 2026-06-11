CREATE DATABASE IF NOT EXISTS db_sur;
USE db_sur;

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
('stk-sur-esp32----0000000000000201', 'suc-sur----------0000000000000002', 'prod-esp32-------0000000000000001', 1),
('stk-sur-dht22----0000000000000202', 'suc-sur----------0000000000000002', 'prod-dht22-------0000000000000002', 8),
('stk-sur-relay----0000000000000203', 'suc-sur----------0000000000000002', 'prod-relay2------0000000000000003', 24),
('stk-sur-servo----0000000000000204', 'suc-sur----------0000000000000002', 'prod-servo-------0000000000000004', 19),
('stk-sur-mq2------0000000000000205', 'suc-sur----------0000000000000002', 'prod-mq2---------0000000000000005', 5),
('stk-sur-uno------0000000000000206', 'suc-sur----------0000000000000002', 'prod-arduino-----0000000000000006', 11);

INSERT INTO reservas_temporales_stock (id, producto_id, sucursal_id, cantidad, carrito_item_id, token_pago, expira_at) VALUES
('res-sur-1--------0000000000000201', 'prod-relay2------0000000000000003', 'suc-sur----------0000000000000002', 2, 'cit-demo-sur-1---0000000000000001', 'tok-demo-sur-201', DATE_ADD(NOW(), INTERVAL 40 MINUTE)),
('res-sur-2--------0000000000000202', 'prod-servo-------0000000000000004', 'suc-sur----------0000000000000002', 1, 'cit-demo-sur-2---0000000000000002', 'tok-demo-sur-202', DATE_ADD(NOW(), INTERVAL 12 MINUTE));

INSERT INTO pedidos (id, cliente_id, numero_orden, sucursal_origen_id, direccion_despacho_id, tipo_entrega, estado_pedido, total_productos, total_despacho, total_pagado) VALUES
('ped-sur-1--------0000000000000201', 'cli-maria--------0000000000000002', 'ORD-SUR201', 'suc-sur----------0000000000000002', NULL, 'retiro_tienda', 'confirmado', 7200, 0, 7200),
('ped-sur-2--------0000000000000202', 'cli-admin--------0000000000009999', 'ORD-SUR202', 'suc-sur----------0000000000000002', NULL, 'retiro_tienda', 'entregado', 10800, 0, 10800);

INSERT INTO detalle_pedidos (id, pedido_id, producto_id, cantidad, precio_unitario_pagado) VALUES
('dp-sur-1---------0000000000000201', 'ped-sur-1--------0000000000000201', 'prod-relay2------0000000000000003', 2, 3600),
('dp-sur-2---------0000000000000202', 'ped-sur-2--------0000000000000202', 'prod-servo-------0000000000000004', 3, 3600);

INSERT INTO stock (id, sucursal_id, producto_id, cantidad_real) VALUES
('stk-sur-bme280---0000000000000201', 'suc-sur----------0000000000000002', 'prod-bme280------0000000000000007', 9),
('stk-sur-ds18b20--0000000000000202', 'suc-sur----------0000000000000002', 'prod-ds18b20-----0000000000000008', 13),
('stk-sur-hcsr04---0000000000000203', 'suc-sur----------0000000000000002', 'prod-hcsr04------0000000000000009', 11),
('stk-sur-pir------0000000000000204', 'suc-sur----------0000000000000002', 'prod-pir---------0000000000000010', 14),
('stk-sur-ds3231---0000000000000205', 'suc-sur----------0000000000000002', 'prod-ds3231------0000000000000011', 8),
('stk-sur-microsd--0000000000000206', 'suc-sur----------0000000000000002', 'prod-microsd-----0000000000000012', 6),
('stk-sur-5v2a-----0000000000000207', 'suc-sur----------0000000000000002', 'prod-5v2a--------0000000000000013', 5),
('stk-sur-12v3a----0000000000000208', 'suc-sur----------0000000000000002', 'prod-12v3a-------0000000000000014', 4),
('stk-sur-protobo--0000000000000209', 'suc-sur----------0000000000000002', 'prod-protoboard--0000000000000015', 18),
('stk-sur-dupontfh-0000000000000210', 'suc-sur----------0000000000000002', 'prod-dupont-fh---0000000000000016', 28),
('stk-sur-dupontmm-0000000000000211', 'suc-sur----------0000000000000002', 'prod-dupont-mm---0000000000000017', 22),
('stk-sur-node8266-0000000000000212', 'suc-sur----------0000000000000002', 'prod-node8266----0000000000000018', 10),
('stk-sur-esp32cam-0000000000000213', 'suc-sur----------0000000000000002', 'prod-esp32cam----0000000000000019', 7),
('stk-sur-relay4---0000000000000214', 'suc-sur----------0000000000000002', 'prod-relay4------0000000000000020', 11),
('stk-sur-humsoil--0000000000000215', 'suc-sur----------0000000000000002', 'prod-humsoil-----0000000000000021', 9),
('stk-sur-oled096--0000000000000216', 'suc-sur----------0000000000000002', 'prod-oled096-----0000000000000022', 8),
('stk-sur-lcd162---0000000000000217', 'suc-sur----------0000000000000002', 'prod-lcd162------0000000000000023', 5),
('stk-sur-lm2596---0000000000000218', 'suc-sur----------0000000000000002', 'prod-lm2596------0000000000000024', 10),
('stk-sur-l298n----0000000000000219', 'suc-sur----------0000000000000002', 'prod-l298n-------0000000000000025', 6),
('stk-sur-sg90-----0000000000000220', 'suc-sur----------0000000000000002', 'prod-sg90--------0000000000000026', 12),
('stk-sur-mq135---0000000000000221', 'suc-sur----------0000000000000002', 'prod-mq135-------0000000000000027', 7),
('stk-sur-bh1750---0000000000000222', 'suc-sur----------0000000000000002', 'prod-bh1750------0000000000000028', 8),
('stk-sur-mpu6050--0000000000000223', 'suc-sur----------0000000000000002', 'prod-mpu6050-----0000000000000029', 9),
('stk-sur-keypad4x40000000000000224', 'suc-sur----------0000000000000002', 'prod-keypad4x4---0000000000000030', 10),
('stk-sur-rc522----0000000000000225', 'suc-sur----------0000000000000002', 'prod-rc522-------0000000000000031', 5),
('stk-sur-fan5v----0000000000000226', 'suc-sur----------0000000000000002', 'prod-fan5v-------0000000000000032', 12),
('stk-sur-hc05-----0000000000000227', 'suc-sur----------0000000000000002', 'prod-hc05--------0000000000000033', 7),
('stk-sur-reskit---0000000000000228', 'suc-sur----------0000000000000002', 'prod-reskit------0000000000000034', 18),
('stk-sur-capkit---0000000000000229', 'suc-sur----------0000000000000002', 'prod-capkit------0000000000000035', 16),
('stk-sur-transkit-0000000000000230', 'suc-sur----------0000000000000002', 'prod-transkit----0000000000000036', 10),
('stk-sur-pushkit--0000000000000231', 'suc-sur----------0000000000000002', 'prod-pushkit-----0000000000000037', 20),
('stk-sur-7seg-----0000000000000232', 'suc-sur----------0000000000000002', 'prod-7seg--------0000000000000038', 17),
('stk-sur-buzzer---0000000000000233', 'suc-sur----------0000000000000002', 'prod-buzzer------0000000000000039', 22),
('stk-sur-flame----0000000000000234', 'suc-sur----------0000000000000002', 'prod-flame-------0000000000000040', 6),
('stk-sur-waterlvl-0000000000000235', 'suc-sur----------0000000000000002', 'prod-waterlvl----0000000000000041', 9),
('stk-sur-joystick-0000000000000236', 'suc-sur----------0000000000000002', 'prod-joystick----0000000000000042', 5),
('stk-sur-acs712---0000000000000237', 'suc-sur----------0000000000000002', 'prod-acs712------0000000000000043', 7),
('stk-sur-ntc10k---0000000000000238', 'suc-sur----------0000000000000002', 'prod-ntc10k------0000000000000044', 14),
('stk-sur-tp4056---0000000000000239', 'suc-sur----------0000000000000002', 'prod-tp4056------0000000000000045', 11);
