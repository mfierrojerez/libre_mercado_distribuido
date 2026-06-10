<?php
// src/controllers/ProductsController.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/node-config.php';

class ProductsController
{
    private string $sucursalId;

    public function __construct()
    {
        if (empty($_SESSION['sucursal_id'])) {
            throw new RuntimeException('[ProductsController] sucursal_id no definido en sesión');
        }
        $this->sucursalId = $_SESSION['sucursal_id'];
    }

    // ── Listar productos ──────────────────────────────────────────────────────

    /**
     * GET /products
     * Lista productos desde db_matriz con precio y stock de la sucursal actual.
     *
     * @param int $limit  Máx. resultados (usa getMaxProductsPerPage() por defecto)
     * @param int $offset Paginación
     */
    public function index(int $limit = 0, int $offset = 0): array
    {
        if ($limit === 0) {
            $limit = getMaxProductsPerPage();
        }

        try {
            // Obtener productos desde db_matriz
            $productos = queryAll(dbMatriz(), '
                SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos,
                       ps.precio_efectivo, ps.precio_tarjeta
                FROM productos p
                LEFT JOIN precios_sucursal ps
                       ON ps.producto_id = p.id AND ps.sucursal_id = :suc_id
                ORDER BY p.nombre ASC
                LIMIT :lim OFFSET :off
            ', [
                ':suc_id' => $this->sucursalId,
                ':lim'    => $limit,
                ':off'    => $offset,
            ]);

            // Enriquecer con stock local
            $productos = array_map(function (array $p): array {
                $p['stock'] = getStockProducto($p['id'], $this->sucursalId);
                return $p;
            }, $productos);

            return [
                'success'     => true,
                'productos'   => $productos,
                'count'       => count($productos),
                'sucursal_id' => $this->sucursalId,
                'node_info'   => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Exception $e) {
            error_log('[ProductsController::index] ' . $e->getMessage());
            return [
                'success'   => false,
                'error'     => 'Error al obtener productos',
                'productos' => [],
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        }
    }

    // ── Ver producto ──────────────────────────────────────────────────────────

    /**
     * GET /products/{id}
     * Retorna un producto con su precio en la sucursal y stock disponible.
     *
     * @param string $id UUID del producto
     */
    public function show(string $id): array
{
    try {
        $producto = getProductoConPrecio($id, $this->sucursalId);

        if ($producto === null) {
            return [
                'success'   => false,
                'error'     => 'Producto no encontrado',
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        }

        $producto['stock'] = getStockProducto($id, $this->sucursalId);
        $producto['stock_por_sucursal'] = getStockProductoPorSucursal($id);

        $preciosOtros = queryAll(dbMatriz(), '
            SELECT s.id AS sucursal_id, s.nombre AS sucursal, ps.precio_efectivo, ps.precio_tarjeta
            FROM precios_sucursal ps
            JOIN sucursales s ON s.id = ps.sucursal_id
            WHERE ps.producto_id = :pid AND ps.sucursal_id != :suc
            ORDER BY s.nombre ASC
        ', [
            ':pid' => $id,
            ':suc' => $this->sucursalId,
        ]);

        return [
            'success'       => true,
            'producto'      => $producto,
            'precios_otros' => $preciosOtros,
            'node_info'     => Database::getInstance()->getNodeInfo(),
        ];
    } catch (Exception $e) {
        error_log('[ProductsController::show] ' . $e->getMessage());
        return [
            'success'   => false,
            'error'     => 'Error al obtener producto',
            'node_info' => Database::getInstance()->getNodeInfo(),
        ];
    }
}

    // ── Buscar productos ──────────────────────────────────────────────────────

    /**
     * GET /products/search?q=...
     * Búsqueda por nombre o SKU en db_matriz, con precio y stock de la sucursal.
     *
     * @param string $query Término de búsqueda
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if (strlen($query) < 2) {
            return ['success' => false, 'error' => 'El término de búsqueda es muy corto'];
        }

        try {
            $like = '%' . $query . '%';

            $productos = queryAll(dbMatriz(), '
                SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos,
                       ps.precio_efectivo, ps.precio_tarjeta
                FROM productos p
                LEFT JOIN precios_sucursal ps
                       ON ps.producto_id = p.id AND ps.sucursal_id = :suc_id
                WHERE p.nombre LIKE :q OR p.sku LIKE :q2
                ORDER BY p.nombre ASC
                LIMIT :lim
            ', [
                ':suc_id' => $this->sucursalId,
                ':q'      => $like,
                ':q2'     => $like,
                ':lim'    => getMaxProductsPerPage(),
            ]);

            // Enriquecer con stock local
            $productos = array_map(function (array $p): array {
                $p['stock'] = getStockProducto($p['id'], $this->sucursalId);
                return $p;
            }, $productos);

            return [
                'success'   => true,
                'query'     => $query,
                'productos' => $productos,
                'count'     => count($productos),
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Exception $e) {
            error_log('[ProductsController::search] ' . $e->getMessage());
            return [
                'success'   => false,
                'error'     => 'Error en búsqueda',
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        }
    }

    // ── Inventario (vista admin) ──────────────────────────────────────────────

    /**
     * GET /products/inventory
     * Retorna stock completo de la sucursal actual con datos de producto.
     * Solo accesible para rol admin.
     */
    public function inventory(): array
    {
        if (($_SESSION['rol'] ?? '') !== 'admin') {
            return ['success' => false, 'error' => 'No autorizado'];
        }

        try {
            $inventario = getInventario($this->sucursalId);

            return [
                'success'     => true,
                'inventario'  => $inventario,
                'count'       => count($inventario),
                'sucursal_id' => $this->sucursalId,
                'node_info'   => Database::getInstance()->getNodeInfo(),
            ];
        } catch (Exception $e) {
            error_log('[ProductsController::inventory] ' . $e->getMessage());
            return [
                'success'   => false,
                'error'     => 'Error al obtener inventario',
                'node_info' => Database::getInstance()->getNodeInfo(),
            ];
        }
    }
}