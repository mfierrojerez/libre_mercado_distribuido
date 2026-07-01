DELIMITER //

CREATE PROCEDURE sp_actualizar_stock(
    IN p_producto_id VARCHAR(36),
    IN p_sucursal_id VARCHAR(36),
    IN p_cantidad INT,
    OUT p_success TINYINT
)
BEGIN
    DECLARE v_stock_actual INT;
    
    -- Manejador de errores para asegurar ACID (Rollback en caso de error)
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = 0;
    END;

    SET p_success = 0;

    START TRANSACTION;

    -- SELECT ... FOR UPDATE bloquea la fila previniendo lecturas fantasmas
    -- y condiciones de carrera si varios compran al mismo tiempo.
    SELECT cantidad_real INTO v_stock_actual
    FROM stock 
    WHERE producto_id = p_producto_id 
      AND sucursal_id = p_sucursal_id 
    FOR UPDATE;

    -- Verificamos que haya suficiente stock
    IF v_stock_actual >= p_cantidad THEN
        -- Si hay stock, actualizamos restando la cantidad
        UPDATE stock
        SET cantidad_real = cantidad_real - p_cantidad
        WHERE producto_id = p_producto_id 
          AND sucursal_id = p_sucursal_id;
        
        SET p_success = 1;
        COMMIT;
    ELSE
        -- Si no hay stock suficiente
        SET p_success = 0;
        ROLLBACK;
    END IF;
END //

CREATE PROCEDURE sp_reconstruir_stock(
    IN p_producto_id VARCHAR(36),
    IN p_cantidad INT
)
BEGIN
    -- Manejador de errores para asegurar ACID
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;

    -- Deducción forzada sin validación porque la compra ya fue procesada en la matriz
    UPDATE stock 
    SET cantidad_real = cantidad_real - p_cantidad 
    WHERE producto_id = p_producto_id;

    COMMIT;
END //

DELIMITER ;
