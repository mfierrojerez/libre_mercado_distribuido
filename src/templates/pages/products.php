<?php
$pageTitle = 'Productos - SisDist Marketplace';
$productos = $productos ?? [];
$sucursales = $sucursales ?? [];
$searchQuery = trim($_GET['q'] ?? ($_GET['search'] ?? ''));
$stockFilter = $_GET['stock'] ?? '';
$sortFilter = $_GET['sort'] ?? 'relevancia';
$sucursalFilter = trim($_GET['sucursal_id'] ?? '');

$productosFiltrados = $productos;

if ($searchQuery !== '') {
    $q = mb_strtolower($searchQuery);
    $productosFiltrados = array_values(array_filter($productosFiltrados, function ($producto) use ($q) {
        $nombre = mb_strtolower((string) ($producto['nombre'] ?? ''));
        $sku = mb_strtolower((string) ($producto['sku'] ?? ''));
        return str_contains($nombre, $q) || str_contains($sku, $q);
    }));
}

if ($stockFilter === 'con') {
    $productosFiltrados = array_values(array_filter($productosFiltrados, fn($producto) => (int) ($producto['stock_total'] ?? 0) > 0));
} elseif ($stockFilter === 'sin') {
    $productosFiltrados = array_values(array_filter($productosFiltrados, fn($producto) => (int) ($producto['stock_total'] ?? 0) <= 0));
}

if ($sucursalFilter !== '') {
    $productosFiltrados = array_values(array_filter($productosFiltrados, function ($producto) use ($sucursalFilter) {
        foreach (($producto['stock_por_sucursal'] ?? []) as $stockInfo) {
            if ((string) ($stockInfo['sucursal_id'] ?? '') === $sucursalFilter && (int) ($stockInfo['cantidad'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }));
}

$comparators = [
    'relevancia' => fn($a, $b) => strcasecmp((string)($a['nombre'] ?? ''), (string)($b['nombre'] ?? '')),
    'nombre_asc' => fn($a, $b) => strcasecmp((string)($a['nombre'] ?? ''), (string)($b['nombre'] ?? '')),
    'nombre_desc' => fn($a, $b) => strcasecmp((string)($b['nombre'] ?? ''), (string)($a['nombre'] ?? '')),
    'precio_asc' => fn($a, $b) => ((int)($a['precio_efectivo'] ?? 0)) <=> ((int)($b['precio_efectivo'] ?? 0)),
    'precio_desc' => fn($a, $b) => ((int)($b['precio_efectivo'] ?? 0)) <=> ((int)($a['precio_efectivo'] ?? 0)),
];

usort($productosFiltrados, $comparators[$sortFilter] ?? $comparators['relevancia']);
?>

<h1 class="page-title">Nuestros Productos</h1>
<form method="get" class="filters-bar" style="display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 1.5rem;">
    <div>
        <label for="q">Buscar</label>
        <input type="text" id="q" name="q" value="<?php echo e($searchQuery); ?>" placeholder="Nombre o SKU" class="form-control">
    </div>

    <div>
        <label for="stock">Stock</label>
        <select id="stock" name="stock" class="form-control">
            <option value="" <?php echo $stockFilter === '' ? 'selected' : ''; ?>>Todos</option>
            <option value="con" <?php echo $stockFilter === 'con' ? 'selected' : ''; ?>>Con stock</option>
            <option value="sin" <?php echo $stockFilter === 'sin' ? 'selected' : ''; ?>>Sin stock</option>
        </select>
    </div>

    <div>
        <label for="sort">Orden</label>
        <select id="sort" name="sort" class="form-control">
            <option value="relevancia" <?php echo $sortFilter === 'relevancia' ? 'selected' : ''; ?>>Relevancia</option>
            <option value="nombre_asc" <?php echo $sortFilter === 'nombre_asc' ? 'selected' : ''; ?>>Nombre A-Z</option>
            <option value="nombre_desc" <?php echo $sortFilter === 'nombre_desc' ? 'selected' : ''; ?>>Nombre Z-A</option>
            <option value="precio_asc" <?php echo $sortFilter === 'precio_asc' ? 'selected' : ''; ?>>Precio menor a mayor</option>
            <option value="precio_desc" <?php echo $sortFilter === 'precio_desc' ? 'selected' : ''; ?>>Precio mayor a menor</option>
        </select>
    </div>

    <div>
        <label for="sucursal_id">Sucursal</label>
        <select id="sucursal_id" name="sucursal_id" class="form-control">
            <option value="" <?php echo $sucursalFilter === '' ? 'selected' : ''; ?>>Todas</option>
            <?php foreach ($sucursales as $sucursal): ?>
                <?php
                    $sid = (string) ($sucursal['id'] ?? '');
                    $nombreSucursal = (string) ($sucursal['nombre'] ?? 'Sucursal');
                    if ($sid === '') continue;
                ?>
                <option value="<?php echo e($sid); ?>" <?php echo $sucursalFilter === $sid ? 'selected' : ''; ?>><?php echo e($nombreSucursal); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display:flex; gap:8px; align-items:end;">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="<?php echo e(url('products')); ?>" class="btn btn-secondary">Limpiar</a>
    </div>
</form>

<div class="products-list">
    <?php if (empty($productosFiltrados)): ?>
        <div class="no-products">
            <p>No hay productos que coincidan con los filtros seleccionados.</p>
        </div>
    <?php else: ?>
        <div class="products-card-grid">
            <?php foreach ($productosFiltrados as $producto): ?>
                <?php 
                    $cardMode = 'full';
                    include __DIR__ . '/../partials/product-card.php'; 
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>