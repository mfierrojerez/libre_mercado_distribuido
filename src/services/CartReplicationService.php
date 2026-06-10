<?php
// src/services/CartReplicationService.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

/**
 * CartReplicationService
 *
 * Servicio de carrito. En la nueva arquitectura el carrito vive
 * completamente en la DB local de cada sucursal (carritos + carrito_items).
 * Los precios se leen desde db_matriz.precios_sucursal.
 *
 * Nota: La lógica de carrito también está disponible directamente
 * via helpers (getCarrito, addToCarrito). Este servicio ofrece
 * métodos más granulares para uso interno de otros servicios.
 */
class CartReplicationService
{
    // ── Obtener carrito ───────────────────────────────────────────────────────

    /**
     * Obtiene el carrito activo con items enriquecidos con precios.
     *
     * @param string|null $sessionToken Token de sesión anónima
     * @param string|null $clienteId    UUID del cliente autenticado
     */
    public function getCart(?string $sessionToken = null, ?string $clienteId = null): array
    {
        $empty = ['carrito_id' => null, 'items' => [], 'total_items' => 0, 'subtotal' => 0.0];

        try {
            $carrito = getCarrito($sessionToken, $clienteId);

            if (empty($carrito['items'])) {
                return $carrito;
            }

            // Enriquecer items con precio desde db_matriz
            $carrito['items'] = array_map(
                fn(array $item) => $this->enrichItem($item),
                $carrito['items']
            );

            $carrito['subtotal'] = $this->getSubtotal($carrito['items']);

            return $carrito;
        } catch (Exception $e) {
            error_log('[CartReplicationService::getCart] ' . $e->getMessage());
            return $empty;
        }
    }

    // ── Agregar item ──────────────────────────────────────────────────────────

    /**
     * Agrega un producto al carrito en la DB local.
     *
     * @param string      $productoId   UUID del producto
     * @param string      $sucursalId   UUID de la sucursal
     * @param int         $cantidad     Unidades
     * @param string|null $sessionToken Token sesión anónima
     * @param string|null $clienteId    UUID cliente autenticado
     */
    public function addToCart(
        string  $productoId,
        string  $sucursalId,
        int     $cantidad,
        ?string $sessionToken = null,
        ?string $clienteId    = null
    ): bool {
        try {
            return addToCarrito($productoId, $sucursalId, $cantidad, $sessionToken, $clienteId);
        } catch (Exception $e) {
            error_log('[CartReplicationService::addToCart] ' . $e->getMessage());
            return false;
        }
    }

    // ── Actualizar cantidad ───────────────────────────────────────────────────

    /**
     * Actualiza la cantidad de un item verificando stock disponible.
     *
     * @param string $itemId      UUID del carrito_item
     * @param int    $newQuantity Nueva cantidad (> 0)
     */
    public function updateCartItem(string $itemId, int $newQuantity): bool
    {
        if ($newQuantity < 1) {
            error_log('[CartReplicationService::updateCartItem] Cantidad inválida: ' . $newQuantity);
            return false;
        }

        try {
            $pdo = db();

            // Obtener item para verificar stock
            $item = queryOne($pdo,
                'SELECT producto_id, sucursal_id FROM carrito_items WHERE id = :id LIMIT 1',
                [':id' => $itemId]
            );

            if ($item === null) {
                error_log('[CartReplicationService::updateCartItem] Item no encontrado: ' . $itemId);
                return false;
            }

            $stock = getStockProducto($item['producto_id'], $item['sucursal_id']);
            if ($stock < $newQuantity) {
                error_log("[CartReplicationService::updateCartItem] Stock insuficiente: {$stock} < {$newQuantity}");
                return false;
            }

            $pdo->prepare('UPDATE carrito_items SET cantidad = :qty WHERE id = :id')
                ->execute([':qty' => $newQuantity, ':id' => $itemId]);

            return true;
        } catch (PDOException $e) {
            error_log('[CartReplicationService::updateCartItem] ' . $e->getMessage());
            return false;
        }
    }

    // ── Eliminar item ─────────────────────────────────────────────────────────

    /**
     * Elimina un item del carrito (hard delete).
     *
     * @param string $itemId UUID del carrito_item
     */
    public function removeFromCart(string $itemId): bool
    {
        try {
            $stmt = db()->prepare('DELETE FROM carrito_items WHERE id = :id');
            $stmt->execute([':id' => $itemId]);

            if ($stmt->rowCount() === 0) {
                error_log('[CartReplicationService::removeFromCart] Item no encontrado: ' . $itemId);
                return false;
            }

            return true;
        } catch (PDOException $e) {
            error_log('[CartReplicationService::removeFromCart] ' . $e->getMessage());
            return false;
        }
    }

    // ── Vaciar carrito ────────────────────────────────────────────────────────

    /**
     * Vacía todos los items del carrito indicado.
     *
     * @param string $carritoId UUID del carrito
     */
    public function clearCart(string $carritoId): bool
    {
        try {
            db()->prepare('DELETE FROM carrito_items WHERE carrito_id = :cid')
                ->execute([':cid' => $carritoId]);

            return true;
        } catch (PDOException $e) {
            error_log('[CartReplicationService::clearCart] ' . $e->getMessage());
            return false;
        }
    }

    // ── Migrar carrito anónimo → autenticado ──────────────────────────────────

    /**
     * Al hacer login, fusiona el carrito de sesión anónima con el del cliente.
     * Items del carrito anónimo se mueven al carrito del cliente.
     * Si hay conflicto de producto+sucursal, suma las cantidades.
     *
     * @param string $sessionToken Token de la sesión anónima
     * @param string $clienteId    UUID del cliente recién autenticado
     */
    public function mergeAnonymousCart(string $sessionToken, string $clienteId): bool
    {
        try {
            $pdo = db();

            $anonCarrito = queryOne($pdo,
                'SELECT id FROM carritos WHERE session_token = :tok LIMIT 1',
                [':tok' => $sessionToken]
            );

            if ($anonCarrito === null) {
                return true; // Nada que fusionar
            }

            $anonItems = queryAll($pdo,
                'SELECT producto_id, sucursal_id, cantidad FROM carrito_items WHERE carrito_id = :cid',
                [':cid' => $anonCarrito['id']]
            );

            if (empty($anonItems)) {
                return true;
            }

            // Buscar o crear carrito del cliente
            $clienteCarrito = queryOne($pdo,
                'SELECT id FROM carritos WHERE cliente_id = :cid LIMIT 1',
                [':cid' => $clienteId]
            );

            if ($clienteCarrito === null) {
                // Reusar el carrito anónimo asignándolo al cliente
                $pdo->prepare(
                    'UPDATE carritos SET cliente_id = :cid, session_token = NULL WHERE id = :id'
                )->execute([':cid' => $clienteId, ':id' => $anonCarrito['id']]);
                return true;
            }

            $clienteCarritoId = $clienteCarrito['id'];

            // Fusionar items
            foreach ($anonItems as $item) {
                $existing = queryOne($pdo,
                    'SELECT id, cantidad FROM carrito_items
                     WHERE carrito_id = :cid AND producto_id = :pid AND sucursal_id = :sid LIMIT 1',
                    [
                        ':cid' => $clienteCarritoId,
                        ':pid' => $item['producto_id'],
                        ':sid' => $item['sucursal_id'],
                    ]
                );

                if ($existing !== null) {
                    $pdo->prepare(
                        'UPDATE carrito_items SET cantidad = cantidad + :qty WHERE id = :id'
                    )->execute([':qty' => $item['cantidad'], ':id' => $existing['id']]);
                } else {
                    $pdo->prepare('
                        INSERT INTO carrito_items (id, carrito_id, producto_id, sucursal_id, cantidad)
                        VALUES (:id, :cid, :pid, :sid, :qty)
                    ')->execute([
                        ':id'  => generateUuid(),
                        ':cid' => $clienteCarritoId,
                        ':pid' => $item['producto_id'],
                        ':sid' => $item['sucursal_id'],
                        ':qty' => $item['cantidad'],
                    ]);
                }
            }

            // Eliminar carrito anónimo
            $pdo->prepare('DELETE FROM carrito_items WHERE carrito_id = :cid')
                ->execute([':cid' => $anonCarrito['id']]);
            $pdo->prepare('DELETE FROM carritos WHERE id = :id')
                ->execute([':id' => $anonCarrito['id']]);

            return true;
        } catch (PDOException $e) {
            error_log('[CartReplicationService::mergeAnonymousCart] ' . $e->getMessage());
            return false;
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    /**
     * Calcula el subtotal de un array de items enriquecidos.
     */
    public function getSubtotal(array $items): float
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $precio   = (float) ($item['precio_efectivo'] ?? 0);
            $cantidad = (int)   ($item['cantidad']        ?? 0);
            $subtotal += $precio * $cantidad;
        }
        return round($subtotal, 2);
    }

    /**
     * Enriquece un item de carrito_items con nombre y precio desde db_matriz.
     */
    private function enrichItem(array $item): array
    {
        $producto = getProductoConPrecio($item['producto_id'], $item['sucursal_id']);
        return array_merge($item, [
            'nombre'          => $producto['nombre']          ?? 'Producto no disponible',
            'sku'             => $producto['sku']             ?? null,
            'precio_efectivo' => $producto['precio_efectivo'] ?? 0,
            'precio_tarjeta'  => $producto['precio_tarjeta']  ?? 0,
            'subtotal_item'   => ($producto['precio_efectivo'] ?? 0) * $item['cantidad'],
        ]);
    }
}