<?php
// src/controllers/CartController.php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/node-config.php';

class CartController
{
    public function __construct()
    {
        if (!isset($_SESSION['cart_token'])) {
            $_SESSION['cart_token'] = generateUuid();
        }
    }

    private function getClienteId(): ?string
    {
        return $_SESSION['cliente_id'] ?? null;
    }

    private function getSessionToken(): ?string
    {
        if (!isset($_SESSION['cart_token'])) {
            $_SESSION['cart_token'] = generateUuid();
        }

        return $_SESSION['cart_token'];
    }

    private function isAjax(): bool
    {
        return (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    public function handleAddRequest(array $input): void
    {
        $productoId = (string) ($input['producto_id'] ?? '');
        $sucursalId = (string) ($input['sucursal_id'] ?? '');
        $cantidad   = (int) ($input['cantidad'] ?? 1);

        $resultado = $this->add($productoId, $sucursalId, $cantidad);

        if ($this->isAjax()) {
            ob_clean();
            header('Content-Type: application/json');
            if (!empty($resultado['success'])) {
                echo json_encode(['success' => true, 'message' => $resultado['message'] ?? 'Producto agregado al carrito']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $resultado['error'] ?? 'No se pudo agregar el producto']);
            }
            exit;
        }

        if (!empty($resultado['success'])) {
            $_SESSION['flash_success'] = $resultado['message'] ?? 'Producto agregado al carrito';
            header('Location: ' . url('cart'));
            exit;
        }

        $_SESSION['flash_error'] = $resultado['error'] ?? 'No se pudo agregar el producto al carrito';
        error_log('[CartController::handleAddRequest] ' . $_SESSION['flash_error']);
        header('Location: ' . url('products/' . $productoId));
        exit;
    }

    public function handleUpdateRequest(array $input): void
    {
        $itemId   = (string) ($input['item_id'] ?? '');
        $cantidad = (int) ($input['cantidad'] ?? 1);

        $resultado = $this->update($itemId, $cantidad);

        if ($this->isAjax()) {
            ob_clean();
            header('Content-Type: application/json');
            if (!empty($resultado['success'])) {
                echo json_encode(['success' => true, 'message' => $resultado['message'] ?? 'Carrito actualizado']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $resultado['error'] ?? 'No se pudo actualizar el carrito']);
            }
            exit;
        }

        $_SESSION['flash_' . (!empty($resultado['success']) ? 'success' : 'error')] =
            $resultado['message'] ?? $resultado['error'] ?? 'No se pudo actualizar el carrito';

        header('Location: ' . url('cart'));
        exit;
    }

    public function handleRemoveRequest(array $input): void
    {
        $itemId = (string) ($input['item_id'] ?? '');

        $resultado = $this->remove($itemId);

        if ($this->isAjax()) {
            ob_clean();
            header('Content-Type: application/json');
            if (!empty($resultado['success'])) {
                echo json_encode(['success' => true, 'message' => $resultado['message'] ?? 'Producto eliminado']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $resultado['error'] ?? 'No se pudo eliminar el producto']);
            }
            exit;
        }

        $_SESSION['flash_' . (!empty($resultado['success']) ? 'success' : 'error')] =
            $resultado['message'] ?? $resultado['error'] ?? 'No se pudo eliminar el producto';

        header('Location: ' . url('cart'));
        exit;
    }

    public function handleClearRequest(): void
    {
        $resultado = $this->clear();

        if ($this->isAjax()) {
            ob_clean();
            header('Content-Type: application/json');
            if (!empty($resultado['success'])) {
                echo json_encode(['success' => true, 'message' => $resultado['message'] ?? 'Carrito vaciado']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $resultado['error'] ?? 'No se pudo vaciar el carrito']);
            }
            exit;
        }

        $_SESSION['flash_' . (!empty($resultado['success']) ? 'success' : 'error')] =
            $resultado['message'] ?? $resultado['error'] ?? 'No se pudo vaciar el carrito';

        header('Location: ' . url('cart'));
        exit;
    }

    public function index(): array
    {
        try {
            $carrito = getCarrito($this->getSessionToken(), $this->getClienteId());

            $carrito['items'] = array_map(function (array $item): array {
                $item['precio_efectivo'] = (float) ($item['precio_efectivo'] ?? 0);
                $item['precio_tarjeta']  = (float) ($item['precio_tarjeta'] ?? 0);
                $item['subtotal_item']   = ((float) ($item['precio_efectivo'] ?? 0)) * (int) ($item['cantidad'] ?? 0);
                return $item;
            }, $carrito['items']);

            return [
                'success'   => true,
                'carrito'   => $carrito,
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Throwable $e) {
            error_log('[CartController::index] ' . $e->getMessage());

            return [
                'success'   => false,
                'error'     => 'Error al obtener el carrito',
                'carrito'   => [
                    'carrito_id'  => null,
                    'items'       => [],
                    'total_items' => 0,
                    'subtotal'    => 0.0,
                ],
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        }
    }

    public function add(string $productoId, string $sucursalId, int $cantidad = 1): array
    {
        if ($productoId === '') {
            return ['success' => false, 'error' => 'Producto inválido'];
        }

        if ($cantidad < 1) {
            return ['success' => false, 'error' => 'La cantidad debe ser mayor a 0'];
        }

        if ($sucursalId === '') {
            return ['success' => false, 'error' => 'Debe seleccionar una sucursal'];
        }

        try {
            $producto = getProductById($productoId);
            if ($producto === null) {
                return ['success' => false, 'error' => 'Producto no encontrado'];
            }

            $sucursal = getBranchById($sucursalId);
            if ($sucursal === null) {
                return ['success' => false, 'error' => 'Sucursal inválida'];
            }

            $success = addToCarrito(
                $productoId,
                $sucursalId,
                $cantidad,
                $this->getSessionToken(),
                $this->getClienteId()
            );

            return [
                'success'   => $success,
                'message'   => $success ? 'Producto agregado al carrito' : null,
                'error'     => $success ? null : 'No se pudo agregar el producto al carrito',
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Throwable $e) {
            error_log('[CartController::add] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al agregar producto'];
        }
    }

    public function update(string $itemId, int $cantidad): array
    {
        if ($itemId === '') {
            return ['success' => false, 'error' => 'Item inválido'];
        }

        if ($cantidad < 0) {
            return ['success' => false, 'error' => 'Cantidad inválida'];
        }

        if ($cantidad === 0) {
            return $this->remove($itemId);
        }

        try {
            $pdo = dbMatriz();

            $item = queryOne(
                $pdo,
                'SELECT ci.id, ci.producto_id, ci.sucursal_id, ci.cantidad, ci.carrito_id
                 FROM carrito_items ci
                 JOIN carritos c ON c.id = ci.carrito_id
                 WHERE ci.id = :item_id
                   AND (c.session_token = :tok OR c.cliente_id = :cid)
                 LIMIT 1',
                [
                    ':item_id' => $itemId,
                    ':tok'     => $this->getSessionToken(),
                    ':cid'     => $this->getClienteId(),
                ]
            );

            if ($item === null) {
                return ['success' => false, 'error' => 'Item no encontrado en el carrito'];
            }

            $stockDisponible = getStockProducto(
                (string) $item['producto_id'],
                (string) $item['sucursal_id']
            );

            if ($stockDisponible < $cantidad) {
                return [
                    'success' => false,
                    'error'   => "Stock insuficiente (disponible: {$stockDisponible})",
                ];
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE carrito_items SET cantidad = :qty WHERE id = :id');
            $stmt->execute([
                ':qty' => $cantidad,
                ':id'  => $itemId,
            ]);

            $pdo->commit();

            return [
                'success'   => true,
                'message'   => 'Cantidad actualizada',
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[CartController::update] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al actualizar cantidad'];
        }
    }

    public function remove(string $itemId): array
    {
        if ($itemId === '') {
            return ['success' => false, 'error' => 'Item inválido'];
        }

        try {
            $pdo = dbMatriz();

            $item = queryOne(
                $pdo,
                'SELECT ci.id
                 FROM carrito_items ci
                 JOIN carritos c ON c.id = ci.carrito_id
                 WHERE ci.id = :item_id
                   AND (c.session_token = :tok OR c.cliente_id = :cid)
                 LIMIT 1',
                [
                    ':item_id' => $itemId,
                    ':tok'     => $this->getSessionToken(),
                    ':cid'     => $this->getClienteId(),
                ]
            );

            if ($item === null) {
                return ['success' => false, 'error' => 'Item no encontrado en el carrito'];
            }

            $pdo->prepare('DELETE FROM carrito_items WHERE id = :id')
                ->execute([':id' => $itemId]);

            return [
                'success'   => true,
                'message'   => 'Producto eliminado del carrito',
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Throwable $e) {
            error_log('[CartController::remove] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al eliminar producto'];
        }
    }

    public function clear(): array
    {
        try {
            $pdo = dbMatriz();
            $carrito = getCarrito($this->getSessionToken(), $this->getClienteId());

            if (($carrito['carrito_id'] ?? null) === null) {
                return ['success' => true, 'message' => 'El carrito ya estaba vacío'];
            }

            $pdo->prepare('DELETE FROM carrito_items WHERE carrito_id = :cid')
                ->execute([':cid' => $carrito['carrito_id']]);

            return [
                'success'   => true,
                'message'   => 'Carrito vaciado',
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Throwable $e) {
            error_log('[CartController::clear] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al vaciar el carrito'];
        }
    }
}