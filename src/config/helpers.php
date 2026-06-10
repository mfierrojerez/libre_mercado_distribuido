<?php
// src/config/helpers.php

function queryAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function queryOne(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function queryAllWithLimit(PDO $pdo, string $sql, array $params = [], ?int $limit = null): array
{
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    if ($limit !== null) {
        $stmt->bindValue(':lim', max(1, (int) $limit), PDO::PARAM_INT);
    }

    $stmt->execute();
    return $stmt->fetchAll();
}

// ── Nodo ──────────────────────────────────────────────────────────────────────

function isMatrixNode(): bool
{
    return Database::getInstance()->isMatrixNode();
}

function isNodeHealthy(): bool
{
    try {
        dbMatriz()->query('SELECT 1');

        foreach (dbNodos() as $pdo) {
            $pdo->query('SELECT 1');
        }

        return true;
    } catch (Throwable $e) {
        error_log('[isNodeHealthy] ' . $e->getMessage());
        return false;
    }
}

// ── Productos (db_matriz) ─────────────────────────────────────────────────────

function getProductos(): array
{
    $sql = '
        SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos
        FROM productos p
        WHERE p.activo = 1
        ORDER BY p.nombre ASC
        LIMIT 100
    ';

    try {
        return queryAll(dbMatriz(), $sql);
    } catch (PDOException $e) {
        error_log('[getProductos] ' . $e->getMessage());
        return [];
    }
}

function getProductById(string $id): ?array
{
    $sql = '
        SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos
        FROM productos p
        WHERE p.id = :id
          AND p.activo = 1
        LIMIT 1
    ';

    try {
        return queryOne(dbMatriz(), $sql, [':id' => $id]);
    } catch (PDOException $e) {
        error_log('[getProductById] ' . $e->getMessage());
        return null;
    }
}

function getProductoConPrecio(string $productoId, string $sucursalId): ?array
{
    $sql = '
        SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos,
               ps.precio_efectivo, ps.precio_tarjeta
        FROM productos p
        LEFT JOIN precios_sucursal ps
               ON ps.producto_id = p.id AND ps.sucursal_id = :sucursal_id
        WHERE p.id = :producto_id
          AND p.activo = 1
        LIMIT 1
    ';

    try {
        return queryOne(dbMatriz(), $sql, [
            ':producto_id' => $productoId,
            ':sucursal_id' => $sucursalId,
        ]);
    } catch (PDOException $e) {
        error_log('[getProductoConPrecio] ' . $e->getMessage());
        return null;
    }
}

// ── Sucursales (db_matriz) ────────────────────────────────────────────────────

function getAllBranches(): array
{
    try {
        return queryAll(dbMatriz(), '
            SELECT s.id, s.nombre, s.codigo_nodo, s.es_bodega_central, s.activa,
                   c.nombre AS ciudad, r.nombre AS region
            FROM sucursales s
            JOIN ciudades c ON c.id = s.ciudad_id
            JOIN regiones r ON r.id = c.region_id
            WHERE s.activa = 1
            ORDER BY s.nombre ASC
        ');
    } catch (PDOException $e) {
        error_log('[getAllBranches] ' . $e->getMessage());
        return [];
    }
}

function getBranchById(string $sucursalId): ?array
{
    try {
        return queryOne(dbMatriz(), '
            SELECT s.id, s.nombre, s.codigo_nodo, s.es_bodega_central, s.activa,
                   c.nombre AS ciudad, r.nombre AS region
            FROM sucursales s
            JOIN ciudades c ON c.id = s.ciudad_id
            JOIN regiones r ON r.id = c.region_id
            WHERE s.id = :id
            LIMIT 1
        ', [':id' => $sucursalId]);
    } catch (PDOException $e) {
        error_log('[getBranchById] ' . $e->getMessage());
        return null;
    }
}

function getNodeFromSucursalId(string $sucursalId): string
{
    $branch = getBranchById($sucursalId);

    if ($branch === null) {
        throw new Exception("Sucursal [{$sucursalId}] no encontrada.");
    }

    $node = $branch['codigo_nodo'] ?? null;

    if ($node === null || $node === '') {
        throw new Exception("Sucursal [{$sucursalId}] sin codigo_nodo.");
    }

    return $node;
}

function dbBySucursalId(string $sucursalId): PDO
{
    return dbSucursal(getNodeFromSucursalId($sucursalId));
}

// ── Inventario / Stock (DB local por sucursal) ───────────────────────────────

function getInventario(string $sucursalId): array
{
    try {
        $pdo = dbBySucursalId($sucursalId);

        $stocks = queryAll($pdo, '
            SELECT s.producto_id, s.cantidad_real
            FROM stock s
            WHERE s.sucursal_id = :sucursal_id
        ', [':sucursal_id' => $sucursalId]);

        if (empty($stocks)) {
            return [];
        }

        $ids = array_column($stocks, 'producto_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $productos = queryAll(
            dbMatriz(),
            "SELECT id, sku, nombre FROM productos WHERE id IN ($placeholders)",
            $ids
        );

        $prodIdx = array_column($productos, null, 'id');

        return array_map(function ($s) use ($prodIdx) {
            $p = $prodIdx[$s['producto_id']] ?? [];
            return array_merge($s, [
                'sku'    => $p['sku'] ?? null,
                'nombre' => $p['nombre'] ?? 'Producto no encontrado',
            ]);
        }, $stocks);
    } catch (Throwable $e) {
        error_log('[getInventario] ' . $e->getMessage());
        return [];
    }
}

function getStockProducto(string $productoId, string $sucursalId): int
{
    try {
        $branch = getBranchById($sucursalId);

        if ($branch === null) {
            error_log('[getStockProducto] Sucursal no encontrada: ' . $sucursalId);
            return 0;
        }

        $node = (string) ($branch['codigo_nodo'] ?? '');

        if ($node === '') {
            error_log('[getStockProducto] Sucursal sin codigo_nodo: ' . $sucursalId);
            return 0;
        }

        $pdo = dbSucursal($node);

        $row = queryOne($pdo, '
            SELECT cantidad_real
            FROM stock
            WHERE producto_id = :pid AND sucursal_id = :sid
            LIMIT 1
        ', [
            ':pid' => $productoId,
            ':sid' => $sucursalId,
        ]);

        $cantidad = (int) ($row['cantidad_real'] ?? 0);

        error_log(sprintf(
            '[getStockProducto] producto=%s sucursal=%s nodo=%s stock=%d',
            $productoId,
            $sucursalId,
            $node,
            $cantidad
        ));

        return $cantidad;
    } catch (Throwable $e) {
        error_log('[getStockProducto] ' . $e->getMessage());
        return 0;
    }
}

function getStockProductoPorSucursal(string $productoId): array
{
    $branches = getAllBranches();
    $result = [];

    foreach ($branches as $branch) {
        $sid = $branch['id'];
        $node = $branch['codigo_nodo'] ?? null;

        if (!$node) {
            $result[] = [
                'node'        => null,
                'sucursal_id' => $sid,
                'sucursal'    => $branch['nombre'],
                'cantidad'    => 0,
            ];
            continue;
        }

        try {
            $row = queryOne(dbSucursal($node), '
                SELECT cantidad_real
                FROM stock
                WHERE producto_id = :pid AND sucursal_id = :sid
                LIMIT 1
            ', [
                ':pid' => $productoId,
                ':sid' => $sid,
            ]);

            $result[] = [
                'node'        => $node,
                'sucursal_id' => $sid,
                'sucursal'    => $branch['nombre'],
                'cantidad'    => (int) ($row['cantidad_real'] ?? 0),
            ];
        } catch (Throwable $e) {
            error_log('[getStockProductoPorSucursal][' . $node . '] ' . $e->getMessage());

            $result[] = [
                'node'        => $node,
                'sucursal_id' => $sid,
                'sucursal'    => $branch['nombre'],
                'cantidad'    => 0,
            ];
        }
    }

    return $result;
}

function getStockTotalProducto(string $productoId): int
{
    $stocks = getStockProductoPorSucursal($productoId);
    return array_sum(array_column($stocks, 'cantidad'));
}

// ── Carrito (DB matriz) ───────────────────────────────────────────────────────

function getCarrito(?string $sessionToken = null, ?string $clienteId = null): array
{
    $empty = [
        'carrito_id'  => null,
        'items'       => [],
        'total_items' => 0,
        'subtotal'    => 0.0,
    ];

    if ($sessionToken === null && $clienteId === null) {
        return $empty;
    }

    try {
        $pdo = dbMatriz();

        if ($clienteId !== null) {
            $carrito = queryOne(
                $pdo,
                'SELECT id FROM carritos WHERE cliente_id = :cid LIMIT 1',
                [':cid' => $clienteId]
            );
        } else {
            $carrito = queryOne(
                $pdo,
                'SELECT id FROM carritos WHERE session_token = :tok LIMIT 1',
                [':tok' => $sessionToken]
            );
        }

        if ($carrito === null) {
            return $empty;
        }

        $carritoId = $carrito['id'];

        $items = queryAll($pdo, '
            SELECT
                ci.id,
                ci.carrito_id,
                ci.producto_id,
                ci.sucursal_id,
                ci.cantidad,
                p.sku,
                p.nombre,
                p.descripcion,
                s.nombre AS sucursal_nombre,
                s.codigo_nodo,
                ps.precio_efectivo,
                ps.precio_tarjeta
            FROM carrito_items ci
            JOIN productos p
              ON p.id = ci.producto_id
            JOIN sucursales s
              ON s.id = ci.sucursal_id
            LEFT JOIN precios_sucursal ps
              ON ps.producto_id = ci.producto_id
             AND ps.sucursal_id = ci.sucursal_id
            WHERE ci.carrito_id = :cid
            ORDER BY p.nombre ASC
        ', [':cid' => $carritoId]);

        $totalItems = array_sum(array_map(
            fn(array $item): int => (int) ($item['cantidad'] ?? 0),
            $items
        ));

        $subtotal = calculateCartSubtotal($items);

        return [
            'carrito_id'  => $carritoId,
            'items'       => $items,
            'total_items' => $totalItems,
            'subtotal'    => $subtotal,
        ];
    } catch (PDOException $e) {
        error_log('[getCarrito] ' . $e->getMessage());
        return $empty;
    }
}

function calculateCartSubtotal(array $items): float
{
    if (empty($items)) {
        return 0.0;
    }

    $subtotal = 0.0;

    foreach ($items as $item) {
        $precio = 0.0;

        if (isset($item['precio_unitario_pagado'])) {
            $precio = (float) $item['precio_unitario_pagado'];
        } elseif (isset($item['precio_efectivo'])) {
            $precio = (float) $item['precio_efectivo'];
        }

        $subtotal += $precio * (int) ($item['cantidad'] ?? 0);
    }

    return round($subtotal, 2);
}

function addToCarrito(
    string $productoId,
    string $sucursalId,
    int $cantidad,
    ?string $sessionToken = null,
    ?string $clienteId = null
): bool {
    if ($cantidad <= 0) {
        return false;
    }

    try {
        $stockDisponible = getStockProducto($productoId, $sucursalId);
        if ($stockDisponible < $cantidad) {
            return false;
        }

        $pdo = dbMatriz();
        $pdo->beginTransaction();

        if ($clienteId !== null) {
            $carrito = queryOne(
                $pdo,
                'SELECT id FROM carritos WHERE cliente_id = :cid LIMIT 1',
                [':cid' => $clienteId]
            );
        } else {
            $carrito = queryOne(
                $pdo,
                'SELECT id FROM carritos WHERE session_token = :tok LIMIT 1',
                [':tok' => $sessionToken]
            );
        }

        if ($carrito === null) {
            $carritoId = generateUuid();

            $pdo->prepare('
                INSERT INTO carritos (id, session_token, cliente_id)
                VALUES (:id, :tok, :cid)
            ')->execute([
                ':id'  => $carritoId,
                ':tok' => $sessionToken,
                ':cid' => $clienteId,
            ]);
        } else {
            $carritoId = $carrito['id'];
        }

        $existing = queryOne($pdo, '
            SELECT id, cantidad
            FROM carrito_items
            WHERE carrito_id = :cid
              AND producto_id = :pid
              AND sucursal_id = :sid
            LIMIT 1
        ', [
            ':cid' => $carritoId,
            ':pid' => $productoId,
            ':sid' => $sucursalId,
        ]);

        if ($existing !== null) {
            $nuevaCantidad = (int) $existing['cantidad'] + $cantidad;

            if ($nuevaCantidad > $stockDisponible) {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare('
                UPDATE carrito_items
                SET cantidad = :qty
                WHERE id = :id
            ')->execute([
                ':qty' => $nuevaCantidad,
                ':id'  => $existing['id'],
            ]);
        } else {
            $pdo->prepare('
                INSERT INTO carrito_items (id, carrito_id, producto_id, sucursal_id, cantidad)
                VALUES (:id, :cid, :pid, :sid, :qty)
            ')->execute([
                ':id'  => generateUuid(),
                ':cid' => $carritoId,
                ':pid' => $productoId,
                ':sid' => $sucursalId,
                ':qty' => $cantidad,
            ]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[addToCarrito] ' . $e->getMessage());
        return false;
    }
}

// ── Pedidos (DB local por nodo) ───────────────────────────────────────────────

function getPedidosByNode(string $node, ?string $clienteId = null, int $limit = 50): array
{
    try {
        $pdo = dbSucursal($node);
        $limit = max(1, (int) $limit);

        if ($clienteId !== null) {
            $sql = "
                SELECT *
                FROM pedidos
                WHERE cliente_id = :cid
                ORDER BY created_at DESC
                LIMIT {$limit}
            ";

            return queryAll($pdo, $sql, [':cid' => $clienteId]);
        }

        $sql = "
            SELECT *
            FROM pedidos
            ORDER BY created_at DESC
            LIMIT {$limit}
        ";

        return queryAll($pdo, $sql, []);
    } catch (Throwable $e) {
        error_log('[getPedidosByNode][' . $node . '] ' . $e->getMessage());
        return [];
    }
}

function getPedidos(?string $clienteId = null, int $limit = 50): array
{
    $all = [];

    foreach (dbNodos() as $node => $pdo) {
        try {
            $rows = getPedidosByNode($node, $clienteId, $limit);
            $all = array_merge($all, $rows);
        } catch (Throwable $e) {
            error_log('[getPedidos][' . $node . '] ' . $e->getMessage());
        }
    }

    usort($all, function ($a, $b) {
        return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
    });

    return array_slice($all, 0, max(1, (int) $limit));
}

// ── Usuarios (db_matriz) ──────────────────────────────────────────────────────

function getUsers(int $limit = 100): array
{
    try {
        $sql = '
            SELECT u.id, u.email, u.rol, u.created_at,
                   c.nombre, c.apellido, c.rut, c.telefono
            FROM usuarios u
            LEFT JOIN clientes c ON c.usuario_id = u.id
            ORDER BY u.created_at DESC
            LIMIT :lim
        ';

        return queryAllWithLimit(dbMatriz(), $sql, [], $limit);
    } catch (PDOException $e) {
        error_log('[getUsers] ' . $e->getMessage());
        return [];
    }
}

// ── Utilidades generales ──────────────────────────────────────────────────────

function generateUuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function page(string $name): string
{
    return __DIR__ . '/../templates/pages/' . ltrim($name, '/') . '.php';
}

// ── Helpers de vista / URL ────────────────────────────────────────────────────

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function appBaseUrl(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    $dir = rtrim($dir, '/');

    return ($dir === '/' || $dir === '.') ? '' : $dir;
}

function url(string $path = ''): string
{
    $base = appBaseUrl();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

function asset(string $path): string
{
    return url($path);
}

function image_asset(string $path): string
{
    return url('images/' . ltrim($path, '/'));
}

function jsonForHtml(mixed $value): string
{
    $json = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    return $json === false ? 'null' : $json;
}

function currentUserId(): ?string
{
    $id = $_SESSION['usuario_id'] ?? null;
    return is_string($id) && $id !== '' ? $id : null;
}

function currentClienteId(): ?string
{
    $id = $_SESSION['cliente_id'] ?? null;
    return is_string($id) && $id !== '' ? $id : null;
}

function isAuthenticated(): bool
{
    return currentUserId() !== null && currentClienteId() !== null;
}

function getFlash(string $type): ?string
{
    $key = 'flash_' . $type;

    if (!isset($_SESSION[$key])) {
        return null;
    }

    $message = (string) $_SESSION[$key];
    unset($_SESSION[$key]);

    return $message;
}

function currentUserName(): ?string
{
    $name = $_SESSION['nombre'] ?? null;
    return is_string($name) && $name !== '' ? $name : null;
}