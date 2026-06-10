<?php
$pageTitle = 'Productos - SisDist Marketplace';
$productos = $productos ?? [];
$searchQuery = $search_query ?? '';
?>

<h1 class="page-title">Nuestros Productos</h1>

<div class="products-list">
    <?php if (empty($productos)): ?>
        <div class="no-products">
            <p>No hay productos disponibles en este momento.</p>
        </div>
    <?php else: ?>
        <div class="products-card-grid">
            <?php foreach ($productos as $producto): ?>
                <?php
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
                    $skuFile = strtolower(trim($sku));
                    $skuFile = preg_replace('/[^a-z0-9\-]+/i', '-', $skuFile);
                    $skuFile = trim($skuFile, '-');
                    $imageFs = dirname(__DIR__, 3) . '/public/images/' . $skuFile . '.jpg';
                    $imageUrl = image_asset('images/' . $skuFile . '.jpg');
                ?>

                <article class="product-card product-card-compact" data-id="<?php echo e($id); ?>">
                    <?php if (is_file($imageFs)): ?>
                        <img src="<?php echo e($imageUrl); ?>" alt="<?php echo e($nombre); ?>" class="product-image">
                    <?php else: ?>
                        <div class="no-image-placeholder">Sin imagen</div>
                    <?php endif; ?>

                    <div class="product-card-header">
                        <a href="<?php echo e(url('products/' . $id)); ?>" class="product-name-link"><?php echo e($nombre); ?></a>
                        <div class="product-meta">SKU: <?php echo e($sku); ?></div>
                    </div>

                    <div class="product-card-prices">
                        <?php if ($precioEfectivo !== null): ?>
                            <div class="price-line"><span>Efectivo</span><strong>$<?php echo number_format($precioEfectivo, 0, ',', '.'); ?></strong></div>
                        <?php endif; ?>
                        <?php if ($precioTarjeta !== null): ?>
                            <div class="price-line"><span>Tarjeta</span><strong>$<?php echo number_format($precioTarjeta, 0, ',', '.'); ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <div class="product-card-stock">
                        <?php if ($stockTotal > 0): ?>
                            <span class="badge badge-success">Disponible (<?php echo $stockTotal; ?>)</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Sin stock</span>
                        <?php endif; ?>

                        <?php if (!empty($stockSucursales)): ?>
                            <ul class="stock-list">
                                <?php foreach ($stockSucursales as $stockInfo): ?>
                                    <?php
                                        $sucursal = $stockInfo['sucursal'] ?? ucfirst((string) ($stockInfo['node'] ?? 'Sucursal'));
                                        $cantidad = (int) ($stockInfo['cantidad'] ?? 0);
                                    ?>
                                    <li><span><?php echo e($sucursal); ?></span><strong><?php echo $cantidad; ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="product-actions-inline">
                        <a href="<?php echo e(url('products/' . $id)); ?>" class="btn btn-secondary full-width">Ver detalle</a>

                        <?php if ($puedeAgregar): ?>
                            <form method="post" action="<?php echo e(url('cart/add')); ?>" class="add-to-cart-form inline-form">
                                <input type="hidden" name="producto_id" value="<?php echo e($id); ?>">

                                <div class="form-row-2">
                                    <div class="form-group compact">
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
                                    </div>

                                    <div class="form-group compact">
                                        <label for="cantidad_<?php echo e($id); ?>">Cantidad</label>
                                        <select id="cantidad_<?php echo e($id); ?>" name="cantidad" required>
                                            <?php for ($i = 1; $i <= max(1, min(10, $defaultMaxQty)); $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $i === 1 ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary full-width">Agregar al carrito</button>
                            </form>
                        <?php else: ?>
                            <p class="text-muted">Sin stock para agregar.</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>