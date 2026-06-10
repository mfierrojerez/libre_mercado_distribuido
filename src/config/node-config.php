<?php
// src/config/node-config.php

function nodeConfigs(): array
{
    return [
        Database::NODE_MATRIZ => [
            'name'                => 'Casa Matriz',
            'role'                => 'global',
            'description'         => 'Nodo principal con datos globales: catálogo, usuarios, precios y sucursales',
            'can_write_local'     => true,   // db_matriz
            'can_write_matriz'    => true,   // escribe directo en db_matriz
            'cache_enabled'       => true,
            'max_products_per_page' => 100,
        ],
        Database::NODE_NORTE => [
            'name'                => 'Sucursal Norte',
            'role'                => 'branch',
            'description'         => 'Nodo sucursal: escribe inventario, pedidos y carritos locales; lee catálogo desde db_matriz',
            'can_write_local'     => true,   // db_norte (stock, pedidos, carritos, reservas)
            'can_write_matriz'    => false,  // NO escribe en db_matriz directamente
            'cache_enabled'       => true,
            'max_products_per_page' => 50,
        ],
        Database::NODE_SUR => [
            'name'                => 'Sucursal Sur',
            'role'                => 'branch',
            'description'         => 'Nodo sucursal: escribe inventario, pedidos y carritos locales; lee catálogo desde db_matriz',
            'can_write_local'     => true,   // db_sur
            'can_write_matriz'    => false,
            'cache_enabled'       => true,
            'max_products_per_page' => 50,
        ],
        Database::NODE_CENTRO => [
            'name'                => 'Sucursal Centro',
            'role'                => 'branch',
            'description'         => 'Nodo sucursal: escribe inventario, pedidos y carritos locales; lee catálogo desde db_matriz',
            'can_write_local'     => true,   // db_centro
            'can_write_matriz'    => false,
            'cache_enabled'       => true,
            'max_products_per_page' => 50,
        ],
    ];
}

/** Configuración completa del nodo actual */
function getNodeConfig(): array
{
    $configs  = nodeConfigs();
    $nodeInfo = Database::getInstance()->getNodeInfo();
    return $configs[$nodeInfo['type']] ?? $configs[Database::NODE_MATRIZ];
}

/** El nodo puede escribir en su DB local (siempre true para cualquier nodo) */
function canWriteLocal(): bool
{
    return getNodeConfig()['can_write_local'] === true;
}

/** El nodo puede escribir directamente en db_matriz (solo nodo matriz) */
function canWriteMatriz(): bool
{
    return getNodeConfig()['can_write_matriz'] === true;
}

/**
 * @deprecated Usar canWriteLocal() o canWriteMatriz() según el caso.
 * Se mantiene por compatibilidad temporal.
 */
function canWriteToNode(): bool
{
    return canWriteLocal();
}

/** Nombre legible del nodo actual para logs y UI */
function getDisplayName(): string
{
    $configs  = nodeConfigs();
    $nodeInfo = Database::getInstance()->getNodeInfo();
    return $configs[$nodeInfo['type']]['name'] ?? 'Desconocido';
}

/** Verifica si el nodo actual es una sucursal (no matriz) */
function isBranchNode(): bool
{
    $nodeInfo = Database::getInstance()->getNodeInfo();
    return in_array(
        $nodeInfo['type'],
        [Database::NODE_NORTE, Database::NODE_SUR, Database::NODE_CENTRO],
        true
    );
}

/**
 * Capacidades disponibles según el tipo de nodo.
 *
 * Regla general:
 *  - Lectura de catálogo    → siempre disponible vía dbMatriz()
 *  - Escritura local        → siempre disponible (stock, pedidos, carritos, reservas)
 *  - Escritura en matriz    → solo nodo matriz (productos, precios, clientes, sucursales)
 *  - Gestión de usuarios    → solo nodo matriz
 */
function getAvailableRoles(): array
{
    return [
        'catalog_read'      => true,              // Todos leen de db_matriz
        'inventory_read'    => true,              // Todos pueden leer stock local
        'inventory_write'   => canWriteLocal(),   // Todos escriben en su DB local
        'cart_write'        => canWriteLocal(),   // Carritos en DB local
        'order_create'      => canWriteLocal(),   // Pedidos en DB local
        'prices_write'      => canWriteMatriz(),  // Precios en db_matriz → solo matriz
        'catalog_write'     => canWriteMatriz(),  // Productos en db_matriz → solo matriz
        'user_management'   => canWriteMatriz(),  // Usuarios en db_matriz → solo matriz
    ];
}

/** Límite de productos por página según el nodo */
function getMaxProductsPerPage(): int
{
    return (int) (getNodeConfig()['max_products_per_page'] ?? 50);
}