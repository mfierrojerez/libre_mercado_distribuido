<?php
// src/controllers/OrdersController.php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/node-config.php';

class OrdersController
{
    private ?string $clienteId;

    public function __construct()
    {
        $this->clienteId = $_SESSION['cliente_id'] ?? null;
    }

    public function index(string $node = ''): array
    {
        try {
            $pdo = dbMatriz();
            
            if (($_SESSION['rol'] ?? '') !== 'admin') {
                if (!$this->clienteId) {
                    return ['pedidos' => [], 'error' => 'No autenticado'];
                }
                
                // Consultamos directamente a la matriz
                $stmt = $pdo->prepare('
                    SELECT p.* 
                    FROM pedidos p 
                    WHERE p.cliente_id = :cid 
                    ORDER BY p.created_at DESC
                ');
                $stmt->execute([':cid' => $this->clienteId]);
                $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Opcional: Adjuntar los detalles de cada pedido
                foreach ($pedidos as &$pedido) {
                    $stmtDet = $pdo->prepare('
                        SELECT dp.*, prod.nombre 
                        FROM detalle_pedidos dp
                        LEFT JOIN productos prod ON prod.id = dp.producto_id
                        WHERE dp.pedido_id = :pid
                    ');
                    $stmtDet->execute([':pid' => $pedido['id']]);
                    $pedido['items'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
                }
                unset($pedido);

            } else {
                // Admin también consulta de manera centralizada (db_matriz)
                $node = trim(strtolower($node));
                $allowed = ['norte', 'sur', 'centro'];
                if ($node === '' || !in_array($node, $allowed, true)) {
                    $node = strtolower((string) currentNodeType());
                }
                if (!in_array($node, $allowed, true)) {
                    $node = 'norte';
                }

                $stmt = $pdo->prepare('
                    SELECT p.* 
                    FROM pedidos p
                    JOIN sucursales s ON s.id = p.sucursal_origen_id
                    WHERE s.codigo_nodo = :nodo
                    ORDER BY p.created_at DESC
                    LIMIT 100
                ');
                $stmt->execute([':nodo' => $node]);
                $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if ($this->isAjax()) {
                if (ob_get_level() > 0) ob_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'pedidos' => $pedidos]);
                exit;
            }

            return [
                'pedidos' => $pedidos,
                'error' => null,
            ];

        } catch (Throwable $e) {
            error_log('[OrdersController::index] error centralizado: ' . $e->getMessage());
            
            if ($this->isAjax()) {
                if (ob_get_level() > 0) ob_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Error al obtener historial']);
                exit;
            }

            return [
                'pedidos' => [],
                'error' => 'Error al obtener pedidos centralizados',
            ];
        }
    }

    public function show(string $orderId): array
    {
        try {
            $rol = $_SESSION['rol'] ?? 'cliente';
            $pedido = null;
            $pedidoNode = null;

            foreach (dbNodos() as $node => $pdo) {
                $row = ($rol === 'admin')
                    ? queryOne($pdo, 'SELECT * FROM pedidos WHERE id = :id LIMIT 1', [':id' => $orderId])
                    : queryOne(
                        $pdo,
                        'SELECT * FROM pedidos WHERE id = :id AND cliente_id = :cid LIMIT 1',
                        [':id' => $orderId, ':cid' => $this->clienteId]
                    );

                if ($row !== null) {
                    $pedido = $row;
                    $pedidoNode = $node;
                    break;
                }
            }

            if ($pedido === null || $pedidoNode === null) {
                return ['success' => false, 'error' => 'Pedido no encontrado'];
            }

            $pdo = dbSucursal($pedidoNode);

            $items = queryAll(
                $pdo,
                'SELECT dp.producto_id, dp.cantidad, dp.precio_unitario_pagado
                 FROM detalle_pedidos dp
                 WHERE dp.pedido_id = :pid',
                [':pid' => $orderId]
            );

            $items = array_map(function (array $item): array {
                $producto = getProductById((string) $item['producto_id']);

                return array_merge($item, [
                    'nombre'   => $producto['nombre'] ?? 'Producto no disponible',
                    'sku'      => $producto['sku'] ?? null,
                    'subtotal' => ((float) ($item['precio_unitario_pagado'] ?? 0)) * (int) ($item['cantidad'] ?? 0),
                ]);
            }, $items);

            $cliente = queryOne(
                dbMatriz(),
                'SELECT c.nombre, c.apellido, c.rut, u.email
                 FROM clientes c
                 JOIN usuarios u ON u.id = c.usuario_id
                 WHERE c.id = :cid
                 LIMIT 1',
                [':cid' => $pedido['cliente_id']]
            );

            return [
                'success'   => true,
                'pedido'    => array_merge($pedido, ['node' => $pedidoNode]),
                'items'     => $items,
                'cliente'   => $cliente,
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Throwable $e) {
            error_log('[OrdersController::show] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al obtener pedido'];
        }
    }

    private function isAjax(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    public function handleCreateRequest(array $input): void
    {
        $tipoEntrega = (string) ($input['tipo_entrega'] ?? '');
        $direccionId = trim((string) ($input['direccion_despacho_id'] ?? ''));
        $direccionManual = trim((string) ($input['direccion_manual'] ?? ''));

        $resultado = $this->create($tipoEntrega, $direccionId !== '' ? $direccionId : null, $direccionManual);

        if ($this->isAjax()) {
            ob_clean();
            header('Content-Type: application/json');
            if (!empty($resultado['success'])) {
                echo json_encode(['success' => true, 'message' => $resultado['message'] ?? 'Pedido creado correctamente']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $resultado['error'] ?? 'No se pudo crear el pedido']);
            }
            exit;
        }

        if (!empty($resultado['success'])) {
            $_SESSION['flash_success'] = $resultado['message'] ?? 'Pedido creado correctamente';
            header('Location: ' . url('reviews'));
            exit;
        }

        $_SESSION['flash_error'] = $resultado['error'] ?? 'No se pudo crear el pedido';
        header('Location: ' . url('checkout'));
        exit;
    }

    public function create(string $tipoEntrega, ?string $direccionDespachoId = null, string $direccionManual = ''): array
    {
        if ($this->clienteId === null) {
            return ['success' => false, 'error' => 'Debes iniciar sesión'];
        }

        $carrito = getCarrito($_SESSION['cart_token'] ?? null, $this->clienteId);
        if (empty($carrito['items'])) {
            return ['success' => false, 'error' => 'El carrito está vacío'];
        }

        $grupos = [];
        foreach ($carrito['items'] as $item) {
            $grupos[$item['sucursal_id']][] = $item;
        }

        // INYECCIÓN DE DIRECCIÓN ON THE FLY
        if ($tipoEntrega === 'despacho_domicilio' && $direccionManual !== '') {
            try {
                $pdoMatriz = dbMatriz();
                $stmtCiudad = $pdoMatriz->query("SELECT id FROM ciudades LIMIT 1");
                $ciudadId = $stmtCiudad->fetchColumn();

                if ($ciudadId) {
                    $nuevaDireccionId = generateUuid();
                    $stmtIns = $pdoMatriz->prepare('INSERT INTO direcciones_despacho (id, cliente_id, ciudad_id, calle, numero) VALUES (?, ?, ?, ?, ?)');
                    $stmtIns->execute([$nuevaDireccionId, $this->clienteId, $ciudadId, $direccionManual, 'S/N']);
                    $direccionDespachoId = $nuevaDireccionId;
                }
            } catch (Exception $e) {
                error_log("Error insertando dirección on the fly: " . $e->getMessage());
            }
        }

        $pedidosCreados = [];
        $totalGlobal = 0;
        $despachoPendiente = ($tipoEntrega === 'despacho_domicilio') ? 3990 : 0;

        foreach ($grupos as $sucursalId => $itemsSucursal) {
            $sucursal = getBranchById($sucursalId);
            $node = $sucursal['codigo_nodo'] ?? null;
            
            if (!$node) {
                throw new RuntimeException("La sucursal {$sucursalId} no tiene codigo_nodo");
            }

            // Generación de UUIDs antes de cualquier conexión
            $pedidoId = generateUuid();
            $numeroOrden = 'ORD-' . strtoupper(substr($pedidoId, 0, 8));
            $totalDespachoPedido = $despachoPendiente;
            $despachoPendiente = 0;

            // NOTA: Asumimos que los items ya traen el 'precio_unitario' desde el carrito o lo obtienes de una caché local.
            // Si necesitas extraer precios de la DB, deberías hacerlo a través del dbSucursal($node) para no depender de dbMatriz.
            $totalProductos = 0;
            $itemsWithIds = [];

            foreach ($itemsSucursal as $item) {
                // 1. Obtener precio unitario con fallback a base de datos
                $precioUnitario = (int) ($item['precio_unitario'] ?? 0);

                if ($precioUnitario === 0) {
                    try {
                        $pdoPrecio = dbMatriz(); // Usando dbMatriz() que es la función global correcta en el proyecto
                        $stmtPrecio = $pdoPrecio->prepare("SELECT precio_efectivo FROM precios_sucursal WHERE producto_id = ? AND sucursal_id = ?");
                        $stmtPrecio->execute([$item['producto_id'], $sucursalId]);
                        $precioDB = $stmtPrecio->fetchColumn();
                        
                        if ($precioDB !== false) {
                            $precioUnitario = (int) $precioDB;
                        } else {
                            // Fallback de seguridad si no existe el precio
                            $precioUnitario = 9990; 
                        }
                    } catch (Exception $e) {
                        // Fallback de contingencia si db_matriz está offline al momento de consultar precio
                        $precioUnitario = 9990; 
                    }
                }

                $totalProductos += $precioUnitario * $item['cantidad'];
                
                $itemsWithIds[] = [
                    'detalle_id'      => generateUuid(),
                    'producto_id'     => $item['producto_id'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $precioUnitario,
                    'carrito_item_id' => $item['id']
                ];
            }

            $totalPagado = $totalProductos + $totalDespachoPedido;
            $compraExitosa = false;

            // Intento 1: Conectar a la Matriz
            try {
                $matrizPdo = dbMatriz();
                $matrizPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                foreach ($itemsWithIds as $item) {
                    $stmtMatriz = $matrizPdo->prepare('CALL sp_realizar_compra(
                        :ped_id, :cli_id, :num_ord, :suc_origen, :dir_despacho, :tipo_entrega, :estado,
                        :tot_prod, :tot_despacho, :tot_pagado,
                        :det_id, :prod_id, :qty, :precio,
                        :carrito_id, :nodo_offline
                    )');
                    
                    $stmtMatriz->execute([
                        ':ped_id'       => $pedidoId,
                        ':cli_id'       => $this->clienteId,
                        ':num_ord'      => $numeroOrden,
                        ':suc_origen'   => $sucursalId,
                        ':dir_despacho' => $direccionDespachoId,
                        ':tipo_entrega' => $tipoEntrega,
                        ':estado'       => 'pendiente',
                        ':tot_prod'     => $totalProductos,
                        ':tot_despacho' => $totalDespachoPedido,
                        ':tot_pagado'   => $totalPagado,
                        ':det_id'       => $item['detalle_id'],
                        ':prod_id'      => $item['producto_id'],
                        ':qty'          => $item['cantidad'],
                        ':precio'       => $item['precio_unitario'],
                        ':carrito_id'   => $item['carrito_item_id'],
                        ':nodo_offline' => 0
                    ]);
                }
                $compraExitosa = true;

                // Descuento stock local tras confirmación de la Matriz
                try {
                    $pdoLocal = dbSucursal($node);
                    $this->descontarStockLocal($pdoLocal, $sucursalId, $itemsWithIds);
                } catch (Exception $eStock) {
                    error_log('[Matriz OK pero Fallo Stock Local] ' . $eStock->getMessage());
                }

            } catch (PDOException $e) {
                error_log('[Matriz Caída] Fallback a contingencia local para nodo ' . $node . ': ' . $e->getMessage());
                
                // Intento 2: Contingencia Local
                $pdoLocal = dbSucursal($node);
                $pdoLocal->beginTransaction();

                try {
                    // Descuento stock local en contingencia
                    $this->descontarStockLocal($pdoLocal, $sucursalId, $itemsWithIds);

                    foreach ($itemsWithIds as $item) {
                        // Guardar en tabla de huérfanas
                        $stmtHuerfana = $pdoLocal->prepare('
                            INSERT INTO ventas_huerfanas_matriz 
                            (id, pedido_id, usuario_id, producto_id, cantidad, precio_unitario, total) 
                            VALUES (:id, :ped_id, :usu_id, :prod_id, :cant, :precio, :total)
                        ');
                        
                        $stmtHuerfana->execute([
                            ':id'     => generateUuid(),
                            ':ped_id' => $pedidoId,
                            ':usu_id' => $this->clienteId,
                            ':prod_id'=> $item['producto_id'],
                            ':cant'   => $item['cantidad'],
                            ':precio' => $item['precio_unitario'],
                            ':total'  => $item['precio_unitario'] * $item['cantidad']
                        ]);
                    }
                    
                    $pdoLocal->commit();
                    $compraExitosa = true;
                    $numeroOrden = 'SYNC-' . strtoupper(substr($pedidoId, 0, 8)); // Marca visual de que operó en contingencia
                    
                } catch (Exception $eLocal) {
                    $pdoLocal->rollBack();
                    error_log('[Contingencia Fallida] ' . $eLocal->getMessage());
                    return ['success' => false, 'error' => 'No se pudo procesar la compra ni en Matriz ni en Contingencia Local.'];
                }
            }

            if ($compraExitosa) {
                $pedidosCreados[] = [
                    'pedido_id'    => $pedidoId,
                    'numero_orden' => $numeroOrden,
                    'sucursal_id'  => $sucursalId,
                    'sucursal'     => $sucursal['nombre'] ?? 'Sucursal',
                    'node'         => $node,
                    'total_pagado' => $totalPagado,
                ];
                $totalGlobal += $totalPagado;
            }
        }

        // Limpiar carrito si todo salió bien (incluso si dbMatriz está caída, puedes eliminar el carrito en la DB de sesión o local)
        // Limpieza estricta de buffer antes de emitir JSON para evitar corrupción de AJAX
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success'          => true,
            'message'          => 'Pedido procesado correctamente',
            'pedidos_creados'  => $pedidosCreados,
            'total_pagado'     => $totalGlobal,
            'cantidad_pedidos' => count($pedidosCreados)
        ]);
        exit;
    }


    public function updateStatus(string $orderId, string $estado): array
    {
        $estadosValidos = ['pendiente', 'confirmado', 'en_camino', 'entregado', 'cancelado'];

        if (!in_array($estado, $estadosValidos, true)) {
            return ['success' => false, 'error' => 'Estado no válido'];
        }

        if (($_SESSION['rol'] ?? '') !== 'admin') {
            return ['success' => false, 'error' => 'No autorizado'];
        }

        try {
            foreach (dbNodos() as $node => $pdo) {
                $stmt = $pdo->prepare('UPDATE pedidos SET estado_pedido = :estado WHERE id = :id');
                $stmt->execute([
                    ':estado' => $estado,
                    ':id'     => $orderId,
                ]);

                if ($stmt->rowCount() > 0) {
                    return [
                        'success'   => true,
                        'message'   => "Estado actualizado a '{$estado}'",
                        'node'      => $node,
                        'node_info' => Database::getInstance()->getNodeInfo(),
                    ];
                }
            }

            return ['success' => false, 'error' => 'Pedido no encontrado'];
        } catch (Throwable $e) {
            error_log('[OrdersController::updateStatus] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al actualizar estado'];
        }
    }

    public function handleUpdateStatusRequest(array $input): void
    {
        if (($_SESSION['rol'] ?? '') !== 'admin') {
            http_response_code(403);
            $_SESSION['flash_error'] = 'No autorizado';
            header('Location: ' . url('reviews'));
            exit;
        }

        $pedidoId = trim((string) ($input['pedido_id'] ?? ''));
        $estadoPedido = trim((string) ($input['estado_pedido'] ?? ''));
        $node = trim(strtolower((string) ($input['node'] ?? '')));
        $allowed = ['norte', 'sur', 'centro'];
        if (!in_array($node, $allowed, true)) {
            $node = strtolower((string) currentNodeType());
        }
        if (!in_array($node, $allowed, true)) {
            $node = 'norte';
        }

        $allowedStates = ['pendiente', 'confirmado', 'en_camino', 'entregado', 'cancelado'];
        if ($pedidoId === '' || !in_array($estadoPedido, $allowedStates, true)) {
            $_SESSION['flash_error'] = 'Datos inválidos para actualizar el pedido';
            header('Location: ' . url('reviews') . '?node=' . urlencode($node));
            exit;
        }

        try {
            $stmt = dbSucursal($node)->prepare('
                UPDATE pedidos
                SET estado_pedido = :estado
                WHERE id = :id
            ');
            $stmt->execute([':estado' => $estadoPedido, ':id' => $pedidoId]);
            $_SESSION['flash_success'] = $stmt->rowCount() > 0 ? 'Estado del pedido actualizado correctamente' : 'No se pudo actualizar el pedido';
        } catch (Throwable $e) {
            error_log('[OrdersController] ' . $e->getMessage());
            $_SESSION['flash_error'] = 'Error al actualizar el pedido';
        }

        header('Location: ' . url('reviews') . '?node=' . urlencode($node));
        exit;
    }

    private function descontarStockLocal(PDO $pdoLocal, string $sucursalId, array $itemsWithIds): void
    {
        foreach ($itemsWithIds as $item) {
            $stmtStock = $pdoLocal->prepare('CALL sp_actualizar_stock(:pid, :sid, :qty, @success)');
            $stmtStock->execute([
                ':pid' => $item['producto_id'],
                ':sid' => $sucursalId,
                ':qty' => $item['cantidad']
            ]);
            
            $res = $pdoLocal->query('SELECT @success AS success')->fetch(PDO::FETCH_ASSOC);
            if (!$res || $res['success'] == 0) {
                throw new RuntimeException("Stock insuficiente local para producto {$item['producto_id']}");
            }
        }
    }
}