<?php
declare(strict_types=1);

final class PageController
{
    public function home(string $sucursalId): array
    {
        $productos = queryAll(dbMatriz(), '
            SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos,
                   ps.precio_efectivo, ps.precio_tarjeta
            FROM productos p
            LEFT JOIN precios_sucursal ps
                ON ps.producto_id = p.id AND ps.sucursal_id = :sid
            WHERE p.activo = 1
            ORDER BY p.nombre ASC
            LIMIT 12
        ', [':sid' => $sucursalId]);

        $productos = array_map(function (array $p): array {
            $p['stock_por_sucursal'] = getStockProductoPorSucursal($p['id']);
            $p['stock_total'] = getStockTotalProducto($p['id']);
            return $p;
        }, $productos);

        error_log('[HOME FINAL] productos_render=' . json_encode($productos, JSON_UNESCAPED_UNICODE));

        return [
            'view'  => 'home',
            'title' => 'Inicio',
            'data'  => [
                'productos' => $productos,
            ],
        ];
    }

    public function products(string $sucursalId): array
    {
        $query = trim((string) ($_GET['search'] ?? ''));
        $limit = max(1, (int) getMaxProductsPerPage());

        if ($query !== '') {
            $like = '%' . $query . '%';

            $sql = '
                SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos,
                       ps.precio_efectivo, ps.precio_tarjeta
                FROM productos p
                LEFT JOIN precios_sucursal ps
                    ON ps.producto_id = p.id AND ps.sucursal_id = :sid
                WHERE p.activo = 1
                  AND (p.nombre LIKE :q OR p.sku LIKE :q2)
                ORDER BY p.nombre ASC
                LIMIT :lim
            ';

            $productos = queryAllWithLimit(dbMatriz(), $sql, [
                ':sid' => $sucursalId,
                ':q'   => $like,
                ':q2'  => $like,
            ], $limit);
        } else {
            $sql = '
                SELECT p.id, p.sku, p.nombre, p.descripcion, p.peso_gramos,
                       ps.precio_efectivo, ps.precio_tarjeta
                FROM productos p
                LEFT JOIN precios_sucursal ps
                    ON ps.producto_id = p.id AND ps.sucursal_id = :sid
                WHERE p.activo = 1
                ORDER BY p.nombre ASC
                LIMIT :lim
            ';

            $productos = queryAllWithLimit(dbMatriz(), $sql, [
                ':sid' => $sucursalId,
            ], $limit);
        }

        $productos = array_map(function (array $p): array {
            $p['stock_por_sucursal'] = getStockProductoPorSucursal($p['id']);
            $p['stock_total'] = getStockTotalProducto($p['id']);
            return $p;
        }, $productos);

        return [
            'view'  => 'products',
            'title' => 'Productos',
            'data'  => [
                'productos'    => $productos,
                'search_query' => $query,
            ],
        ];
    }

    public function cart(): array
    {
        require_once __DIR__ . '/CartController.php';
        $controller = new CartController();
        $resultado = $controller->index();

        return [
            'view'  => 'cart',
            'title' => 'Carrito',
            'data'  => [
                'carrito' => $resultado['carrito'] ?? [
                    'carrito_id'  => null,
                    'items'       => [],
                    'total_items' => 0,
                    'subtotal'    => 0,
                ],
            ],
        ];
    }

    public function inventory(): array
    {
        if (($_SESSION['rol'] ?? '') !== 'admin') {
            http_response_code(403);
            return [
                'view'  => '403',
                'title' => 'No autorizado',
                'data'  => [],
            ];
        }

        $inventario = queryAll(dbMatriz(), '
            SELECT p.id, p.sku, p.nombre
            FROM productos p
            WHERE p.activo = 1
            ORDER BY p.nombre ASC
        ');

        $inventario = array_map(function (array $producto): array {
            $stockPorSucursal = getStockProductoPorSucursal($producto['id']);

            $stockIndexado = [
                'Norte'  => 0,
                'Sur'    => 0,
                'Centro' => 0,
            ];

            foreach ($stockPorSucursal as $stock) {
                $node = strtolower((string) ($stock['node'] ?? ''));
                $cantidad = (int) ($stock['cantidad'] ?? 0);

                if ($node === 'norte') {
                    $stockIndexado['Norte'] = $cantidad;
                } elseif ($node === 'sur') {
                    $stockIndexado['Sur'] = $cantidad;
                } elseif ($node === 'centro') {
                    $stockIndexado['Centro'] = $cantidad;
                }
            }

            $producto['stock_norte'] = $stockIndexado['Norte'];
            $producto['stock_sur'] = $stockIndexado['Sur'];
            $producto['stock_centro'] = $stockIndexado['Centro'];
            $producto['stock_total'] = $producto['stock_norte'] + $producto['stock_sur'] + $producto['stock_centro'];

            return $producto;
        }, $inventario);

        return [
            'view'  => 'inventory',
            'title' => 'Inventario',
            'data'  => [
                'inventario' => $inventario,
            ],
        ];
    }

    public function checkout(?string $sessionToken, ?string $clienteId): array
    {
        $carrito = getCarrito($sessionToken, $clienteId);

        $direcciones = [];
        if (!empty($clienteId)) {
            $direcciones = queryAll(dbMatriz(), '
                SELECT d.id, d.calle, d.numero, d.depto_block, c.nombre AS ciudad_nombre
                FROM direcciones_despacho d
                JOIN ciudades c ON c.id = d.ciudad_id
                WHERE d.cliente_id = :cid
                ORDER BY d.id ASC
            ', [':cid' => $clienteId]);
        }

        require_once __DIR__ . '/CartController.php';
        $cartController = new CartController();
        $resultadoCarrito = $cartController->index();

        return [
            'view'  => 'checkout',
            'title' => 'Checkout',
            'data'  => [
                'carrito'     => $resultadoCarrito['carrito'] ?? $carrito,
                'direcciones' => $direcciones,
            ],
        ];
    }

    public function reviews(): array
    {
        if (!isAuthenticated()) {
            $_SESSION['flash_error'] = 'Debes iniciar sesión para ver tus pedidos';
            header('Location: ' . url('login'));
            exit;
        }

        require_once __DIR__ . '/OrdersController.php';
        $controller = new OrdersController();
        $resultado = $controller->index();

        return [
            'view'  => 'reviews',
            'title' => (($_SESSION['rol'] ?? '') === 'admin') ? 'Todos los pedidos' : 'Mis pedidos',
            'data'  => [
                'pedidos'       => $resultado['pedidos'] ?? [],
                'error'         => $resultado['error'] ?? null,
                'is_admin_view' => (($_SESSION['rol'] ?? '') === 'admin'),
            ],
        ];
    }

    public function login(): array
    {
        require_once __DIR__ . '/AuthController.php';
        $controller = new AuthController();
        return $controller->showLogin();
    }

    public function register(): array
    {
        require_once __DIR__ . '/AuthController.php';
        $controller = new AuthController();
        return $controller->showRegister();
    }

    public function adminOrders(): array
    {
        if (($_SESSION['rol'] ?? '') !== 'admin') {
            http_response_code(403);
            return [
                'view'  => '403',
                'title' => 'No autorizado',
                'data'  => [],
            ];
        }

        require_once __DIR__ . '/OrdersController.php';
        $controller = new OrdersController();
        $resultado = $controller->index();

        return [
            'view'  => 'reviews',
            'title' => 'Todos los pedidos',
            'data'  => [
                'pedidos'       => $resultado['pedidos'] ?? [],
                'error'         => $resultado['error'] ?? null,
                'is_admin_view' => true,
            ],
        ];
    }

    public function productDetail(string $id, string $sucursalId): array
    {
        if ($id === '') {
            http_response_code(404);
            return [
                'view'  => '404',
                'title' => 'Producto no encontrado',
                'data'  => [],
            ];
        }

        $producto = ($sucursalId !== '')
            ? getProductoConPrecio($id, $sucursalId)
            : getProductById($id);

        if (!$producto) {
            http_response_code(404);
            return [
                'view'  => '404',
                'title' => 'Producto no encontrado',
                'data'  => [],
            ];
        }

        $producto['stock_por_sucursal'] = getStockProductoPorSucursal($id);
        $producto['stock_total'] = getStockTotalProducto($id);

        $preciosOtros = [];
        if ($sucursalId !== '') {
            $preciosOtros = queryAll(dbMatriz(), '
                SELECT s.nombre AS sucursal, ps.precio_efectivo, ps.precio_tarjeta
                FROM precios_sucursal ps
                JOIN sucursales s ON s.id = ps.sucursal_id
                WHERE ps.producto_id = :pid AND ps.sucursal_id != :sid
                ORDER BY s.nombre ASC
            ', [':pid' => $id, ':sid' => $sucursalId]);
        }

        return [
            'view'  => 'product-detail',
            'title' => $producto['nombre'] ?? 'Detalle de producto',
            'data'  => [
                'producto'      => $producto,
                'precios_otros' => $preciosOtros,
            ],
        ];
    }

    public function notFound(string $title = 'No encontrado'): array
    {
        http_response_code(404);
        return [
            'view'  => '404',
            'title' => $title,
            'data'  => [],
        ];
    }
}