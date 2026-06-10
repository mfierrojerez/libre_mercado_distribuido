-- ============================================================
-- DATOS DE PRUEBA - CAPA LOCAL (db_norte, db_sur, db_centro)
-- Compatible con tablaslocal_ajustadas-2.sql
--
-- USO:
-- 1) Cambia el CREATE DATABASE / USE según el nodo.
-- 2) Ajusta SOLO el bloque INSERT de stock para que corresponda a la sucursal del nodo.
-- 3) Los bloques de pedidos de ejemplo son opcionales.
-- ============================================================

CREATE DATABASE IF NOT EXISTS db_norte;
USE db_norte;

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

-- ============================================================
-- PARA USAR EN db_sur, reemplaza cabecera y usa este bloque stock:
-- CREATE DATABASE IF NOT EXISTS db_sur;
-- USE db_sur;
--
-- INSERT INTO stock (id, sucursal_id, producto_id, cantidad_real) VALUES
-- ('stk-sur-esp32----0000000000000001', 'suc-sur----------0000000000000002', 'prod-esp32-------0000000000000001', 10),
-- ('stk-sur-dht22----0000000000000002', 'suc-sur----------0000000000000002', 'prod-dht22-------0000000000000002', 18),
-- ('stk-sur-relay----0000000000000003', 'suc-sur----------0000000000000002', 'prod-relay2------0000000000000003', 16),
-- ('stk-sur-servo----0000000000000004', 'suc-sur----------0000000000000002', 'prod-servo-------0000000000000004', 22),
-- ('stk-sur-mq2------0000000000000005', 'suc-sur----------0000000000000002', 'prod-mq2---------0000000000000005', 9),
-- ('stk-sur-uno------0000000000000006', 'suc-sur----------0000000000000002', 'prod-arduino-----0000000000000006', 7);
--
-- INSERT INTO pedidos (...) VALUES
-- ('ped-sur-1--------0000000000000001', 'cli-maria--------0000000000000002', 'ORD-SUR001', 'suc-sur----------0000000000000002', NULL, 'retiro_tienda', 'confirmado', 3600, 0, 3600);
--
-- INSERT INTO detalle_pedidos (...) VALUES
-- ('dp-sur-1---------0000000000000001', 'ped-sur-1--------0000000000000001', 'prod-relay2------0000000000000003', 1, 3600);

-- ============================================================
-- PARA USAR EN db_centro, reemplaza cabecera y usa este bloque stock:
-- CREATE DATABASE IF NOT EXISTS db_centro;
-- USE db_centro;
--
-- INSERT INTO stock (id, sucursal_id, producto_id, cantidad_real) VALUES
-- ('stk-cen-esp32----0000000000000001', 'suc-centro-------0000000000000003', 'prod-esp32-------0000000000000001', 15),
-- ('stk-cen-dht22----0000000000000002', 'suc-centro-------0000000000000003', 'prod-dht22-------0000000000000002', 30),
-- ('stk-cen-relay----0000000000000003', 'suc-centro-------0000000000000003', 'prod-relay2------0000000000000003', 12),
-- ('stk-cen-servo----0000000000000004', 'suc-centro-------0000000000000003', 'prod-servo-------0000000000000004', 17),
-- ('stk-cen-mq2------0000000000000005', 'suc-centro-------0000000000000003', 'prod-mq2---------0000000000000005', 13),
-- ('stk-cen-uno------0000000000000006', 'suc-centro-------0000000000000003', 'prod-arduino-----0000000000000006', 9);
--
-- INSERT INTO pedidos (...) VALUES
-- ('ped-cen-1--------0000000000000001', 'cli-eduardo------0000000000000001', 'ORD-CENTRO001', 'suc-centro-------0000000000000003', NULL, 'retiro_tienda', 'entregado', 8700, 0, 8700);
--
-- INSERT INTO detalle_pedidos (...) VALUES
-- ('dp-cen-1---------0000000000000001', 'ped-cen-1--------0000000000000001', 'prod-esp32-------0000000000000001', 1, 8700);


-- ///////////////////////////////////
--                SUR
-- ///////////////////////////////////

CREATE DATABASE IF NOT EXISTS db_sur;
USE db_sur;

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

-- ///////////////////////////////////
--              CENTRO
-- ///////////////////////////////////

CREATE DATABASE IF NOT EXISTS db_centro;
USE db_centro;

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
