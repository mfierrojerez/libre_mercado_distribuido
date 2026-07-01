<?php
// src/templates/pages/checkout.php

$carrito = $carrito ?? [
    'carrito_id'  => null,
    'items'       => [],
    'total_items' => 0,
    'subtotal'    => 0,
];

$direcciones = $direcciones ?? [];
$pageTitle = 'Checkout - SisDist Marketplace';
?>

<h1 class="page-title">Checkout</h1>

<?php if (!isAuthenticated()): ?>
    <div class="error-message">
        <p>Debes iniciar sesión o crear una cuenta para finalizar tu compra.</p>

        <div class="auth-actions">
            <a href="<?php echo e(url('login')); ?>" class="btn btn-secondary">Iniciar sesión</a>
            <a href="<?php echo e(url('register')); ?>" class="btn btn-primary">Crear cuenta</a>
        </div>
    </div>

<?php elseif (empty($carrito['items'])): ?>
    <div class="error-message">
        <p>Tu carrito está vacío. No puedes realizar un pedido.</p>
        <a href="<?php echo e(url('products')); ?>" class="btn btn-primary">Ir a la tienda</a>
    </div>

<?php else: ?>
    <div class="checkout-container">
        <section class="checkout-form">
            <h2>Datos del pedido</h2>

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

            <form id="checkoutForm" action="<?php echo e(url('orders/create')); ?>" method="post">
                <div class="form-group">
                    <label for="tipo_entrega">Tipo de entrega</label>
                    <select id="tipo_entrega" name="tipo_entrega" required>
                        <option value="">Selecciona una opción</option>
                        <option value="retiro_tienda">Retiro en tienda</option>
                        <option value="despacho_domicilio">Despacho a domicilio</option>
                    </select>
                </div>

                <div class="form-group" id="direccionGroup" style="display: none;">
                    <label for="direccion_despacho_id">Dirección de despacho</label>
                    <select id="direccion_despacho_id" name="direccion_despacho_id">
                        <option value="">Selecciona una dirección</option>
                        <?php foreach ($direcciones as $direccion): ?>
                            <?php
                                $direccionTexto = trim(
                                    ($direccion['calle'] ?? '') . ' ' .
                                    ($direccion['numero'] ?? '') .
                                    (!empty($direccion['depto_block']) ? ', ' . $direccion['depto_block'] : '')
                                );

                                if (!empty($direccion['ciudad_nombre'])) {
                                    $direccionTexto .= ' - ' . $direccion['ciudad_nombre'];
                                }
                            ?>
                            <option value="<?php echo e($direccion['id'] ?? ''); ?>">
                                <?php echo e($direccionTexto); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Debes seleccionar una dirección registrada para despacho.</small>
                </div>

                <div class="form-group">
                    <label for="metodo_pago">Método de pago</label>
                    <select id="metodo_pago" name="metodo_pago">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                    <small>Por ahora este dato es informativo; el pedido se guarda con los totales calculados por backend.</small>
                </div>

                <div class="order-summary">
                    <h3>Resumen del pedido</h3>

                    <?php foreach ($carrito['items'] as $item): ?>
                        <?php
                            $nombre         = $item['nombre'] ?? 'Producto sin nombre';
                            $sucursalNombre = $item['sucursal_nombre'] ?? 'Sucursal desconocida';
                            $cantidad       = (int) ($item['cantidad'] ?? 0);
                            $precio         = (float) ($item['precio_efectivo'] ?? 0);
                            $subtotalItem   = (float) ($item['subtotal_item'] ?? ($precio * $cantidad));
                        ?>
                        <div class="summary-item">
                            <div>
                                <span><?php echo e($nombre); ?></span><br>
                                <small>Sucursal origen: <?php echo e($sucursalNombre); ?></small>
                            </div>
                            <span>x<?php echo $cantidad; ?></span>
                            <span>$<?php echo number_format($subtotalItem, 0, ',', '.'); ?></span>
                        </div>
                    <?php endforeach; ?>

                    <hr>

                    <div class="summary-line">
                        <span>Subtotal productos</span>
                        <strong>$<?php echo number_format((float) ($carrito['subtotal'] ?? 0), 0, ',', '.'); ?></strong>
                    </div>

                    <div class="summary-line">
                        <span>Despacho</span>
                        <strong id="shippingCost">$0</strong>
                    </div>

                    <div class="summary-total">
                        <span>Total</span>
                        <strong id="orderTotal" data-subtotal="<?php echo (float) ($carrito['subtotal'] ?? 0); ?>">
                            $<?php echo number_format((float) ($carrito['subtotal'] ?? 0), 0, ',', '.'); ?>
                        </strong>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary full-width">Confirmar Pedido</button>
            </form>
        </section>

        <aside class="checkout-info">
            <h2>Información importante</h2>

            <div class="info-box info-info">
                <strong>Tipo de entrega</strong>
                <p>Puedes retirar en tienda o solicitar despacho a domicilio según tus direcciones registradas.</p>
            </div>

            <div class="info-box info-warning">
                <strong>Costo de despacho</strong>
                <p>El despacho a domicilio agrega un cargo fijo. Retiro en tienda no tiene costo adicional.</p>
            </div>

            <div class="info-box info-success">
                <strong>Confirmación</strong>
                <p>Al confirmar, el pedido debe generarse usando la sucursal origen correspondiente a los items del carrito.</p>
            </div>
        </aside>
    </div>

    <script>
        (function () {
            const tipoEntrega = document.getElementById('tipo_entrega');
            const direccionGroup = document.getElementById('direccionGroup');
            const direccionSelect = document.getElementById('direccion_despacho_id');
            const shippingCost = document.getElementById('shippingCost');
            const orderTotal = document.getElementById('orderTotal');

            const subtotal = parseInt(orderTotal.dataset.subtotal || '0', 10);
            const despachoFijo = 3990;

            function formatCLP(value) {
                return '$' + value.toLocaleString('es-CL');
            }

            function updateCheckoutState() {
                const tipo = tipoEntrega.value;
                const shipping = tipo === 'despacho_domicilio' ? despachoFijo : 0;
                const total = subtotal + shipping;

                shippingCost.textContent = formatCLP(shipping);
                orderTotal.textContent = formatCLP(total);

                if (tipo === 'despacho_domicilio') {
                    direccionGroup.style.display = 'block';
                    direccionSelect.setAttribute('required', 'required');
                } else {
                    direccionGroup.style.display = 'none';
                    direccionSelect.removeAttribute('required');
                    direccionSelect.value = '';
                }
            }

            tipoEntrega.addEventListener('change', updateCheckoutState);
            updateCheckoutState();

            const form = document.getElementById('checkoutForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const btn = form.querySelector('button[type="submit"]');
                    const oldText = btn.textContent;
                    btn.textContent = 'Cargando...';
                    btn.disabled = true;

                    fetch(form.action, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: new FormData(form)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message || 'Pedido confirmado');
                            window.location.href = '<?= e(url("reviews")) ?>';
                        } else {
                            alert(data.error || 'Error al confirmar');
                            btn.textContent = oldText;
                            btn.disabled = false;
                        }
                    })
                    .catch(err => {
                        alert('Error de conexión');
                        btn.textContent = oldText;
                        btn.disabled = false;
                    });
                });
            }
        })();
    </script>
<?php endif; ?>