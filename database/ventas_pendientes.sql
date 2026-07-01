CREATE TABLE IF NOT EXISTS `ventas_pendientes_sincronizacion` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` VARCHAR(36) NOT NULL,
  `producto_id` VARCHAR(36) NOT NULL,
  `sucursal_id` VARCHAR(36) NOT NULL,
  `cantidad` INT NOT NULL,
  `precio_unitario_pagado` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
