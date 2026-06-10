<?php
// src/services/ProductSyncService.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

class ProductSyncService
{
    // ── Catálogo base ─────────────────────────────────────────────────────────

    /**
     * Obtiene productos desde db_matriz.
     * Si se entrega $sucursalId, agrega precios de esa sucursal.
     *
     * @param string|null $sucursalId UUID de sucursal
     * @param int $limit
     * @param int $offset
     */
    public function getCatalog(?string $sucursalId = null, int $limit = 100, int $offset = 0): array
    {
        try {
            if ($sucursalId !== null) {
                return queryAll(dbMatriz(), '
                    SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos,
                           ps.precio_efectivo, ps.precio_tarjeta
                    FROM productos p
                    LEFT JOIN precios_sucursal ps
                           ON ps.producto_id = p.id AND ps.sucursal_id = :sid
                    ORDER BY p.nombre ASC
                    LIMIT :lim OFFSET :off
                ', [
                    ':sid' => $sucursalId,
                    ':lim' => $limit,
                    ':off' => $offset,
                ]);
            }

            return queryAll(dbMatriz(), '
                SELECT id, sku, nombre, descripcion, peso_gramos
                FROM productos
                ORDER BY nombre ASC
                LIMIT :lim OFFSET :off
            ', [
                ':lim' => $limit,
                ':off' => $offset,
            ]);
        } catch (PDOException $e) {
            error_log('[ProductSyncService::getCatalog] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Método de compatibilidad con código antiguo.
     * Antes devolvía “productos locales”; ahora devuelve catálogo de matriz.
     */
    public function getLocalProducts(?string $sucursalId = null, int $limit = 100, int $offset = 0): array
    {
        return $this->getCatalog($sucursalId, $limit, $offset);
    }

    // ── Producto individual ───────────────────────────────────────────────────

    /**
     * Verifica si un producto existe en db_matriz.
     *
     * @param string $productId UUID
     */
    public function productExistsInNode(string $productId): bool
    {
        try {
            $row = queryOne(dbMatriz(), '
                SELECT id
                FROM productos
                WHERE id = :id
                LIMIT 1
            ', [':id' => $productId]);

            return $row !== null;
        } catch (PDOException $e) {
            error_log('[ProductSyncService::productExistsInNode] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene un producto por UUID.
     * Si se entrega sucursal, agrega precio de esa sucursal.
     */
    public function getProductById(string $productId, ?string $sucursalId = null): ?array
    {
        try {
            if ($sucursalId !== null) {
                return queryOne(dbMatriz(), '
                    SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos,
                           ps.precio_efectivo, ps.precio_tarjeta
                    FROM productos p
                    LEFT JOIN precios_sucursal ps
                           ON ps.producto_id = p.id AND ps.sucursal_id = :sid
                    WHERE p.id = :pid
                    LIMIT 1
                ', [
                    ':pid' => $productId,
                    ':sid' => $sucursalId,
                ]);
            }

            return queryOne(dbMatriz(), '
                SELECT id, sku, nombre, descripcion, peso_gramos
                FROM productos
                WHERE id = :pid
                LIMIT 1
            ', [':pid' => $productId]);
        } catch (PDOException $e) {
            error_log('[ProductSyncService::getProductById] ' . $e->getMessage());
            return null;
        }
    }

    // ── Búsquedas ─────────────────────────────────────────────────────────────

    /**
     * Búsqueda por nombre o SKU.
     * Si hay sucursal, agrega precios de esa sucursal.
     */
    public function searchProducts(string $query, ?string $sucursalId = null, int $limit = 100): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $like = '%' . mb_strtolower($query) . '%';

            if ($sucursalId !== null) {
                return queryAll(dbMatriz(), '
                    SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos,
                           ps.precio_efectivo, ps.precio_tarjeta
                    FROM productos p
                    LEFT JOIN precios_sucursal ps
                           ON ps.producto_id = p.id AND ps.sucursal_id = :sid
                    WHERE LOWER(p.nombre) LIKE :q OR LOWER(p.sku) LIKE :q2
                    ORDER BY p.nombre ASC
                    LIMIT :lim
                ', [
                    ':sid' => $sucursalId,
                    ':q'   => $like,
                    ':q2'  => $like,
                    ':lim' => $limit,
                ]);
            }

            return queryAll(dbMatriz(), '
                SELECT id, sku, nombre, descripcion, peso_gramos
                FROM productos
                WHERE LOWER(nombre) LIKE :q OR LOWER(sku) LIKE :q2
                ORDER BY nombre ASC
                LIMIT :lim
            ', [
                ':q'   => $like,
                ':q2'  => $like,
                ':lim' => $limit,
            ]);
        } catch (PDOException $e) {
            error_log('[ProductSyncService::searchProducts] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Método legado: antes buscaba por categoría.
     * En el esquema nuevo no existe tabla categorias.
     * Se mantiene por compatibilidad, devolviendo [].
     */
    public function getProductsByCategory(string $category): array
    {
        error_log('[ProductSyncService::getProductsByCategory] categorias no existe en el esquema actual');
        return [];
    }

    // ── Precios y stock ───────────────────────────────────────────────────────

    /**
     * Obtiene precios de un producto en todas las sucursales.
     */
    public function getProductPricesAcrossBranches(string $productId): array
    {
        try {
            return queryAll(dbMatriz(), '
                SELECT s.id AS sucursal_id,
                       s.nombre AS sucursal_nombre,
                       ps.precio_efectivo,
                       ps.precio_tarjeta
                FROM precios_sucursal ps
                JOIN sucursales s ON s.id = ps.sucursal_id
                WHERE ps.producto_id = :pid
                ORDER BY s.nombre ASC
            ', [':pid' => $productId]);
        } catch (PDOException $e) {
            error_log('[ProductSyncService::getProductPricesAcrossBranches] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Enriquecer listado de productos con stock local.
     * Útil cuando el controller necesita catálogo + stock.
     */
    public function attachLocalStock(array $productos, string $sucursalId): array
    {
        return array_map(function (array $producto) use ($sucursalId): array {
            $producto['stock'] = getStockProducto($producto['id'], $sucursalId);
            return $producto;
        }, $productos);
    }

    /**
     * Enriquecer un producto con precio + stock local.
     */
    public function attachSucursalContext(array $producto, string $sucursalId): array
    {
        $precio = queryOne(dbMatriz(), '
            SELECT precio_efectivo, precio_tarjeta
            FROM precios_sucursal
            WHERE producto_id = :pid AND sucursal_id = :sid
            LIMIT 1
        ', [
            ':pid' => $producto['id'],
            ':sid' => $sucursalId,
        ]);

        $producto['precio_efectivo'] = $precio['precio_efectivo'] ?? null;
        $producto['precio_tarjeta']  = $precio['precio_tarjeta'] ?? null;
        $producto['stock']           = getStockProducto($producto['id'], $sucursalId);

        return $producto;
    }

    // ── “Sincronización” lógica ───────────────────────────────────────────────

    /**
     * En la nueva arquitectura no se replican productos a bases locales.
     * Este método se conserva como stub para no romper llamadas antiguas.
     */
    public function syncToLocalNode(): bool
    {
        error_log('[ProductSyncService::syncToLocalNode] No aplica: productos se leen desde db_matriz');
        return true;
    }

    /**
     * Método legado de compatibilidad.
     */
    public function syncFromMatrix(): array
    {
        return $this->getCatalog();
    }
}