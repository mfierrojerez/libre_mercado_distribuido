DELIMITER //

CREATE PROCEDURE sp_realizar_compra(
    IN p_pedido_id VARCHAR(36),
    IN p_cliente_id VARCHAR(36),
    IN p_numero_orden VARCHAR(50),
    IN p_sucursal_origen_id VARCHAR(36),
    IN p_direccion_despacho_id VARCHAR(36),
    IN p_tipo_entrega VARCHAR(50),
    IN p_estado_pedido VARCHAR(50),
    IN p_total_productos DECIMAL(10,2),
    IN p_total_despacho DECIMAL(10,2),
    IN p_total_pagado DECIMAL(10,2),
    
    IN p_detalle_id VARCHAR(36),
    IN p_producto_id VARCHAR(36),
    IN p_cantidad INT,
    IN p_precio_unitario_pagado DECIMAL(10,2),
    
    IN p_carrito_item_id VARCHAR(36),
    IN p_nodo_offline TINYINT
)
BEGIN
    -- Manejador de errores para asegurar el ROLLBACK si algo falla en la matriz
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- 1. Insertar el pedido en la matriz. Usamos IGNORE por si el procedimiento 
    -- se llama iterativamente para múltiples productos del mismo pedido.
    INSERT IGNORE INTO pedidos (
        id, cliente_id, numero_orden, sucursal_origen_id, direccion_despacho_id, 
        tipo_entrega, estado_pedido, total_productos, total_despacho, total_pagado
    ) VALUES (
        p_pedido_id, p_cliente_id, p_numero_orden, p_sucursal_origen_id, p_direccion_despacho_id, 
        p_tipo_entrega, p_estado_pedido, p_total_productos, p_total_despacho, p_total_pagado
    );

    -- 2. Insertar el detalle del pedido
    INSERT INTO detalle_pedidos (
        id, pedido_id, producto_id, cantidad, precio_unitario_pagado
    ) VALUES (
        p_detalle_id, p_pedido_id, p_producto_id, p_cantidad, p_precio_unitario_pagado
    );

    -- 3. Validar contingencia Teorema CAP (AP). Si el nodo falló, insertar intención
    IF p_nodo_offline = 1 THEN
        INSERT INTO ventas_pendientes_sincronizacion (
            pedido_id, producto_id, sucursal_id, cantidad, precio_unitario_pagado
        ) VALUES (
            p_pedido_id, p_producto_id, p_sucursal_origen_id, p_cantidad, p_precio_unitario_pagado
        );
    END IF;

    -- 4. Eliminar el ítem del carrito global
    IF p_carrito_item_id IS NOT NULL AND p_carrito_item_id != '' THEN
        DELETE FROM carrito_items WHERE id = p_carrito_item_id;
    END IF;

    COMMIT;
END //

DELIMITER ;
