<?php
// src/templates/pages/cart.php

$carrito = $carrito ?? [
    'carrito_id'  => null,
    'items'       => [],
    'total_items' => 0,
    'subtotal'    => 0,
];

$pageTitle = 'Carrito de Compras - SisDist Marketplace';
?>

<h1 class="page-title">Tu Carrito</h1>

<?php if (empty($carrito['items'])): ?>
    <div class="empty-cart">
        <p>Tu carrito está vacío.</p>
        <a href="<?php echo e(url('products')); ?>" class="btn btn-primary">Continuar comprando</a>
    </div>
<?php else: ?>
    <?php if (!isAuthenticated()): ?>
        <div class="info-box info-warning auth-cart-warning">
            <strong>Antes de finalizar tu compra</strong>
            <p>Debes iniciar sesión o crear una cuenta para poder confirmar el pedido en checkout.</p>

            <div class="auth-actions">
                <a href="<?php echo e(url('login')); ?>" class="btn btn-secondary">Iniciar sesión</a>
                <a href="<?php echo e(url('register')); ?>" class="btn btn-primary">Crear cuenta</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($msg = getFlash('error')): ?>
        <div class="error-message">
            <p><?php echo e($msg); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($msg = getFlash('success')): ?>
        <div class="success-message">
            <p><?php echo e($msg); ?></p>
        </div>
    <?php endif; ?>

    <div class="cart-container">
        <div class="cart-items">
            <?php foreach ($carrito['items'] as $item): ?>
                <?php
                    $itemId         = (string) ($item['id'] ?? '');
                    $productoId     = (string) ($item['producto_id'] ?? '');
                    $nombre         = $item['nombre'] ?? 'Producto sin nombre';
                    $sku            = $item['sku'] ?? 'Sin SKU';
                    $sucursalNombre = $item['sucursal_nombre'] ?? 'Sucursal desconocida';
                    $cantidad       = (int) ($item['cantidad'] ?? 0);
                    $precio         = (float) ($item['precio_efectivo'] ?? 0);
                    $subtotalItem   = (float) ($item['subtotal_item'] ?? ($precio * $cantidad));
                ?>
                <article
                    class="cart-item"
                    data-id="<?php echo e($itemId); ?>"
                    data-total="<?php echo e((string) $subtotalItem); ?>"
                >
                    <div class="item-image">
                        <div class="no-image-placeholder">Sin imagen</div>
                    </div>

                    <div class="item-details">
                        <h3><?php echo e($nombre); ?></h3>
                        <p class="sku">SKU: <?php echo e($sku); ?></p>
                        <p class="branch-origin">
                            <strong>Sucursal origen:</strong> <?php echo e($sucursalNombre); ?>
                        </p>

                        <div class="item-pricing">
                            <span class="unit-price">$<?php echo number_format($precio, 0, ',', '.'); ?></span>
                            <span class="quantity">x <?php echo $cantidad; ?></span>
                            <span class="total-price">$<?php echo number_format($subtotalItem, 0, ',', '.'); ?></span>
                        </div>

                        <form
                            method="post"
                            action="<?php echo e(url('cart/update')); ?>"
                            class="quantity-form"
                        >
                            <input type="hidden" name="item_id" value="<?php echo e($itemId); ?>">

                            <label for="qty-<?php echo e($itemId); ?>">Cantidad</label>
                            <select id="qty-<?php echo e($itemId); ?>" name="cantidad">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i === $cantidad ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>

                            <button type="submit" class="btn btn-secondary">Actualizar</button>
                        </form>

                        <form
                            method="post"
                            action="<?php echo e(url('cart/remove')); ?>"
                            class="remove-form"
                            onsubmit="return confirm('¿Estás seguro de eliminar este producto del carrito?');"
                        >
                            <input type="hidden" name="item_id" value="<?php echo e($itemId); ?>">
                            <button type="submit" class="btn btn-danger">Eliminar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="cart-summary">
            <h2>Resumen</h2>

            <table class="summary-table">
                <tr>
                    <th>Items</th>
                    <td><?php echo (int) ($carrito['total_items'] ?? 0); ?></td>
                </tr>
                <tr>
                    <th>Subtotal productos</th>
                    <td>$<?php echo number_format((float) ($carrito['subtotal'] ?? 0), 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <th>Envío</th>
                    <td><span class="shipping-cost">Se calcula en checkout</span></td>
                </tr>
                <tr class="total-row">
                    <th>Total parcial</th>
                    <td>$<?php echo number_format((float) ($carrito['subtotal'] ?? 0), 0, ',', '.'); ?></td>
                </tr>
            </table>

            <div id="cartMessage"></div>

            <div class="cart-actions">
                <?php if (isAuthenticated()): ?>
                    <a href="<?php echo e(url('checkout')); ?>" class="btn btn-primary full-width">Proceder al Checkout</a>
                <?php else: ?>
                    <a href="<?php echo e(url('login')); ?>" class="btn btn-primary full-width">Iniciar sesión para comprar</a>
                    <a href="<?php echo e(url('register')); ?>" class="btn btn-secondary full-width">Crear cuenta</a>
                <?php endif; ?>

                <form
                    method="post"
                    action="<?php echo e(url('cart/clear')); ?>"
                    onsubmit="return confirm('¿Vaciar todo el carrito?');"
                >
                    <button type="submit" class="btn btn-secondary full-width">Vaciar carrito</button>
                </form>
            </div>
        </aside>
    </div>
<?php endif; ?>