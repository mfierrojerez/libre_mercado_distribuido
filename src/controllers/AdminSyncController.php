<?php
// src/controllers/AdminSyncController.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/node-config.php';

class AdminSyncController
{
    public function handleSyncReverse(): void
    {
        header('Content-Type: application/json');

        try {
            $matrizPdo = dbMatriz(); 
            // Comprobamos si la matriz ya está arriba
            $matrizPdo->query("SELECT 1"); 

            $recoveredOrders = 0;
            $nodos = dbNodos(); // Array con claves 'norte', 'sur', 'centro'
            
            foreach ($nodos as $nombreNodo => $pdoLocal) {
                try {
                    // 1. Obtener ventas huérfanas de este nodo
                    $stmt = $pdoLocal->query("SELECT * FROM ventas_huerfanas_matriz");
                    $huerfanas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($huerfanas)) {
                        continue;
                    }

                    // 2. Insertar en Matriz
                    $matrizPdo->beginTransaction();

                    foreach ($huerfanas as $row) {
                        // Insertar Cabecera de Pedido (Evitando duplicados si ya existe un pedido_id)
                        $stmtPedido = $matrizPdo->prepare("
                            INSERT IGNORE INTO pedidos 
                            (id, cliente_id, estado_pedido, total_productos, total_pagado, created_at) 
                            VALUES (:id, :cli_id, 'pendiente', :tot_prod, :tot_pagado, :created)
                        ");
                        $stmtPedido->execute([
                            ':id'         => $row['pedido_id'],
                            ':cli_id'     => $row['usuario_id'],
                            ':tot_prod'   => $row['total'],
                            ':tot_pagado' => $row['total'],
                            ':created'    => $row['created_at']
                        ]);

                        // Insertar Detalle de Pedido
                        $stmtDetalle = $matrizPdo->prepare("
                            INSERT IGNORE INTO detalle_pedidos 
                            (id, pedido_id, producto_id, cantidad, precio_unitario_pagado) 
                            VALUES (:id, :ped_id, :prod_id, :cant, :precio)
                        ");
                        $stmtDetalle->execute([
                            ':id'      => $row['id'], // Reutilizamos el ID huérfano como detalle_id
                            ':ped_id'  => $row['pedido_id'],
                            ':prod_id' => $row['producto_id'],
                            ':cant'    => $row['cantidad'],
                            ':precio'  => $row['precio_unitario']
                        ]);
                        
                        $recoveredOrders++;
                    }

                    $matrizPdo->commit();

                    // 3. Borrar registros sincronizados en el nodo local
                    $pdoLocal->query("DELETE FROM ventas_huerfanas_matriz");

                } catch (Exception $e) {
                    if ($matrizPdo->inTransaction()) {
                        $matrizPdo->rollBack();
                    }
                    error_log("Error sincronizando nodo {$nombreNodo}: " . $e->getMessage());
                    // Continúa con el siguiente nodo aunque este haya fallado
                }
            }

            if (ob_get_level() > 0) ob_clean();
            echo json_encode(['success' => true, 'recovered' => $recoveredOrders]);
            exit;

        } catch (PDOException $e) {
            if (ob_get_level() > 0) ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'La base de datos Matriz aún se encuentra Offline.']);
            exit;
        }
    }
}
