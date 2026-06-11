<?php
// src/templates/partials/product-card.php

$cardMode = $cardMode ?? 'full';

$id              = $producto['id'] ?? '';
$nombre          = $producto['nombre'] ?? 'Producto sin nombre';
$sku             = $producto['sku'] ?? 'Sin SKU';
$precioEfectivo  = isset($producto['precio_efectivo']) ? (int) $producto['precio_efectivo'] : null;
$precioTarjeta   = isset($producto['precio_tarjeta']) ? (int) $producto['precio_tarjeta'] : null;
$stockTotal      = isset($producto['stock_total']) ? (int) $producto['stock_total'] : 0;
$stockSucursales = $producto['stock_por_sucursal'] ?? [];

$defaultSucursalId = '';
$defaultMaxQty = 0;
foreach ($stockSucursales as $stockInfo) {
    $cantidad = (int) ($stockInfo['cantidad'] ?? 0);
    $sid = (string) ($stockInfo['sucursal_id'] ?? '');
    if ($sid !== '' && $cantidad > 0) {
        $defaultSucursalId = $sid;
        $defaultMaxQty = $cantidad;
        break;
    }
}

$puedeAgregar = $id !== '' && $defaultSucursalId !== '' && $stockTotal > 0;
$skuFile = trim($sku);
$skuFile = preg_replace('/[^a-z0-9\-]+/i', '-', $skuFile);
$skuFile = trim($skuFile, '-');
$imageFs = dirname(__DIR__, 2) . '/public/images/' . $skuFile . '.jpg';
$imageUrl = '/images/' . $skuFile . '.jpg';
?>

<article class="product-card" data-id="<?php echo e($id); ?>">
    <?php if (is_file($imageFs)): ?>
        <img src="<?php echo e($imageUrl); ?>" alt="<?php echo e($nombre); ?>" class="product-image">
    <?php else: ?>
        <div class="no-image-placeholder">Sin imagen</div>
    <?php endif; ?>

    <h3><a href="<?php echo e(url('products/' . $id)); ?>"><?php echo e($nombre); ?></a></h3>
    <p class="sku">SKU: <?php echo e($sku); ?></p>

    <div class="product-prices">
        <?php if ($precioEfectivo !== null): ?>
            <span class="price">Efectivo: $<?php echo number_format($precioEfectivo, 0, ',', '.'); ?></span>
        <?php endif; ?>
        <?php if ($precioTarjeta !== null): ?>
            <span class="price price-secondary">Tarjeta: $<?php echo number_format($precioTarjeta, 0, ',', '.'); ?></span>
        <?php endif; ?>
    </div>

    <div class="product-stock">
        <?php if ($stockTotal > 0): ?>
            <span class="badge badge-success">Stock total: <?php echo $stockTotal; ?></span>
        <?php else: ?>
            <span class="badge badge-warning">Sin stock</span>
        <?php endif; ?>
    </div>

    <div class="product-actions">
        <a href="<?php echo '/products/' . $id; ?>" class="btn btn-secondary full-width">Ver detalle</a>
        <?php if ($cardMode === 'full'): ?>
            <?php if (!empty($stockSucursales)): ?>
                <div class="stock-breakdown">
                    <p><strong>Disponibilidad por sucursal:</strong></p>
                    <ul>
                        <?php foreach ($stockSucursales as $stockInfo): ?>
                            <?php
                                $sucursal = $stockInfo['sucursal'] ?? ucfirst((string) ($stockInfo['node'] ?? 'Sucursal'));
                                $cantidad = (int) ($stockInfo['cantidad'] ?? 0);
                            ?>
                            <li><?php echo e($sucursal); ?>: <strong><?php echo $cantidad; ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($puedeAgregar): ?>
                <form method="post" action="<?php echo e(url('cart/add')); ?>" class="add-to-cart-form">
                    <input type="hidden" name="producto_id" value="<?php echo e($id); ?>">

                    <label for="sucursal_id_<?php echo e($id); ?>">Sucursal</label>
                    <select id="sucursal_id_<?php echo e($id); ?>" name="sucursal_id" required>
                        <option value="">Seleccione sucursal</option>
                        <?php foreach ($stockSucursales as $stockInfo): ?>
                            <?php
                                $sid = (string) ($stockInfo['sucursal_id'] ?? '');
                                $sucursal = $stockInfo['sucursal'] ?? ucfirst((string) ($stockInfo['node'] ?? 'Sucursal'));
                                $cantidad = (int) ($stockInfo['cantidad'] ?? 0);
                                if ($sid === '' || $cantidad <= 0) { continue; }
                            ?>
                            <option value="<?php echo e($sid); ?>" <?php echo $sid === $defaultSucursalId ? 'selected' : ''; ?>>
                                <?php echo e($sucursal); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="cantidad_<?php echo e($id); ?>">Cantidad</label>
                    <select id="cantidad_<?php echo e($id); ?>" name="cantidad" required>
                        <?php for ($i = 1; $i <= max(1, min(10, $defaultMaxQty)); $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i === 1 ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>

                    <button type="submit" class="btn btn-primary">Agregar al carrito</button>
                </form>
            <?php else: ?>
                <p class="text-muted">Producto sin stock para agregar.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</article>
