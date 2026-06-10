# Guía para Agente de Código - SisDist Marketplace

## 📋 Introducción

Esta guía documenta el estado actual del proyecto **SisDist Marketplace**, un sistema de comercio electrónico distribuido con arquitectura multi-nodo. El agente debe seguir estas especificaciones para mantener consistencia y evitar errores comunes.

---

## 🏗️ Arquitectura del Proyecto

### Modelo de Datos

#### Base Global (`db_matriz`)
Almacena datos maestros y agregadores:
- **Entidades globales**: `regiones`, `ciudades`, `sucursales`, `usuarios`, `clientes`, `direcciones_despacho`, `productos`, `precios_sucursal`, `carritos`, `carrito_items`, `proveedores`, `ordenes_compra`, `detalle_compras`

#### Bases Locales (`db_norte`, `db_sur`, `db_centro`)
Almacenan datos operativos por sucursal:
- **Entidades locales**: `stock`, `reservas_temporales_stock`, `pedidos`, `detalle_pedidos` (o `detallepedidos`)

### Principio Operativo

1. Usuario navega catálogo global desde `db_matriz`
2. Agrega productos al carrito global en matriz
3. Cada ítem del carrito está asociado a una sucursal específica
4. En checkout, el sistema agrupa ítems por `sucursal_id`
5. Se resuelve el `codigo_nodo` de cada sucursal
6. Crea un pedido local por nodo/sucursal
7. Descuenta stock localmente
8. Limpia `carrito_items` del carrito global al finalizar

---

## 🗂️ Estructura de Archivos

```
src/
├── config/
│   ├── database.php          # Clase Database + helpers PDO
│   ├── helpers.php           # Funciones auxiliares DB y negocio
│   └── node-config.php       # Configuración por nodo
├── controllers/
│   ├── AuthController.php    # Login, registro, logout
│   ├── CartController.php    # CRUD carrito global
│   ├── OrdersController.php  # Crear pedidos (multi-nodo)
│   └── ProductsController.php# Catálogo + inventario admin
├── services/
│   ├── CartReplicationService.php   # Lógica granular de carrito
│   ├── InventorySyncService.php     # Sincronización de stock
│   └── ProductSyncService.php       # Operaciones con catálogo
├── templates/
│   ├── layout.php            # Layout principal
│   ├── partials/
│   │   ├── header.php        # Header con navegación y carrito
│   │   └── footer.php        # Footer con scripts
│   └── pages/                # Vistas por página (home, products, cart, etc.)
└── index.php                 # Front controller + routing

templates/pages/
    ├── home.php              # Página de inicio
    ├── products.php          # Listado productos
    ├── product-detail.php    # Detalle producto
    ├── cart.php              # Carrito de compras
    ├── checkout.php          # Checkout con formulario
    ├── reviews.php           # Historial de pedidos (antes reseñas)
    ├── inventory.php         # Vista inventario admin
    ├── login.php             # Login
    ├── register.php          # Registro
    └── 403.php               # Error no autorizado
```

---

## 🌐 Endpoints y Rutas

### GET Routes (Rutas de lectura)

| Ruta | Controller/Función | Descripción | Datos en respuesta |
|------|-------------------|-------------|-------------------|
| `/` | `dataRoutes['']()` | Home con catálogo | `{view: 'home', productos: [...]}` |
| `/products` | `dataRoutes['products']()` | Listado productos | `{view: 'products', productos: [...]}` |
| `/products/{id}` | Fallback en index.php | Detalle producto | `{producto: {...}, precios_otros: [...]}` |
| `/cart` | `CartController::index()` | Carrito actual | `{carrito: {items, total_items, subtotal}}` |
| `/checkout` | `dataRoutes['checkout']()` | Checkout + direcciones | `{carrito, direcciones: [...]}` |
| `/reviews` | `OrdersController::index()` | Mis pedidos | `{pedidos: [...], is_admin_view: bool}` |
| `/inventory` | `dataRoutes['inventory']()` | Inventario admin | `{inventario: [...]}` |
| `/admin-orders` | `dataRoutes['admin-orders']()` | Todos los pedidos | `{pedidos: [...], is_admin_view: true}` |

### POST Routes (Rutas de escritura)

| Ruta | Controller/Función | Descripción | Parámetros |
|------|-------------------|-------------|------------|
| `/cart/add` | `CartController::handleAddRequest()` | Agregar al carrito | `{producto_id, sucursal_id, cantidad}` |
| `/cart/update` | `CartController::handleUpdateRequest()` | Actualizar cantidad | `{item_id, cantidad}` |
| `/cart/remove` | `CartController::handleRemoveRequest()` | Eliminar item | `{item_id}` |
| `/cart/clear` | `CartController::handleClearRequest()` | Vaciar carrito | `-` |
| `/orders/create` | `OrdersController::handleCreateRequest()` | Crear pedido | `{tipo_entrega, direccion_despacho_id?}` |
| `/login` | `AuthController::handleLoginRequest()` | Iniciar sesión | `{email, password}` |
| `/register` | `AuthController::handleRegisterRequest()` | Registrar cuenta | `{nombre, apellido, rut, telefono, email, password}` |
| `/logout` | `AuthController::logout()` | Cerrar sesión | `-` |

---

## 🛠️ Funciones Principales (helpers.php)

### Conexión a Base de Datos

```php
// PDO de db_matriz (global)
dbMatriz() → PDO

// PDO de nodo local activo (norte/sur/centro)
db() → PDO  // ⚠️ NO usar para operaciones sensibles si NODE_TYPE=matriz

// PDO de sucursal específica por nombre de nodo
dbSucursal('norte') → PDO

// Todos los nodos locales disponibles
dbNodos() → array(PDO)
```

### Funciones de Productos (desde db_matriz)

```php
getProductos(int $limit = 100): array           // Listado con precios y stock
getProductById(string $id): ?array              // Producto por UUID
getProductoConPrecio(string $productoId, string $sucursalId): ?array  // Con precio de sucursal

getAllBranches(): array                          // Todas las sucursales activas
getBranchById(string $sucursalId): ?array        // Sucursal por ID
getNodeFromSucursalId(string $sucursalId): string// código_nodo de la sucursal
dbBySucursalId(string $sucursalId): PDO          // PDO directo a sucursal
```

### Funciones de Inventario/Stock (locales)

```php
getInventario(string $sucursalId): array         // Stock consolidado con datos producto
getStockProducto(string $productoId, string $sucursalId): int  // Stock en una sucursal
getStockProductoPorSucursal(string $productoId): array  // Stock por todas las sucursales
getStockTotalProducto(string $productoId): int   // Suma total de stock
```

### Funciones de Carrito (db_matriz)

```php
getCarrito(?string $sessionToken = null, ?string $clienteId = null): array  // Obtener carrito
addToCarrito(string $productoId, string $sucursalId, int $cantidad, 
              ?string $sessionToken = null, ?string $clienteId = null): bool // Agregar item

calculateCartSubtotal(array $items): float       // Calcular subtotal
```

### Funciones de Pedidos (locales)

```php
getPedidosByNode(string $node, ?string $clienteId = null, int $limit = 50): array  // Por nodo
getPedidos(?string $clienteId = null, int $limit = 50): array                       // Todos los nodos
```

### Funciones de Usuario/Rol

```php
currentUserId(): ?string                          // ID del usuario actual
currentClienteId(): ?string                       // ID del cliente actual
isAuthenticated(): bool                           // ¿Usuario autenticado?
currentUserName(): ?string                        // Nombre completo
getFlash(string $type): ?string                   // Obtener flash message (success/error)
```

### Funciones de Utilidad

```php
generateUuid(): string                            // Generar UUID aleatorio
page(string $name): string                        // Path al template: templates/pages/{name}.php
e(mixed $value): string                           // Escape HTML para seguridad
url(string $path = ''): string                    // Construir URL completa
asset(string $path): string                       // Path a asset estático
jsonForHtml(mixed $value): string                 // JSON seguro para insertar en HTML
```

---

## 📦 Servicios (Services Layer)

### CartReplicationService.php

Métodos disponibles:

```php
$service = new CartReplicationService();

// Obtener carrito completo con items enriquecidos
$service->getCart(?string $sessionToken, ?string $clienteId): array

// Agregar producto al carrito
$service->addToCart(string $productoId, string $sucursalId, int $cantidad, 
                    ?string $sessionToken = null, ?string $clienteId = null): bool

// Actualizar cantidad de item existente
$service->updateCartItem(string $itemId, int $newQuantity): bool

// Eliminar item del carrito
$service->removeFromCart(string $itemId): bool

// Vaciar todo el carrito
$service->clearCart(string $carritoId): bool

// Mover carrito anónimo al autenticado (login)
$service->mergeAnonymousCart(string $sessionToken, string $clienteId): bool

// Calcular subtotal de items
$service->getSubtotal(array $items): float
```

### InventorySyncService.php

Métodos disponibles:

```php
$service = new InventorySyncService();

// Inventario local de la sucursal actual
$service->getLocalInventory(string $sucursalId): array

// Inventario consolidado de todas las sucursales
$service->getGlobalInventory(): array

// Stock de un producto en todas las sucursales
$service->getProductStockAcrossBranches(string $productId): array

// Verificar disponibilidad mínima en una sucursal
$service->checkAvailabilityInBranch(string $productId, string $branchId, int $requiredQty = 1): bool

// Ajustar stock (ingreso negativo/positivo)
$service->adjustLocalStock(string $productoId, string $sucursalId, int $delta): bool

// Obtener sucursales disponibles desde matriz
$service->getAvailableBranches(): array
```

### ProductSyncService.php

Métodos disponibles:

```php
$service = new ProductSyncService();

// Catálogo base (con o sin precios de sucursal)
$service->getCatalog(?string $sucursalId, int $limit = 100, int $offset = 0): array

// Producto individual con precio opcional
$service->getProductById(string $productId, ?string $sucursalId = null): ?array

// Búsqueda por nombre o SKU
$service->searchProducts(string $query, ?string $sucursalId = null, int $limit = 100): array

// Precios de un producto en todas las sucursales
$service->getProductPricesAcrossBranches(string $productId): array

// Enriquecer listado con stock local
$service->attachLocalStock(array $productos, string $sucursalId): array

// Enriquecer producto individual con precio + stock
$service->attachSucursalContext(array $producto, string $sucursalId): array
```

---

## 🗄️ Esquema de Tablas

### Tablas Globales (db_matriz)

| Tabla | Columnas principales | Descripción |
|-------|---------------------|-------------|
| `regiones` | `id`, `nombre` | Regiones geográficas |
| `ciudades` | `id`, `nombre`, `region_id` | Ciudades con FK a regiones |
| `sucursales` | `id`, `nombre`, `codigo_nodo`, `activa`, `es_bodega_central`, `ciudad_id` | Sucursales activas |
| `usuarios` | `id`, `email`, `password`, `rol`, `created_at` | Usuarios (cliente/admin) |
| `clientes` | `id`, `usuario_id`, `rut`, `nombre`, `apellido`, `telefono` | Perfiles de clientes |
| `direcciones_despacho` | `id`, `calle`, `numero`, `depto_block`, `ciudad_id`, `cliente_id` | Direcciones para despacho |
| `productos` | `id`, `sku`, `nombre`, `descripcion`, `peso_gramos`, `activo` | Catálogo de productos |
| `precios_sucursal` | `producto_id`, `sucursal_id`, `precio_efectivo`, `precio_tarjeta` | Precios por sucursal |
| `carritos` | `id`, `session_token`, `cliente_id` | Carrito (anónimo o autenticado) |
| `carrito_items` | `id`, `carrito_id`, `producto_id`, `sucursal_id`, `cantidad` | Items del carrito global |
| `proveedores` | `id`, `nombre`, `contacto`, ... | Proveedores |
| `ordenes_compra` | `id`, `proveedor_id`, `fecha`, ... | Órdenes de compra a proveedores |
| `detalle_compras` | `id`, `orden_id`, `producto_id`, `cantidad`, `precio_unitario` | Detalle órdenes compra |

### Tablas Locales (db_norte/sur/centro)

| Tabla | Columnas principales | Descripción |
|-------|---------------------|-------------|
| `stock` | `id`, `sucursal_id`, `producto_id`, `cantidad_real` | Inventario por sucursal |
| `reservas_temporales_stock` | `id`, `sucursal_id`, `producto_id`, `fecha_expira` | Reservas temporales |
| `pedidos` | `id`, `cliente_id`, `numero_orden`, `sucursal_origen_id`, `direccion_despacho_id`, `tipo_entrega`, `estado_pedido`, `total_productos`, `total_despacho`, `total_pagado`, `created_at` | Pedidos locales |
| `detalle_pedidos` (o `detallepedidos`) | `id`, `pedido_id`, `producto_id`, `cantidad`, `precio_unitario_pagado` | Detalle de pedidos |

---

## ⚙️ Configuración por Nodo (node-config.php)

```php
nodeConfigs(): array = [
    'matriz' => [
        'name' => 'Casa Matriz',
        'role' => 'global',
        'description' => 'Nodo principal con datos globales: catálogo, usuarios, precios y sucursales',
        'can_write_local' => true,   // db_matriz
        'can_write_matriz' => true,  // escribe directo en db_matriz
        'cache_enabled' => true,
        'max_products_per_page' => 100,
    ],
    'norte' => [
        'name' => 'Sucursal Norte',
        'role' => 'branch',
        'description' => 'Nodo sucursal: escribe inventario, pedidos y carritos locales; lee catálogo desde db_matriz',
        'can_write_local' => true,   // db_norte (stock, pedidos, carritos, reservas)
        'can_write_matriz' => false, // NO escribe en db_matriz directamente
        'cache_enabled' => true,
        'max_products_per_page' => 50,
    ],
    // ... sur y centro con misma estructura
];

// Funciones de configuración:
getNodeConfig(): array              // Configuración completa del nodo actual
canWriteLocal(): bool               // ¿Puede escribir en DB local? (siempre true)
canWriteMatriz(): bool              // ¿Puede escribir en db_matriz? (solo matriz)
getDisplayName(): string            // Nombre legible para UI/logs
isBranchNode(): bool                // ¿Es nodo sucursal (no matriz)?
```

---

## 🎭 Control de Acceso y Roles

### Permisos por Rol

| Acción | Cliente | Admin |
|--------|---------|-------|
| Ver catálogo | ✅ | ✅ |
| Usar carrito | ✅ | ✅ |
| Crear pedidos | ✅ (propios) | ✅ (todos) |
| Ver historial de pedidos | ✅ (propios) | ✅ (todos) |
| Acceder a inventario | ❌ | ✅ |
| Escribir en db_matriz | ❌ | ❌ (solo admin backend) |

### Implementación

```php
// En controllers y rutas:
if (($_SESSION['rol'] ?? '') !== 'admin') {
    return ['success' => false, 'error' => 'No autorizado'];
}

// Header oculta "Inventario" para no-admins:
<?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
    <li><a href="<?= e(url('inventory')); ?>">Inventario</a></li>
<?php endif; ?>
```

---

## 📝 Patrones de Código Recomendados

### 1. Manejo de Errores en Operaciones DB

```php
try {
    $pdo->beginTransaction();
    
    // ... operaciones ...
    
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[OPERACION] ' . $e->getMessage());
    return ['success' => false, 'error' => $e->getMessage()];
}
```

### 2. Uso Correcto de Placeholders PDO

```php
// ❌ INCORRECTO (causa HY093 error):
$updated->execute([':qty_discount' => $detalle['cantidad'], ':qty_check' => $detalle['cantidad']]);

// ✅ CORRECTO:
$stmt1 = $pdo->prepare('UPDATE stock SET cantidad_real = cantidad_real - :qty WHERE producto_id = :pid AND sucursal_id = :sid');
$stmt2 = $pdo->prepare('SELECT cantidad_real FROM stock WHERE producto_id = :pid AND sucursal_id = :sid LIMIT 1');

$stmt1->execute([':qty' => $detalle['cantidad'], ':pid' => $productoId, ':sid' => $sucursalId]);
$stmt2->execute([':pid' => $productoId, ':sid' => $sucursalId]);
```

### 3. Consistencia de Nombres de Columnas

**NOMBRE FINAL**: Usar siempre `snake_case` con guión bajo:

| Incorrecto | Correcto |
|------------|----------|
| `clienteid` | `cliente_id` |
| `productoid` | `producto_id` |
| `detallepedidos` | `detalle_pedidos` |
| `createdat` | `created_at` |
| `sucursalid` | `sucursal_id` |

### 4. Conexión a Base de Datos Segura

```php
// ✅ CORRECTO - Siempre explícito:
dbMatriz()           // Para datos globales (catálogo, usuarios)
dbSucursal('norte')  // Para operaciones locales específicas

// ⚠️ CUIDADO - Ambiguo cuando NODE_TYPE=matriz:
db()                 // NO usar para operaciones sensibles de escritura
```

### 5. Enriquecimiento de Datos

```php
// Patrones comunes en controllers:
$productos = array_map(function (array $p): array {
    $p['stock'] = getStockProducto($p['id'], $_SESSION['sucursal_id']);
    return $p;
}, $productos);

$productoConPrecio = getProductoConPrecio($id, $this->sucursalId);
```

---

## 🔍 Debug y Logging

### Logs en error_log()

Los logs siguen el formato: `[NOMBRE_FUNCION] mensaje`

Ejemplos:
- `[OrdersController::create] INICIO cliente=... tipo_entrega=...`
- `[getStockProducto] producto=... sucursal=... nodo=... stock=...`
- `[DB NODE] type=matriz | host=nodo_matriz_db | matriz=...`

### Debug Controlado (solo en matriz)

```php
try {
    $testMatriz = dbMatriz()->query('SELECT 1')->fetchColumn();
    error_log('[DEBUG] dbMatriz OK: ' . var_export($testMatriz, true));
    
    $countProductos = dbMatriz()->query('SELECT COUNT(*) FROM productos')->fetchColumn();
    error_log('[DEBUG] productos en matriz: ' . var_export($countProductos, true));
} catch (Throwable $e) {
    error_log('[DEBUG] Error dbMatriz: ' . $e->getMessage());
}
```

---

## 📊 Datos de Prueba y Semilla

### Usuarios

Para crear un usuario admin, editar directamente en `db_matriz.usuarios`:

```sql
UPDATE usuarios SET rol = 'admin' WHERE email = 'admin@sisdist.com';
-- O crear nuevo:
INSERT INTO usuarios (id, email, password, rol) 
VALUES ('admin-uuid', 'admin@sisdist.com', '$2y$10$...', 'admin');

INSERT INTO clientes (id, usuario_id, rut, nombre, apellido, telefono)
VALUES ('cliente-uuid', 'admin-uuid', 'K12345678-9', 'Admin', 'Sistema', '');
```

### Sucursales de Prueba

Asegurar que existan con `codigo_nodo` definido:

```sql
-- Verificar sucursales activas
SELECT id, nombre, codigo_nodo FROM sucursales WHERE activa = 1;

-- Esperado: norte, sur, centro con sus respectivos códigos de nodo
```

### Stock Inicial

Cada nodo debe tener stock inicial en `stock` tabla local. Usar `InventorySyncService::adjustLocalStock()` para ajustar.

---

## ⚠️ Riesgos Técnicos y Cómo Evitarlos

### 1. Error HY093: Invalid parameter number

**Causa**: Reutilizar el mismo placeholder `:param` múltiples veces en una sola consulta.

**Solución**: Usar placeholders distintos o bindParams explícitos:
```php
// ❌ MALO:
$stmt->execute([':qty' => $val1, ':qty' => $val2]);

// ✅ BIEN:
$stmt1->execute([':qty' => $val1]);
$stmt2->execute([':qty' => $val2]);
```

### 2. Home vacío o catálogo sin productos

**Causa**: Mezcla entre arquitectura antigua y nueva, efectos del front controller sobre assets.

**Solución**: Verificar logs de `dbMatriz()` en `/mnt/c/Users/EduardoTrabajo/Documents/GitHub/PaginaTipoMercadoLibre-SisDist-main/logs/` para confirmar que existen productos en matriz.

### 3. Carrito no persiste entre sesiones

**Causa**: Token de sesión incorrecto o carrito anónimo no fusionado al login.

**Solución**: Asegurar `$_SESSION['cart_token']` está definido en `index.php`. Usar `CartReplicationService::mergeAnonymousCart()` en punto de login.

### 4. Pedidos duplicados tras error

**Causa**: Falla en transacción local sin rollback coordinado.

**Solución**: El `OrdersController::create()` ya implementa rollback por nodo y matriz. Verificar logs para confirmar commit/rollback correcto.

---

## 🚀 Flujo de Checkout Completo

```
1. Usuario agrega productos al carrito (POST /cart/add)
   → Items en db_matriz.carrito_items con sucursal_id
   
2. Usuario va a checkout (GET /checkout)
   → Carga carrito global + direcciones del cliente
   
3. Usuario selecciona tipo entrega y confirma (POST /orders/create)
   
4. Backend procesa:
   a. Agrupa items por sucursal_id
   b. Para cada grupo:
      - Resuelve codigo_nodo de la sucursal
      - Abre transacción en db local del nodo
      - Verifica stock disponible
      - Inserta pedido en pedidos tabla local
      - Inserta detalle en detalle_pedidos
      - Descuenta stock en stock
   c. Limpia carrito_items global
   d. Commit todas las transacciones
   
5. Redirige a /reviews con mensaje de éxito
```

---

## 📁 Estructura de Directorios del Proyecto

```
PaginaTipoMercadoLibre-SisDist-main/
├── docs/
│   ├── resumen_estado_proyecto_sisdist.md  # Resumen ejecutivo
│   └── guide_coding_agents_sisdist.md      # ← ESTE ARCHIVO
├── src/
│   ├── config/          # database.php, helpers.php, node-config.php
│   ├── controllers/     # AuthController, CartController, OrdersController, ProductsController
│   ├── services/        # CartReplicationService, InventorySyncService, ProductSyncService
│   ├── templates/       # layout.php, partials/, pages/
│   └── index.php        # Front controller
├── logs/                # Logs de aplicación
└── ...
```

---

## 🎯 Checklist para Agente al Retomar el Proyecto

- [ ] Leer `docs/resumen_estado_proyecto_sisdist.md` para contexto
- [ ] Verificar logs de `dbMatriz()` en `/logs/` para confirmar datos existen
- [ ] Confirmar que `templates/pages/403.php` y `templates/pages/404.php` existen
- [ ] Asegurar `$_SESSION['cart_token']` está definido al iniciar sesión
- [ ] Verificar rol del usuario en `db_matriz.usuarios` (admin/cliente)
- [ ] Confirmar sucursales activas con `codigo_nodo` definido
- [ ] Revisar stock inicial en bases locales antes de checkout

---

## 📖 Referencias Adicionales

- **README principal**: `/home/Eduardo/.pi/agent/npm/node_modules/@plannotator/pi-extension/skills/plannotator-setup-goal/SKILL.md`
- **Documentación Pi**: `/home/Eduardo/.local/share/pi-node/node-v22.22.3-linux-x64/lib/node_modules/@earendil-works/pi-coding-agent/docs/`

---

**Última actualización**: 2026-06-09  
**Versión del proyecto**: SisDist Marketplace v1.x (arquitectura distribuida)
