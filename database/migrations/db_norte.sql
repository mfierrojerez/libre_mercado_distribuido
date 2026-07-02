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

CREATE TABLE IF NOT EXISTS ventas_huerfanas_matriz (
    id CHAR(36) PRIMARY KEY,
    pedido_id CHAR(36) NOT NULL,
    usuario_id CHAR(36) NOT NULL,
    producto_id CHAR(36) NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario INT NOT NULL,
    total INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

-- ============================================================
-- STOCK DE PRUEBA PARA NODO NORTE
-- Sucursal esperada: suc-norte--------0000000000000001
-- ============================================================
INSERT INTO stock (id, sucursal_id, producto_id, cantidad_real) VALUES
('stk-nor-esp32----0000000000000001', 'suc-norte--------0000000000000001', 'prod-esp32-------0000000000000001', 8),
('stk-nor-dht22----0000000000000002', 'suc-norte--------0000000000000001', 'prod-dht22-------0000000000000002', 25),
('stk-nor-relay----0000000000000003', 'suc-norte--------0000000000000001', 'prod-relay2------0000000000000003', 14),
('stk-nor-servo----0000000000000004', 'suc-norte--------0000000000000001', 'prod-servo-------0000000000000004', 20),
('stk-nor-mq2------0000000000000005', 'suc-norte--------0000000000000001', 'prod-mq2---------0000000000000005', 11),
('stk-nor-uno------0000000000000006', 'suc-norte--------0000000000000001', 'prod-arduino-----0000000000000006', 6);

-- Reserva temporal de ejemplo
INSERT INTO reservas_temporales_stock (id, producto_id, sucursal_id, cantidad, carrito_item_id, token_pago, expira_at) VALUES
('res-nor-1--------0000000000000001', 'prod-dht22-------0000000000000002', 'suc-norte--------0000000000000001', 2, 'cit-eduardo-2----0000000000000002', 'tok-demo-norte-001', DATE_ADD(NOW(), INTERVAL 30 MINUTE));

-- Pedido local de ejemplo
INSERT INTO pedidos (id, cliente_id, numero_orden, sucursal_origen_id, direccion_despacho_id, tipo_entrega, estado_pedido, total_productos, total_despacho, total_pagado) VALUES
('ped-nor-1--------0000000000000001', 'cli-eduardo------0000000000000001', 'ORD-NORTE001', 'suc-norte--------0000000000000001', 'dir-eduardo-2----0000000000000002', 'despacho_domicilio', 'pendiente', 8400, 3990, 12390);

INSERT INTO detalle_pedidos (id, pedido_id, producto_id, cantidad, precio_unitario_pagado) VALUES
('dp-nor-1---------0000000000000001', 'ped-nor-1--------0000000000000001', 'prod-dht22-------0000000000000002', 2, 4200);

INSERT INTO stock (id, sucursal_id, producto_id, cantidad_real) VALUES
('stk-nor-bme280---0000000000000101', 'suc-norte--------0000000000000001', 'prod-bme280------0000000000000007', 12),
('stk-nor-ds18b20--0000000000000102', 'suc-norte--------0000000000000001', 'prod-ds18b20-----0000000000000008', 18),
('stk-nor-hcsr04---0000000000000103', 'suc-norte--------0000000000000001', 'prod-hcsr04------0000000000000009', 14),
('stk-nor-pir------0000000000000104', 'suc-norte--------0000000000000001', 'prod-pir---------0000000000000010', 16),
('stk-nor-ds3231---0000000000000105', 'suc-norte--------0000000000000001', 'prod-ds3231------0000000000000011', 9),
('stk-nor-microsd--0000000000000106', 'suc-norte--------0000000000000001', 'prod-microsd-----0000000000000012', 7),
('stk-nor-5v2a-----0000000000000107', 'suc-norte--------0000000000000001', 'prod-5v2a--------0000000000000013', 6),
('stk-nor-12v3a----0000000000000108', 'suc-norte--------0000000000000001', 'prod-12v3a-------0000000000000014', 5),
('stk-nor-protobo--0000000000000109', 'suc-norte--------0000000000000001', 'prod-protoboard--0000000000000015', 20),
('stk-nor-dupontfh-0000000000000110', 'suc-norte--------0000000000000001', 'prod-dupont-fh---0000000000000016', 30),
('stk-nor-dupontmm-0000000000000111', 'suc-norte--------0000000000000001', 'prod-dupont-mm---0000000000000017', 25),
('stk-nor-node8266-0000000000000112', 'suc-norte--------0000000000000001', 'prod-node8266----0000000000000018', 11),
('stk-nor-esp32cam-0000000000000113', 'suc-norte--------0000000000000001', 'prod-esp32cam----0000000000000019', 8),
('stk-nor-relay4---0000000000000114', 'suc-norte--------0000000000000001', 'prod-relay4------0000000000000020', 13),
('stk-nor-humsoil--0000000000000115', 'suc-norte--------0000000000000001', 'prod-humsoil-----0000000000000021', 10),
('stk-nor-oled096--0000000000000116', 'suc-norte--------0000000000000001', 'prod-oled096-----0000000000000022', 9),
('stk-nor-lcd162---0000000000000117', 'suc-norte--------0000000000000001', 'prod-lcd162------0000000000000023', 6),
('stk-nor-lm2596---0000000000000118', 'suc-norte--------0000000000000001', 'prod-lm2596------0000000000000024', 12),
('stk-nor-l298n----0000000000000119', 'suc-norte--------0000000000000001', 'prod-l298n-------0000000000000025', 7),
('stk-nor-sg90-----0000000000000120', 'suc-norte--------0000000000000001', 'prod-sg90--------0000000000000026', 15),
('stk-nor-mq135---0000000000000121', 'suc-norte--------0000000000000001', 'prod-mq135-------0000000000000027', 8),
('stk-nor-bh1750---0000000000000122', 'suc-norte--------0000000000000001', 'prod-bh1750------0000000000000028', 9),
('stk-nor-mpu6050--0000000000000123', 'suc-norte--------0000000000000001', 'prod-mpu6050-----0000000000000029', 10),
('stk-nor-keypad4x40000000000000124', 'suc-norte--------0000000000000001', 'prod-keypad4x4---0000000000000030', 11),
('stk-nor-rc522----0000000000000125', 'suc-norte--------0000000000000001', 'prod-rc522-------0000000000000031', 6),
('stk-nor-fan5v----0000000000000126', 'suc-norte--------0000000000000001', 'prod-fan5v-------0000000000000032', 14),
('stk-nor-hc05-----0000000000000127', 'suc-norte--------0000000000000001', 'prod-hc05--------0000000000000033', 8),
('stk-nor-reskit---0000000000000128', 'suc-norte--------0000000000000001', 'prod-reskit------0000000000000034', 20),
('stk-nor-capkit---0000000000000129', 'suc-norte--------0000000000000001', 'prod-capkit------0000000000000035', 18),
('stk-nor-transkit-0000000000000130', 'suc-norte--------0000000000000001', 'prod-transkit----0000000000000036', 12),
('stk-nor-pushkit--0000000000000131', 'suc-norte--------0000000000000001', 'prod-pushkit-----0000000000000037', 22),
('stk-nor-7seg-----0000000000000132', 'suc-norte--------0000000000000001', 'prod-7seg--------0000000000000038', 19),
('stk-nor-buzzer---0000000000000133', 'suc-norte--------0000000000000001', 'prod-buzzer------0000000000000039', 24),
('stk-nor-flame----0000000000000134', 'suc-norte--------0000000000000001', 'prod-flame-------0000000000000040', 7),
('stk-nor-waterlvl-0000000000000135', 'suc-norte--------0000000000000001', 'prod-waterlvl----0000000000000041', 10),
('stk-nor-joystick-0000000000000136', 'suc-norte--------0000000000000001', 'prod-joystick----0000000000000042', 6),
('stk-nor-acs712---0000000000000137', 'suc-norte--------0000000000000001', 'prod-acs712------0000000000000043', 8),
('stk-nor-ntc10k---0000000000000138', 'suc-norte--------0000000000000001', 'prod-ntc10k------0000000000000044', 16),
('stk-nor-tp4056---0000000000000139', 'suc-norte--------0000000000000001', 'prod-tp4056------0000000000000045', 13);