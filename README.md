# Sistema de Comercio Electrónico Distribuido "Libre Mercado"

Prototipo de sistema distribuido de comercio electrónico desarrollado para simular un entorno real de alta disponibilidad y fragmentación, permitiendo el estudio de transacciones ACID y el Teorema CAP.

## Arquitectura de la Infraestructura

El entorno está orquestado con **Docker Compose** y utiliza una red interna tipo bridge (`red_distribuida`) para simular la separación física de los nodos. La arquitectura consta de 5 contenedores:

* **Servidor de Aplicación (PHP 8.2 + Apache)**
  * **Puerto Web:** `8080` (Mapeado al 80 interno).
  * **Rol:** Ejecuta la lógica de negocio, manejo de transacciones distribuidas y ruteo a través de PDO. Sincronizado mediante Volume Mount con el directorio `./src`.

* **Nodo Matriz (MariaDB 10.6)**
  * **Puerto:** `3306`
  * **Base de datos:** `db_matriz`
  * **Rol:** Almacena las tablas globales (Usuarios, Clientes, Catálogo de Productos y Proveedores).

* **Nodos Sucursales (MariaDB 10.6)**
  * Manejan el inventario local, carritos de compra y ventas físicas independientes.
  * **Sucursal Norte:** Puerto `3307` (BD: `db_norte`)
  * **Sucursal Sur:** Puerto `3308` (BD: `db_sur`)
  * **Sucursal Centro:** Puerto `3309` (BD: `db_centro`)

## Requisitos Previos

* **Docker** y **Docker Compose** instalados y ejecutándose en el host.
* Un Cliente de Base de Datos ( MySQL Workbench) para administrar los nodos.

## Instrucciones de Despliegue

1. **Levantar la infraestructura:**
   En la raíz del proyecto, construye y levanta los contenedores en segundo plano:
   ```bash
   docker compose up -d --build
