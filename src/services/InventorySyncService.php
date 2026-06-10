<?php
// src/services/InventorySyncService.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

class InventorySyncService
{
    /**
     * Hosts y DBs locales por nodo.
     * Ajusta si cambian los nombres de servicio Docker.
     */
    private array $branchMap = [
        Database::NODE_NORTE => [
            'host' => 'nodo_norte_db',
            'db'   => 'db_norte',
        ],
        Database::NODE_SUR => [
            'host' => 'nodo_sur_db',
            'db'   => 'db_sur',
        ],
        Database::NODE_CENTRO => [
            'host' => 'nodo_centro_db',
            'db'   => 'db_centro',
        ],
    ];

    // ── Inventario local ──────────────────────────────────────────────────────

    /**
     * Obtiene el inventario local de la sucursal actual.
     *
     * @param string $sucursalId UUID de la sucursal actual
     */
    public function getLocalInventory(string $sucursalId): array
    {
        try {
            $stock = queryAll(db(), '
                SELECT s.producto_id, s.sucursal_id, s.cantidad_real
                FROM stock s
                WHERE s.sucursal_id = :sid
                ORDER BY s.producto_id
            ', [':sid' => $sucursalId]);

            if (empty($stock)) {
                return [];
            }

            $productoIds   = array_values(array_unique(array_column($stock, 'producto_id')));
            $placeholders  = implode(',', array_fill(0, count($productoIds), '?'));

            $productos = queryAll(
                dbMatriz(),
                "SELECT id, sku, nombre, descripcion, peso_gramos
                 FROM productos
                 WHERE id IN ($placeholders)",
                $productoIds
            );

            $precios = queryAll(dbMatriz(), '
                SELECT producto_id, precio_efectivo, precio_tarjeta
                FROM precios_sucursal
                WHERE sucursal_id = :sid
            ', [':sid' => $sucursalId]);

            $productosIdx = array_column($productos, null, 'id');
            $preciosIdx   = array_column($precios, null, 'producto_id');

            return array_map(function (array $row) use ($productosIdx, $preciosIdx) {
                $p  = $productosIdx[$row['producto_id']] ?? [];
                $ps = $preciosIdx[$row['producto_id']] ?? [];

                return [
                    'producto_id'      => $row['producto_id'],
                    'sucursal_id'      => $row['sucursal_id'],
                    'cantidad_real'    => (int) $row['cantidad_real'],
                    'sku'              => $p['sku'] ?? null,
                    'nombre'           => $p['nombre'] ?? 'Producto no encontrado',
                    'descripcion'      => $p['descripcion'] ?? null,
                    'peso_gramos'      => $p['peso_gramos'] ?? null,
                    'precio_efectivo'  => isset($ps['precio_efectivo']) ? (int) $ps['precio_efectivo'] : null,
                    'precio_tarjeta'   => isset($ps['precio_tarjeta']) ? (int) $ps['precio_tarjeta'] : null,
                ];
            }, $stock);

        } catch (PDOException $e) {
            error_log('[InventorySyncService::getLocalInventory] ' . $e->getMessage());
            return [];
        }
    }

    // ── Inventario consolidado ────────────────────────────────────────────────

    /**
     * Obtiene el inventario consolidado de todas las sucursales locales.
     * No incluye matriz porque db_matriz no guarda stock operativo [file:1][file:2].
     */
    public function getGlobalInventory(): array
    {
        $all = [];

        try {
            $sucursales = $this->getAvailableBranches();

            foreach ($sucursales as $sucursal) {
                $branchDb = $this->resolveBranchConnectionBySucursalId($sucursal['id']);
                if ($branchDb === null) {
                    continue;
                }

                $rows = queryAll($branchDb, '
                    SELECT producto_id, sucursal_id, cantidad_real
                    FROM stock
                    WHERE sucursal_id = :sid
                ', [':sid' => $sucursal['id']]);

                foreach ($rows as &$row) {
                    $row['sucursal_nombre'] = $sucursal['nombre'];
                    $row['ciudad']          = $sucursal['ciudad'] ?? null;
                    $row['region']          = $sucursal['region'] ?? null;
                }

                $all = array_merge($all, $rows);
            }

            if (empty($all)) {
                return [];
            }

            $productoIds  = array_values(array_unique(array_column($all, 'producto_id')));
            $placeholders = implode(',', array_fill(0, count($productoIds), '?'));

            $productos = queryAll(
                dbMatriz(),
                "SELECT id, sku, nombre, descripcion, peso_gramos
                 FROM productos
                 WHERE id IN ($placeholders)",
                $productoIds
            );

            $productosIdx = array_column($productos, null, 'id');

            foreach ($all as &$row) {
                $p = $productosIdx[$row['producto_id']] ?? [];
                $row['sku']         = $p['sku'] ?? null;
                $row['nombre']      = $p['nombre'] ?? 'Producto no encontrado';
                $row['descripcion'] = $p['descripcion'] ?? null;
                $row['peso_gramos'] = $p['peso_gramos'] ?? null;
            }

            unset($row);

            return $all;
        } catch (PDOException $e) {
            error_log('[InventorySyncService::getGlobalInventory] ' . $e->getMessage());
            return [];
        }
    }

    // ── Stock por producto en todas las sucursales ───────────────────────────

    /**
     * Retorna stock de un producto en todas las sucursales.
     *
     * @param string $productId UUID del producto
     */
    public function getProductStockAcrossBranches(string $productId): array
    {
        $result = [];

        try {
            $producto = getProductById($productId);
            $sucursales = $this->getAvailableBranches();

            foreach ($sucursales as $sucursal) {
                $branchDb = $this->resolveBranchConnectionBySucursalId($sucursal['id']);
                if ($branchDb === null) {
                    continue;
                }

                $stock = queryOne($branchDb, '
                    SELECT cantidad_real
                    FROM stock
                    WHERE producto_id = :pid AND sucursal_id = :sid
                    LIMIT 1
                ', [
                    ':pid' => $productId,
                    ':sid' => $sucursal['id'],
                ]);

                $precio = queryOne(dbMatriz(), '
                    SELECT precio_efectivo, precio_tarjeta
                    FROM precios_sucursal
                    WHERE producto_id = :pid AND sucursal_id = :sid
                    LIMIT 1
                ', [
                    ':pid' => $productId,
                    ':sid' => $sucursal['id'],
                ]);

                $result[] = [
                    'producto_id'      => $productId,
                    'sku'              => $producto['sku'] ?? null,
                    'nombre'           => $producto['nombre'] ?? 'Producto no encontrado',
                    'sucursal_id'      => $sucursal['id'],
                    'sucursal_nombre'  => $sucursal['nombre'],
                    'ciudad'           => $sucursal['ciudad'] ?? null,
                    'region'           => $sucursal['region'] ?? null,
                    'cantidad_real'    => (int) ($stock['cantidad_real'] ?? 0),
                    'precio_efectivo'  => isset($precio['precio_efectivo']) ? (int) $precio['precio_efectivo'] : null,
                    'precio_tarjeta'   => isset($precio['precio_tarjeta']) ? (int) $precio['precio_tarjeta'] : null,
                ];
            }

            return $result;
        } catch (PDOException $e) {
            error_log('[InventorySyncService::getProductStockAcrossBranches] ' . $e->getMessage());
            return [];
        }
    }

    // ── Disponibilidad ────────────────────────────────────────────────────────

    /**
     * Verifica si un producto tiene al menos cierta cantidad disponible
     * en una sucursal específica.
     *
     * @param string $productId UUID producto
     * @param string $branchId  UUID sucursal
     * @param int    $requiredQty cantidad mínima requerida
     */
    public function checkAvailabilityInBranch(string $productId, string $branchId, int $requiredQty = 1): bool
    {
        try {
            $branchDb = $this->resolveBranchConnectionBySucursalId($branchId);
            if ($branchDb === null) {
                return false;
            }

            $result = queryOne($branchDb, '
                SELECT cantidad_real
                FROM stock
                WHERE producto_id = :pid AND sucursal_id = :sid
                LIMIT 1
            ', [
                ':pid' => $productId,
                ':sid' => $branchId,
            ]);

            return (int) ($result['cantidad_real'] ?? 0) >= $requiredQty;
        } catch (PDOException $e) {
            error_log('[InventorySyncService::checkAvailabilityInBranch] ' . $e->getMessage());
            return false;
        }
    }

    // ── Sucursales ────────────────────────────────────────────────────────────

    /**
     * Obtiene sucursales desde db_matriz.
     */
    public function getAvailableBranches(): array
    {
        try {
            return queryAll(dbMatriz(), '
                SELECT s.id, s.nombre, s.es_bodega_central,
                       c.nombre AS ciudad,
                       r.nombre AS region
                FROM sucursales s
                JOIN ciudades c ON c.id = s.ciudad_id
                JOIN regiones r ON r.id = c.region_id
                ORDER BY s.nombre ASC
            ');
        } catch (PDOException $e) {
            error_log('[InventorySyncService::getAvailableBranches] ' . $e->getMessage());
            return [];
        }
    }

    // ── Ajuste stock local ────────────────────────────────────────────────────

    /**
     * Ajusta stock en la DB local actual.
     * $delta puede ser positivo (ingreso) o negativo (descuento).
     */
    public function adjustLocalStock(string $productoId, string $sucursalId, int $delta): bool
    {
        try {
            $pdo = db();

            $existing = queryOne($pdo, '
                SELECT id, cantidad_real
                FROM stock
                WHERE producto_id = :pid AND sucursal_id = :sid
                LIMIT 1
            ', [':pid' => $productoId, ':sid' => $sucursalId]);

            if ($existing === null) {
                if ($delta < 0) {
                    return false;
                }

                $pdo->prepare('
                    INSERT INTO stock (id, sucursal_id, producto_id, cantidad_real)
                    VALUES (:id, :sid, :pid, :qty)
                ')->execute([
                    ':id'  => generateUuid(),
                    ':sid' => $sucursalId,
                    ':pid' => $productoId,
                    ':qty' => $delta,
                ]);

                return true;
            }

            $nuevoStock = (int) $existing['cantidad_real'] + $delta;
            if ($nuevoStock < 0) {
                return false;
            }

            $pdo->prepare('
                UPDATE stock
                SET cantidad_real = :qty
                WHERE id = :id
            ')->execute([
                ':qty' => $nuevoStock,
                ':id'  => $existing['id'],
            ]);

            return true;
        } catch (PDOException $e) {
            error_log('[InventorySyncService::adjustLocalStock] ' . $e->getMessage());
            return false;
        }
    }

    // ── Conexión a sucursal por UUID ──────────────────────────────────────────

    /**
     * Resuelve conexión PDO a la DB local de una sucursal usando su UUID.
     * Asume que nombre de sucursal contiene Norte/Sur/Centro.
     * Si prefieres, luego lo mejoramos con una tabla de mapeo explícita.
     */
    private function resolveBranchConnectionBySucursalId(string $sucursalId): ?PDO
    {
        $sucursal = queryOne(dbMatriz(), '
            SELECT id, nombre
            FROM sucursales
            WHERE id = :id
            LIMIT 1
        ', [':id' => $sucursalId]);

        if ($sucursal === null) {
            return null;
        }

        $nombre = mb_strtolower($sucursal['nombre']);

        if (str_contains($nombre, 'norte')) {
            $cfg = $this->branchMap[Database::NODE_NORTE];
        } elseif (str_contains($nombre, 'sur')) {
            $cfg = $this->branchMap[Database::NODE_SUR];
        } elseif (str_contains($nombre, 'centro')) {
            $cfg = $this->branchMap[Database::NODE_CENTRO];
        } else {
            return null;
        }

        return $this->connectBranch($cfg['host'], $cfg['db']);
    }

    private function connectBranch(string $host, string $dbName): ?PDO
    {
        try {
            return new PDO(
                "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
                getenv('DB_USER') ?: 'appuser',
                getenv('DB_PASSWORD') ?: 'apppassword',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]
            );
        } catch (PDOException $e) {
            error_log("[InventorySyncService::connectBranch] {$host}/{$dbName}: " . $e->getMessage());
            return null;
        }
    }
}