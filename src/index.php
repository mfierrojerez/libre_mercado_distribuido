<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/node-config.php';

// Contexto básico, sin forzar conexión local ambigua
$nodeInfo = Database::getInstance()->getNodeInfo();
$config   = getNodeConfig();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=()');

// Asegurar token de carrito anónimo
if (!isset($_SESSION['cart_token'])) {
    $_SESSION['cart_token'] = generateUuid();
}

// Resolver sucursal activa por defecto
if (empty($_SESSION['sucursal_id'])) {
    $defaultSucursalId = getenv('DEFAULT_SUCURSAL_ID') ?: '';

    if ($defaultSucursalId !== '') {
        $_SESSION['sucursal_id'] = $defaultSucursalId;
    } else {
        $row = queryOne(dbMatriz(), '
            SELECT id
            FROM sucursales
            WHERE activa = 1
            ORDER BY es_bodega_central DESC, nombre ASC
            LIMIT 1
        ');

        $_SESSION['sucursal_id'] = $row['id'] ?? '';
    }
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}

/*
|--------------------------------------------------------------------------
| Excluir assets estáticos del front controller
|--------------------------------------------------------------------------
|
| Mantiene Bootstrap y app.js como requerimiento, pero evita que sus requests
| pasen por la lógica de negocio si el servidor no los resuelve antes.
|
*/
if (preg_match('#\.(?:js|mjs|css|map|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2|ttf|eot)$#i', $path)) {
    http_response_code(404);
    exit;
}

$path = trim($path, '/');
$pathParts = $path === '' ? [] : explode('/', $path);

$route = $pathParts[0] ?? '';
$id    = $pathParts[1] ?? null;

// Debug controlado solo contra matriz
try {
    $testMatriz = dbMatriz()->query('SELECT 1')->fetchColumn();
    error_log('[DEBUG] dbMatriz OK: ' . var_export($testMatriz, true));

    $countProductos = dbMatriz()->query('SELECT COUNT(*) FROM productos')->fetchColumn();
    error_log('[DEBUG] productos en matriz: ' . var_export($countProductos, true));

    $countSucursales = dbMatriz()->query('SELECT COUNT(*) FROM sucursales')->fetchColumn();
    error_log('[DEBUG] sucursales en matriz: ' . var_export($countSucursales, true));

    $stmt = dbMatriz()->query('SELECT id, sku, nombre FROM productos LIMIT 5');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log('[DEBUG] primeros productos: ' . json_encode($rows, JSON_UNESCAPED_UNICODE));
} catch (Throwable $e) {
    error_log('[DEBUG] Error dbMatriz: ' . $e->getMessage());
}

error_log(sprintf(
    '[INIT] Nodo: %s | Rol: %s | %s',
    $nodeInfo['type'],
    $config['role'],
    $config['description']
));

/*
|--------------------------------------------------------------------------
| Procesamiento de rutas POST
|--------------------------------------------------------------------------
*/
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    switch ($route) {
        case 'cart':
            require_once __DIR__ . '/controllers/CartController.php';
            $controller = new CartController();

            switch ($pathParts[1] ?? '') {
                case 'add':
                    $controller->handleAddRequest($_POST);
                    break;

                case 'update':
                    $controller->handleUpdateRequest($_POST);
                    break;

                case 'remove':
                    $controller->handleRemoveRequest($_POST);
                    break;

                case 'clear':
                    $controller->handleClearRequest();
                    break;

                default:
                    http_response_code(404);
                    echo 'Acción de carrito no encontrada';
                    exit;
            }
            break;

        case 'orders':
            require_once __DIR__ . '/controllers/OrdersController.php';
            $controller = new OrdersController();

            switch ($pathParts[1] ?? '') {
                case 'create':
                    $controller->handleCreateRequest($_POST);
                    break;

                default:
                    http_response_code(404);
                    echo 'Acción de pedido no encontrada';
                    exit;
            }
            break;

        case 'login':
            require_once __DIR__ . '/controllers/AuthController.php';
            $controller = new AuthController();
            $controller->handleLoginRequest($_POST);
            break;

        case 'register':
            require_once __DIR__ . '/controllers/AuthController.php';
            $controller = new AuthController();
            $controller->handleRegisterRequest($_POST);
            break;

        case 'logout':
            require_once __DIR__ . '/controllers/AuthController.php';
            $controller = new AuthController();
            $controller->logout();
            break;

        default:
            http_response_code(405);
            echo 'Método no permitido';
            exit;
    }
}

$data = [
    'node_info' => $nodeInfo,
    'config'    => $config,
    'pageTitle' => 'SisDist Marketplace',
];

$content = page('home');

$sessionToken = $_SESSION['cart_token'] ?? null;
$clienteId    = $_SESSION['cliente_id'] ?? null;
$sucursalId   = $_SESSION['sucursal_id'] ?? '';

// Rutas simples
$staticRoutes = [];

// Rutas con datos
$dataRoutes = [
    '' => function () use ($sucursalId): array {
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
},

    'products' => function () use ($sucursalId): array {
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
    },

    'cart' => function (): array {
        require_once __DIR__ . '/controllers/CartController.php';
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
    },

    'inventory' => function (): array {
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
},

    'checkout' => function () use ($sessionToken, $clienteId): array {
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

        require_once __DIR__ . '/controllers/CartController.php';
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
    },

    'reviews' => function (): array {
    if (!isAuthenticated()) {
        $_SESSION['flash_error'] = 'Debes iniciar sesión para ver tus pedidos';
        header('Location: ' . url('login'));
        exit;
    }

    require_once __DIR__ . '/controllers/OrdersController.php';
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
},

    'login' => function (): array {
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        return $controller->showLogin();
    },

    'register' => function (): array {
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController();
        return $controller->showRegister();
    },

    'admin-orders' => function (): array {
    if (($_SESSION['rol'] ?? '') !== 'admin') {
        http_response_code(403);
        return [
            'view'  => '403',
            'title' => 'No autorizado',
            'data'  => [],
        ];
    }

    require_once __DIR__ . '/controllers/OrdersController.php';
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
},

];

// Resolver rutas GET
if (array_key_exists($route, $dataRoutes)) {
    $definition = $dataRoutes[$route]();
    $content = page($definition['view']);
    $data['pageTitle'] = $definition['title'];
    $data = array_merge($data, $definition['data']);
} elseif (array_key_exists($route, $staticRoutes)) {
    $definition = $staticRoutes[$route];
    $content = page($definition['view']);
    $data['pageTitle'] = $definition['title'];
} else {
    switch ($route) {
        case 'products':
            if ($id === null || $id === '') {
                http_response_code(404);
                $content = page('404');
                $data['pageTitle'] = 'Producto no encontrado';
                break;
            }

            $producto = ($sucursalId !== '')
                ? getProductoConPrecio($id, $sucursalId)
                : getProductById($id);

            if (!$producto) {
                http_response_code(404);
                $content = page('404');
                $data['pageTitle'] = 'Producto no encontrado';
                break;
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

            $content = page('product-detail');
            $data['pageTitle'] = $producto['nombre'] ?? 'Detalle de producto';
            $data['producto'] = $producto;
            $data['precios_otros'] = $preciosOtros;
            break;

        default:
            http_response_code(404);
            $content = page('404');
            $data['pageTitle'] = 'No encontrado';
            break;
    }
}

error_log('current node type: ' . currentNodeType());
error_log('is matrix node?: ' . (shouldUseMatrixNode() ? 'YES' : 'NO'));

require_once __DIR__ . '/templates/layout.php';