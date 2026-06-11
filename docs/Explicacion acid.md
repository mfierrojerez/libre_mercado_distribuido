# SisDist Marketplace: Rutas y flujo de transacciones ACID

## Objetivo
Este documento resume las rutas clave y el flujo transaccional del proyecto para explicar cómo se aplican las propiedades ACID en operaciones críticas.

## Arquitectura relevante
- `dbMatriz()` para datos globales: productos, sucursales, precios, usuarios y clientes.
- `dbSucursal($node)` para datos locales: stock, pedidos, detallepedidos, carritos y reservas.
- Nodos válidos: `norte`, `sur`, `centro`.

## Rutas principales

### GET `/products`
- Lista productos desde `dbMatriz()`.
- Enriquecer la vista con stock/precio por sucursal según el nodo activo.
- No escribe datos, solo lectura.

### GET `/cart`
- Carga el carrito activo.
- Lee y muestra items, subtotal y totales.
- No escribe datos por sí mismo.

### GET `/checkout`
- Reúne carrito y direcciones.
- Prepara la creación del pedido.
- Sigue siendo lectura hasta confirmar compra.

### GET `/reviews`
- Muestra pedidos del usuario o del admin.
- Para admin, filtra por nodo seleccionado.
- Lectura de pedidos locales.

### GET `/inventory`
- Vista administrativa de stock.
- Lee inventario por sucursal y permite ajustar cantidades.
- Usa base local del nodo para escritura.

### POST `/orders/create`
- Crea pedidos.
- Es el flujo transaccional más importante del proyecto.
- Agrupa items por sucursal y ejecuta transacciones locales por nodo.

### POST `/orders/status`
- Admin cambia estado de un pedido.
- Actualiza pedido local dentro del nodo correspondiente.
- Escritura acotada por sucursal.

### POST `/inventory`
- Admin ajusta stock.
- Actualiza una fila local de `stock`.
- Debe usar transacción local y rollback si falla.

## Flujo ACID de pedidos
1. El usuario confirma compra en `/checkout`.
2. El backend agrupa items por sucursal.
3. Para cada nodo, abre conexión local con `dbSucursal($node)`.
4. Inicia transacción con `beginTransaction()`.
5. Verifica stock disponible.
6. Inserta `pedido` y `detallepedidos`.
7. Descuenta stock local.
8. Si todo sale bien, hace `commit()`.
9. Si algo falla, hace `rollBack()`.
10. Luego limpia carrito y redirige a `/reviews`.

## Flujo ACID de inventario
1. El admin entra a `/inventory?node=...`.
2. La vista muestra el stock del nodo seleccionado.
3. El formulario envía `stock_id`, `producto_id`, `sucursal_id` y `delta`.
4. `InventoryController` abre transacción en la base local del nodo.
5. Lee la fila exacta de `stock`.
6. Calcula el nuevo valor.
7. Actualiza la misma fila por `id`.
8. Si falla algo, hace `rollBack()`.
9. Si todo sale bien, hace `commit()`.
10. La redirección vuelve al mismo nodo para verificar el cambio.

## Cómo se verifican las propiedades ACID

### Atomicidad
La operación debe completarse entera o no hacerse. En pedidos, esto significa que no puede quedar el pedido sin stock descontado o viceversa.

### Consistencia
La base debe quedar en un estado válido: stock no negativo, pedidos completos, detalles coherentes con el pedido principal.

### Aislamiento
Cada nodo trabaja con su propia base local. Una operación en `sur` no debe interferir con `norte`.

### Durabilidad
Después de `commit()`, el cambio debe persistir aunque la petición termine. Se verifica consultando de nuevo la base local.

## Puntos de revisión para explicación técnica
- `OrdersController::handleCreateRequest()`.
- `OrdersController::handleUpdateStatusRequest()`.
- `InventoryController::handleAdjustRequest()`.
- `dbSucursal($node)` y la resolución del nodo.
- `rollBack()` en errores y `commit()` en éxito.

## Observación importante
Las rutas de lectura como `/products`, `/cart`, `/checkout` y `/reviews` no son transaccionales por sí mismas, pero dependen de datos ya escritos correctamente por las rutas transaccionales.

## Conclusión
En SisDist, las transacciones ACID se concentran principalmente en la creación de pedidos y el ajuste de inventario. El modelo multi-nodo hace que la verificación real dependa de confirmar, por cada nodo, que la operación local se completó o se revirtió correctamente.
