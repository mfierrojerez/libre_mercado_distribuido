# Explicación del teorema CAP en SisDist Marketplace

## Introducción
SisDist Marketplace es un sistema distribuido porque separa la información global de la información local por sucursal. La base matriz guarda catálogos y datos maestros, mientras que cada sucursal mantiene su propio stock, pedidos y detalles de operación. Esa arquitectura hace que el proyecto sea un buen ejemplo para explicar el teorema CAP.

## Qué dice CAP
El teorema CAP establece que un sistema distribuido no puede garantizar al mismo tiempo Consistencia, Disponibilidad y Tolerancia a Particiones. Cuando ocurre una partición en la red o un nodo deja de responder, el sistema debe decidir qué propiedad priorizar. En este proyecto, esa decisión aparece especialmente en las escrituras críticas.

## Arquitectura del proyecto
La aplicación divide sus datos en dos capas. La capa global usa `dbMatriz()` para productos, precios, sucursales, usuarios y clientes. La capa local usa `dbSucursal($node)` para stock, pedidos, detallepedidos y otros datos propios de cada sucursal. Esa separación permite que cada nodo trabaje de manera relativamente independiente.

## Cómo se ve CAP en la práctica
### Consistencia
El proyecto prioriza que los datos queden correctos en el nodo apropiado. Por eso, al crear pedidos o ajustar inventario, la operación debe ir a la base local correspondiente. Si el sistema no puede asegurar eso, es preferible detener la operación antes que escribir en un lugar equivocado.

### Disponibilidad
La disponibilidad no es la prioridad máxima en las rutas críticas. Si una sucursal no responde o el nodo no es válido, el sistema puede rechazar la operación en lugar de continuar con datos parciales. Eso protege la integridad, pero reduce la disponibilidad total.

### Tolerancia a particiones
Cada sucursal tiene su propia base local, así que el sistema puede seguir funcionando aunque otra sucursal tenga problemas de red o caiga temporalmente. Esa separación permite que una parte del sistema siga operando aun cuando otra falle.

## Rutas que muestran esta idea
### `/orders/create`
Esta ruta crea pedidos y normalmente agrupa items por sucursal. Luego abre una transacción local por nodo, valida stock, inserta el pedido y el detalle, descuenta stock y confirma con `commit()` solo si todo salió bien. Si falla algo, ejecuta `rollBack()`.

### `/inventory`
Esta ruta administrativa modifica stock local. El ajuste debe hacerse en el nodo correcto y sobre la fila correcta. Si la base no coincide, la operación debe fallar para evitar inconsistencias entre la tabla mostrada y la base real.

### `/reviews`
Esta ruta solo lee pedidos, pero refleja el modelo distribuido porque permite filtrar por nodo y mostrar información local de cada sucursal.

## Relación con ACID
CAP y ACID no son lo mismo, pero se complementan en este proyecto. ACID describe cómo se comporta una transacción dentro de una base de datos, mientras que CAP describe el comportamiento del sistema distribuido completo. Aquí, ACID protege cada transacción local, y CAP explica por qué el sistema prefiere consistencia por nodo antes que disponibilidad total.

## Decisión de diseño
SisDist se acerca más a un comportamiento CP: Consistencia más Tolerancia a Particiones. No intenta responder siempre si eso implica guardar datos en la sucursal equivocada o dejar estados inconsistentes. Esa decisión es razonable para inventario y pedidos, porque ambos procesos requieren precisión.

## Conclusión
En SisDist Marketplace, el teorema CAP se ve claramente en la división entre matriz y sucursales. El sistema acepta perder disponibilidad temporal en algunas escrituras para conservar la coherencia de los datos. Por eso, el diseño prioriza que cada nodo mantenga su integridad antes que forzar una respuesta a cualquier costo.
