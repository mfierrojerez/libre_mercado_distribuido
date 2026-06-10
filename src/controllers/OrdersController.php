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

    public function index(): array
{
    try {
        $rol = $_SESSION['rol'] ?? null;
        $clienteId = $_SESSION['cliente_id'] ?? null;

        if ($rol === 'admin') {
            $pedidos = getPedidos(null, 100);
        } elseif ($clienteId !== null) {
            $pedidos = getPedidos($clienteId, 50);
        } else {
            return [
                'success'   => false,
                'error'     => 'Debes iniciar sesión para ver tus pedidos',
                'pedidos'   => [],
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        }

        return [
            'success'   => true,
            'pedidos'   => $pedidos,
            'count'     => count($pedidos),
            'node_info' => Database::getInstance()->getNodeInfo(),
        ];
    } catch (Throwable $e) {
        error_log('[OrdersController::index] ' . $e->getMessage());

        return [
            'success'   => false,
            'error'     => 'Error al obtener pedidos',
            'pedidos'   => [],
            'node_info' => Database::getInstance()->getNodeInfo(),
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

    public function handleCreateRequest(array $input): void
    {
        $tipoEntrega = (string) ($input['tipo_entrega'] ?? '');
        $direccionId = trim((string) ($input['direccion_despacho_id'] ?? ''));

        $resultado = $this->create($tipoEntrega, $direccionId !== '' ? $direccionId : null);

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
    error_log('[OrdersController::create] INICIO cliente=' . ($this->clienteId ?? 'null') .
        ' tipo_entrega=' . $tipoEntrega .
        ' direccion=' . ($direccionDespachoId ?? 'null'));

    if ($this->clienteId === null) {
        error_log('[OrdersController::create] cliente no autenticado');
        return ['success' => false, 'error' => 'Debes iniciar sesión para crear un pedido'];
    }

    if (!in_array($tipoEntrega, ['retiro_tienda', 'despacho_domicilio'], true)) {
        error_log('[OrdersController::create] tipo_entrega invalido=' . $tipoEntrega);
        return ['success' => false, 'error' => 'Tipo de entrega no válido'];
    }

    if ($tipoEntrega === 'despacho_domicilio' && empty($direccionDespachoId)) {
        error_log('[OrdersController::create] falta direccion para despacho');
        return ['success' => false, 'error' => 'Debes seleccionar una dirección de despacho'];
    }

    $sessionToken = $_SESSION['cart_token'] ?? null;
    $carrito = getCarrito($sessionToken, $this->clienteId);

    error_log('[OrdersController::create] carrito_id=' . ($carrito['carrito_id'] ?? 'null') .
        ' items=' . count($carrito['items'] ?? []));

    if (empty($carrito['items'])) {
        error_log('[OrdersController::create] carrito vacio');
        return ['success' => false, 'error' => 'El carrito está vacío'];
    }

    $grupos = [];
    foreach ($carrito['items'] as $item) {
        $sucursalId = (string) ($item['sucursal_id'] ?? '');

        if ($sucursalId === '') {
            error_log('[OrdersController::create] item sin sucursal=' . json_encode($item));
            return ['success' => false, 'error' => 'Hay items sin sucursal asignada'];
        }

        $grupos[$sucursalId][] = $item;
    }

    error_log('[OrdersController::create] grupos=' . json_encode(array_keys($grupos)));

    $matrizPdo = dbMatriz();
    $openedTransactions = [];
    $pedidosCreados = [];
    $totalGlobal = 0;
    $despachoPendiente = ($tipoEntrega === 'despacho_domicilio') ? 3990 : 0;

    try {
        error_log('[OrdersController::create] beginTransaction matriz');
        $matrizPdo->beginTransaction();

        foreach ($grupos as $sucursalId => $itemsSucursal) {
            $sucursal = getBranchById($sucursalId);

            if ($sucursal === null) {
                throw new RuntimeException("Sucursal inválida: {$sucursalId}");
            }

            $node = $sucursal['codigo_nodo'] ?? null;
            error_log('[OrdersController::create] sucursal=' . $sucursalId .
                ' nombre=' . ($sucursal['nombre'] ?? 'null') .
                ' node=' . ($node ?? 'null'));

            if (!$node) {
                throw new RuntimeException("La sucursal {$sucursalId} no tiene codigo_nodo");
            }

            $pdo = dbSucursal($node);

            if (!isset($openedTransactions[$node]) && !$pdo->inTransaction()) {
                error_log('[OrdersController::create] beginTransaction nodo=' . $node);
                $pdo->beginTransaction();
                $openedTransactions[$node] = $pdo;
            }

            $totalProductos = 0;
            $detalleItems = [];

            foreach ($itemsSucursal as $item) {
                $productoId = (string) $item['producto_id'];
                $cantidad   = (int) $item['cantidad'];

                $stock = queryOne($pdo, '
                    SELECT cantidad_real
                    FROM stock
                    WHERE producto_id = :pid AND sucursal_id = :sid
                    LIMIT 1
                ', [
                    ':pid' => $productoId,
                    ':sid' => $sucursalId,
                ]);

                $stockDisponible = (int) ($stock['cantidad_real'] ?? 0);

                $precio = queryOne($matrizPdo, '
                    SELECT precio_efectivo
                    FROM precios_sucursal
                    WHERE producto_id = :pid AND sucursal_id = :sid
                    LIMIT 1
                ', [
                    ':pid' => $productoId,
                    ':sid' => $sucursalId,
                ]);

                $precioUnitario = (int) ($precio['precio_efectivo'] ?? 0);

                error_log('[OrdersController::create] producto=' . $productoId .
                    ' sucursal=' . $sucursalId .
                    ' qty=' . $cantidad .
                    ' stock=' . $stockDisponible .
                    ' precio=' . $precioUnitario);

                if ($stockDisponible < $cantidad) {
                    throw new RuntimeException('Stock insuficiente para un producto en la sucursal seleccionada');
                }

                if ($precioUnitario <= 0) {
                    throw new RuntimeException("Precio inválido para producto {$productoId} en sucursal {$sucursalId}");
                }

                $totalProductos += $precioUnitario * $cantidad;

                $detalleItems[] = [
                    'producto_id'     => $productoId,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioUnitario,
                ];
            }

            $pedidoId = generateUuid();
            $numeroOrden = 'ORD-' . strtoupper(substr($pedidoId, 0, 8));
            $totalDespachoPedido = $despachoPendiente;
            $despachoPendiente = 0;
            $totalPagado = $totalProductos + $totalDespachoPedido;

            error_log('[OrdersController::create] insert pedido id=' . $pedidoId .
                ' numero=' . $numeroOrden .
                ' total_productos=' . $totalProductos .
                ' despacho=' . $totalDespachoPedido .
                ' total_pagado=' . $totalPagado);

            $pdo->prepare('
                INSERT INTO pedidos
                    (id, cliente_id, numero_orden, sucursal_origen_id, direccion_despacho_id,
                     tipo_entrega, estado_pedido, total_productos, total_despacho, total_pagado)
                VALUES
                    (:id, :cid, :num, :suc, :dir,
                     :tipo, :estado, :tp, :td, :tpag)
            ')->execute([
                ':id'     => $pedidoId,
                ':cid'    => $this->clienteId,
                ':num'    => $numeroOrden,
                ':suc'    => $sucursalId,
                ':dir'    => $direccionDespachoId,
                ':tipo'   => $tipoEntrega,
                ':estado' => 'pendiente',
                ':tp'     => $totalProductos,
                ':td'     => $totalDespachoPedido,
                ':tpag'   => $totalPagado,
            ]);

            foreach ($detalleItems as $detalle) {
                error_log('[OrdersController::create] insert detalle pedido=' . $pedidoId .
                    ' producto=' . $detalle['producto_id'] .
                    ' qty=' . $detalle['cantidad'] .
                    ' precio=' . $detalle['precio_unitario']);

                $pdo->prepare('
                    INSERT INTO detalle_pedidos
                        (id, pedido_id, producto_id, cantidad, precio_unitario_pagado)
                    VALUES
                        (:id, :ped, :prod, :qty, :precio)
                ')->execute([
                    ':id'     => generateUuid(),
                    ':ped'    => $pedidoId,
                    ':prod'   => $detalle['producto_id'],
                    ':qty'    => $detalle['cantidad'],
                    ':precio' => $detalle['precio_unitario'],
                ]);

                $updated = $pdo->prepare('
                    UPDATE stock
                    SET cantidad_real = cantidad_real - :qty_discount
                    WHERE producto_id = :pid
                    AND sucursal_id = :sid
                    AND cantidad_real >= :qty_check
                ');

                $updated->execute([
                    ':qty_discount' => $detalle['cantidad'],
                    ':qty_check'    => $detalle['cantidad'],
                    ':pid'          => $detalle['producto_id'],
                    ':sid'          => $sucursalId,
                ]);

                error_log('[OrdersController::create] update stock producto=' . $detalle['producto_id'] .
                    ' sucursal=' . $sucursalId .
                    ' rowCount=' . $updated->rowCount());

                if ($updated->rowCount() === 0) {
                    throw new RuntimeException('No fue posible descontar stock de forma segura');
                }
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
            error_log('[OrdersController::create] delete carrito_items carrito_id=' . $carrito['carrito_id']);
            $stmt = $matrizPdo->prepare('DELETE FROM carrito_items WHERE carrito_id = :cid');
            $stmt->execute([':cid' => $carrito['carrito_id']]);
            error_log('[OrdersController::create] delete carrito_items rowCount=' . $stmt->rowCount());
        }

        foreach ($openedTransactions as $nodeName => $pdo) {
            if ($pdo->inTransaction()) {
                error_log('[OrdersController::create] commit nodo=' . $nodeName);
                $pdo->commit();
            }
        }

        if ($matrizPdo->inTransaction()) {
            error_log('[OrdersController::create] commit matriz');
            $matrizPdo->commit();
        }

        error_log('[OrdersController::create] SUCCESS pedidos=' . count($pedidosCreados) .
            ' total=' . $totalGlobal);

        return [
            'success'          => true,
            'message'          => 'Pedido creado correctamente',
            'pedidos_creados'  => $pedidosCreados,
            'total_pagado'     => $totalGlobal,
            'cantidad_pedidos' => count($pedidosCreados),
            'node_info'        => Database::getInstance()->getNodeInfo(),
        ];
    } catch (Throwable $e) {
        foreach ($openedTransactions as $nodeName => $pdo) {
            if ($pdo->inTransaction()) {
                error_log('[OrdersController::create] rollback nodo=' . $nodeName);
                $pdo->rollBack();
            }
        }

        if ($matrizPdo->inTransaction()) {
            error_log('[OrdersController::create] rollback matriz');
            $matrizPdo->rollBack();
        }

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
}