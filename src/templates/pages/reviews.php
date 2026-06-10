<?php
$pedidos = $pedidos ?? [];
$isAdminView = (bool) ($is_admin_view ?? false);
$error = $error ?? null;

$pageTitle = $isAdminView
    ? 'Todos los pedidos - SisDist Marketplace'
    : 'Mis pedidos - SisDist Marketplace';
?>

<h1 class="page-title">
    <?php echo $isAdminView ? '📦 Todos los Pedidos' : '📦 Mis Pedidos'; ?>
</h1>

<div class="reviews-container">
    <section class="recent-orders">
        <h2>
            <?php echo $isAdminView ? 'Historial general de pedidos' : 'Historial de tus pedidos'; ?>
        </h2>

        <?php if ($msg = getFlash('success')): ?>
            <div class="success-message">
                <p><?php echo e($msg); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($msg = getFlash('error')): ?>
            <div class="error-message">
                <p><?php echo e($msg); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <p><?php echo e($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if (empty($pedidos)): ?>
            <div class="no-data">
                <p>
                    <?php echo $isAdminView
                        ? 'No hay pedidos registrados aún.'
                        : 'Todavía no tienes pedidos registrados.'; ?>
                </p>

                <?php if (!$isAdminView): ?>
                    <a href="<?php echo e(url('products')); ?>" class="btn btn-primary">
                        Ir a productos
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="reviews-table">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Número de Orden</th>

                        <?php if ($isAdminView): ?>
                            <th>Cliente ID</th>
                        <?php endif; ?>

                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Entrega</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <?php
                            $estado = $pedido['estado_pedido'] ?? 'desconocido';

                            $estadoClass = match ($estado) {
                                'pendiente'   => 'badge-warning',
                                'confirmado'  => 'badge-info',
                                'en_camino'   => 'badge-primary',
                                'entregado'   => 'badge-success',
                                'cancelado'   => 'badge-danger',
                                default       => 'badge-secondary',
                            };

                            $fecha = '-';
                            if (!empty($pedido['created_at'])) {
                                $timestamp = strtotime((string) $pedido['created_at']);
                                if ($timestamp !== false) {
                                    $fecha = date('d-m-Y H:i', $timestamp);
                                }
                            }
                        ?>
                        <tr data-id="<?php echo e($pedido['id'] ?? ''); ?>">
                            <td class="order-id">
                                #<?php echo e($pedido['id'] ?? ''); ?>
                            </td>

                            <td>
                                <?php echo e($pedido['numero_orden'] ?? '-'); ?>
                            </td>

                            <?php if ($isAdminView): ?>
                                <td>
                                    <?php echo e($pedido['cliente_id'] ?? '-'); ?>
                                </td>
                            <?php endif; ?>

                            <td>
                                <?php echo e($fecha); ?>
                            </td>

                            <td>
                                $<?php echo number_format((int) ($pedido['total_pagado'] ?? 0), 0, ',', '.'); ?>
                            </td>

                            <td>
                                <?php echo e($pedido['tipo_entrega'] ?? '-'); ?>
                            </td>

                            <td>
                                <span class="badge <?php echo e($estadoClass); ?>">
                                    <?php echo e(ucfirst(str_replace('_', ' ', $estado))); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <aside class="add-review-form">
        <h2>Estado del módulo</h2>

        <div class="no-data">
            <p>
                Esta pantalla ahora se está usando como historial de pedidos.
            </p>
            <p>
                El módulo de reseñas de productos sigue pendiente de implementación.
            </p>
        </div>

        <?php if (!$isAdminView): ?>
            <p class="help-text">
                Aquí podrás revisar tus compras recientes y más adelante también dejar reseñas.
            </p>
        <?php else: ?>
            <p class="help-text">
                Vista administrativa de pedidos globales por nodo.
            </p>
        <?php endif; ?>
    </aside>
</div>