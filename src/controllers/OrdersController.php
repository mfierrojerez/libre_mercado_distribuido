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
        if (($_SESSION['rol'] ?? '') !== 'admin') {
            return [
                'pedidos' => getPedidos(currentClienteId()),
                'error' => null,
            ];
        }

        $node = trim(strtolower($node));
        $allowed = ['norte', 'sur', 'centro'];
        if ($node === '' || !in_array($node, $allowed, true)) {
            $node = strtolower((string) currentNodeType());
        }
        if (!in_array($node, $allowed, true)) {
            $node = 'norte';
        }

        $pedidos = getPedidosByNode($node, null, 100);
        if (empty($pedidos)) {
            try {
                $pedidos = queryAll(dbSucursal($node), '
                    SELECT p.*
                    FROM pedidos p
                    ORDER BY p.created_at DESC
                    LIMIT 100
                ');
            } catch (Throwable $e) {
                error_log('[OrdersController] fallback dbSucursal failed: ' . $e->getMessage());
                $pedidos = [];
            }
        }

        $pedidos = array_values(array_filter($pedidos, function (array $pedido) use ($node): bool {
            foreach (['node', 'nodo', 'sucursal', 'sucursal_origen', 'sucursal_origen_id', 'codigonodo'] as $key) {
                if (!isset($pedido[$key])) {
                    continue;
                }
                $value = strtolower(trim((string) $pedido[$key]));
                if ($value === '') {
                    continue;
                }
                if ($value === $node || str_contains($value, $node)) {
                    return true;
                }
            }
            return true;
        }));

        return [
            'pedidos' => $pedidos,
            'error' => null,
        ];
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

        $resultado = $this->create($tipoEntrega, $direccionId !== '' ? $direccionId : null);

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

    public function create(string $tipoEntrega, ?string $direccionDespachoId = null): array
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

        $matrizPdo = dbMatriz();
        $pedidosCreados = [];
        $totalGlobal = 0;
        $despachoPendiente = ($tipoEntrega === 'despacho_domicilio') ? 3990 : 0;

        try {
            foreach ($grupos as $sucursalId => $itemsSucursal) {
                $sucursal = getBranchById($sucursalId);
                $node = $sucursal['codigo_nodo'] ?? null;
                
                if (!$node) {
                    throw new RuntimeException("La sucursal {$sucursalId} no tiene codigo_nodo");
                }

                $pedidoId = generateUuid();
                $numeroOrden = 'ORD-' . strtoupper(substr($pedidoId, 0, 8));
                $totalDespachoPedido = $despachoPendiente;
                $despachoPendiente = 0;
                
                $totalProductos = 0;
                $itemsWithPrices = [];
                
                foreach ($itemsSucursal as $item) {
                    $precio = queryOne($matrizPdo, '
                        SELECT precio_efectivo FROM precios_sucursal
                        WHERE producto_id = :pid AND sucursal_id = :sid LIMIT 1
                    ', [':pid' => $item['producto_id'], ':sid' => $sucursalId]);
                    
                    $precioUnitario = (int) ($precio['precio_efectivo'] ?? 0);
                    
                    if ($precioUnitario <= 0) {
                        throw new RuntimeException("Precio inválido para producto {$item['producto_id']} en sucursal {$sucursalId}");
                    }
                    
                    $totalProductos += $precioUnitario * $item['cantidad'];
                    
                    $itemsWithPrices[] = [
                        'carrito_item_id' => $item['id'],
                        'producto_id'     => $item['producto_id'],
                        'cantidad'        => $item['cantidad'],
                        'precio_unitario' => $precioUnitario
                    ];
                }
                
                $totalPagado = $totalProductos + $totalDespachoPedido;
                $nodoOffline = false;

                try {
                    $pdo = dbSucursal($node);

                    foreach ($itemsWithPrices as $item) {
                        $stmt = $pdo->prepare('CALL sp_actualizar_stock(:pid, :sid, :qty, @success)');
                        $stmt->execute([
                            ':pid' => $item['producto_id'],
                            ':sid' => $sucursalId,
                            ':qty' => $item['cantidad']
                        ]);
                        
                        $res = $pdo->query('SELECT @success AS success')->fetch(PDO::FETCH_ASSOC);
                        if (!$res || $res['success'] == 0) {
                            throw new RuntimeException('Stock insuficiente para un producto en la sucursal seleccionada');
                        }
                    }
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Stock insuficiente') !== false || strpos($e->getMessage(), 'Precio inválido') !== false) {
                        throw $e;
                    }
                    
                    error_log('[OrdersController::create] fallback AP para nodo ' . $node . ': ' . $e->getMessage());
                    $nodoOffline = true;
                    $numeroOrden = 'SYNC-' . strtoupper(substr($pedidoId, 0, 8));
                }

                foreach ($itemsWithPrices as $item) {
                    $detalleId = generateUuid();
                    
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
                        
                        ':det_id'       => $detalleId,
                        ':prod_id'      => $item['producto_id'],
                        ':qty'          => $item['cantidad'],
                        ':precio'       => $item['precio_unitario'],
                        
                        ':carrito_id'   => $item['carrito_item_id'],
                        ':nodo_offline' => $nodoOffline ? 1 : 0
                    ]);
                }

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

            if (!empty($carrito['carrito_id'])) {
                $matrizPdo->prepare('DELETE FROM carrito WHERE id = :id')->execute([
                    ':id' => $carrito['carrito_id']
                ]);
            }

            return [
                'success'          => true,
                'message'          => 'Pedido creado correctamente',
                'pedidos_creados'  => $pedidosCreados,
                'total_pagado'     => $totalGlobal,
                'cantidad_pedidos' => count($pedidosCreados),
                'node_info'        => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Throwable $e) {
            error_log('[OrdersController::create][ERROR] ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => 'Error al crear el pedido: ' . $e->getMessage(),
            ];
        }
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
}