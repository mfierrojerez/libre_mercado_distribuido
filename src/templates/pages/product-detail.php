<?php
$producto = $producto ?? null;
$pageTitle = 'Detalle de Producto - ' . ($producto['nombre'] ?? 'Producto');

if (!$producto): ?>
    <div class="error-message">
        <h2>Producto no encontrado</h2>
        <a href="<?php echo e(url('products')); ?>" class="btn btn-secondary">Volver a productos</a>
    </div>
<?php
    return;
endif;

$nombre          = $producto['nombre'] ?? 'Producto sin nombre';
$sku             = $producto['sku'] ?? 'Sin SKU';
$descripcion     = $producto['descripcion'] ?? '';
$pesoGramos      = $producto['peso_gramos'] ?? null;
$precioEfectivo  = isset($producto['precio_efectivo']) ? (int) $producto['precio_efectivo'] : null;
$precioTarjeta   = isset($producto['precio_tarjeta']) ? (int) $producto['precio_tarjeta'] : null;
$stockTotal      = isset($producto['stock_total']) ? (int) $producto['stock_total'] : 0;
$stockSucursales = $producto['stock_por_sucursal'] ?? [];
$preciosOtros    = $precios_otros ?? [];

$defaultSucursalId = '';
$defaultMaxQty = 0;
foreach ($stockSucursales as $stockInfo) {
    $cantidad = (int) ($stockInfo['cantidad'] ?? 0);
    if ($cantidad > 0) {
        $defaultSucursalId = (string) ($stockInfo['sucursal_id'] ?? '');
        $defaultMaxQty = $cantidad;
        break;
    }
}

$skuFile = trim((string) $sku);
$skuFile = preg_replace('/[^a-z0-9\-]+/i', '-', $skuFile);
$skuFile = trim($skuFile, '-');
$imageFs = dirname(__DIR__, 2) . '/public/images/' . $skuFile . '.jpg';
$imageUrl = '/images/' . $skuFile . '.jpg';
$hasImage = is_file($imageFs);
?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger">
        <?php echo e($_SESSION['flash_error']); ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">
        <?php echo e($_SESSION['flash_success']); ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<div class="product-detail">
    <nav class="breadcrumb">
        <a href="<?php echo e(url()); ?>">Inicio</a>
        <a href="<?php echo e(url('products')); ?>">Productos</a>
        <span><?php echo e($nombre); ?></span>
    </nav>

    <div class="product-detail-grid">
        <div class="product-images">
            <?php if ($hasImage): ?>
                <img src="<?php echo e($imageUrl); ?>" alt="<?php echo e($nombre); ?>" class="product-main-image">
            <?php else: ?>
                <div class="no-image-placeholder">Sin imagen</div>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <h1><?php echo e($nombre); ?></h1>

            <p class="product-meta">
                <span><strong>SKU:</strong> <?php echo e($sku); ?></span>
                <?php if (!empty($pesoGramos)): ?>
                    <span><strong>Peso:</strong> <?php echo (int) $pesoGramos; ?> g</span>
                <?php endif; ?>
            </p>

            <?php if (!empty($descripcion)): ?>
                <div class="product-description">
                    <h3>Descripción</h3>
                    <p><?php echo nl2br(e($descripcion)); ?></p>
                </div>
            <?php endif; ?>

            <div class="price-section">
                <?php if ($precioEfectivo !== null): ?>
                    <div class="price-row">
                        <span class="label">Precio efectivo</span>
                        <span class="price">$<?php echo number_format($precioEfectivo, 0, ',', '.'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($precioTarjeta !== null): ?>
                    <div class="price-row">
                        <span class="label">Precio tarjeta</span>
                        <span class="price secondary">$<?php echo number_format($precioTarjeta, 0, ',', '.'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="stock-info">
                    <?php if ($stockTotal > 0): ?>
                        <span class="in-stock">Stock total disponible: <?php echo $stockTotal; ?> unidades</span>
                    <?php else: ?>
                        <span class="out-of-stock">Sin stock disponible</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($stockSucursales)): ?>
                <section class="stock-by-branch">
                    <h3>Disponibilidad por sucursal</h3>
                    <div class="branch-stock-list">
                        <?php foreach ($stockSucursales as $stockInfo): ?>
                            <?php
                                $sucursal = $stockInfo['sucursal'] ?? ucfirst((string) ($stockInfo['node'] ?? 'Sucursal'));
                                $cantidad = (int) ($stockInfo['cantidad'] ?? 0);
                            ?>
                            <article class="branch-stock-card">
                                <h4><?php echo e($sucursal); ?></h4>
                                <?php if ($cantidad > 0): ?>
                                    <p class="in-stock">Disponible: <?php echo $cantidad; ?></p>
                                <?php else: ?>
                                    <p class="out-of-stock">Sin stock</p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($stockTotal > 0 && $defaultSucursalId !== ''): ?>
                <form method="post" action="<?php echo e(url('cart/add')); ?>" class="add-to-cart-form">
                    <input type="hidden" name="producto_id" value="<?php echo e($producto['id'] ?? ''); ?>">

                    <label for="sucursal_id">Sucursal de origen</label>
                    <select id="sucursal_id" name="sucursal_id" required>
                        <?php foreach ($stockSucursales as $stockInfo): ?>
                            <?php
                                $sid = (string) ($stockInfo['sucursal_id'] ?? '');
                                $sucursal = $stockInfo['sucursal'] ?? ucfirst((string) ($stockInfo['node'] ?? 'Sucursal'));
                                $cantidad = (int) ($stockInfo['cantidad'] ?? 0);
                                if ($sid === '' || $cantidad <= 0) {
                                    continue;
                                }
                            ?>
                            <option value="<?php echo e($sid); ?>" <?php echo $sid === $defaultSucursalId ? 'selected' : ''; ?>>
                                <?php echo e($sucursal); ?> (<?php echo $cantidad; ?> disponibles)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="quantity">Cantidad</label>
                    <select id="quantity" name="cantidad" required>
                        <?php $max = min($defaultMaxQty, 10); ?>
                        <?php for ($i = 1; $i <= $max; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i === 1 ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>

                    <button type="submit" class="btn btn-primary">Agregar al Carrito</button>
                </form>
            <?php else: ?>
                <div class="cart-message warning">Este producto no tiene disponibilidad actual en ninguna sucursal.</div>
            <?php endif; ?>

            <div id="cartMessage"></div>
        </div>
    </div>

    <?php if (!empty($preciosOtros)): ?>
        <section class="other-prices-section">
            <h2>Precios en otras sucursales</h2>
            <div class="branch-prices">
                <?php foreach ($preciosOtros as $fila): ?>
                    <article class="branch-price-card">
                        <h3><?php echo e($fila['sucursal'] ?? 'Sucursal'); ?></h3>
                        <p>Efectivo: <strong>$<?php echo number_format((int) ($fila['precio_efectivo'] ?? 0), 0, ',', '.'); ?></strong></p>
                        <p>Tarjeta: <strong>$<?php echo number_format((int) ($fila['precio_tarjeta'] ?? 0), 0, ',', '.'); ?></strong></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function showToast(msg, isError = false) {
        const toast = document.createElement('div');
        toast.textContent = msg;
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.padding = '15px';
        toast.style.background = isError ? '#f44336' : '#4CAF50';
        toast.style.color = 'white';
        toast.style.borderRadius = '5px';
        toast.style.zIndex = '9999';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    const form = document.querySelector('.add-to-cart-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const oldText = btn.textContent;
            btn.textContent = 'Agregando...';
            btn.disabled = true;

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new FormData(form)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Agregado al carrito');
                } else {
                    showToast(data.error || 'Error al agregar', true);
                }
            })
            .catch(err => {
                showToast('Error de conexión', true);
            })
            .finally(() => {
                btn.textContent = oldText;
                btn.disabled = false;
            });
        });
    }
});
</script>