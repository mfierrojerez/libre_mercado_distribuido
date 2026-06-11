<?php
$pedidos = $pedidos ?? [];
$isAdminView = (bool) ($is_admin_view ?? false);
$error = $error ?? null;
$selectedNode = trim((string) ($_GET['node'] ?? currentNodeType()));
$allowedNodes = [
    'norte' => 'Norte',
    'sur' => 'Sur',
    'centro' => 'Centro',
];

$pageTitle = $isAdminView
    ? 'Todos los pedidos - SisDist Marketplace'
    : 'Mis pedidos - SisDist Marketplace';
?>

<h1 class="page-title">
    <?php echo $isAdminView ? '📦 Todos los Pedidos' : '📦 Mis Pedidos'; ?>
</h1>

<div class="reviews-container reviews-admin-shell">
    <section class="recent-orders reviews-main-panel">
        <div class="reviews-panel-header">
            <div>
                <h2>
                    <?php echo $isAdminView ? 'Historial general de pedidos' : 'Historial de tus pedidos'; ?>
                </h2>
                <p class="reviews-subtitle">
                    <?php echo $isAdminView
                        ? 'Vista administrativa con cambio de estado por pedido.'
                        : 'Resumen de tus compras recientes.'; ?>
                </p>
            </div>

            <?php if ($isAdminView): ?>
                <form method="get" class="reviews-node-filter">
                    <label for="node">Nodo</label>
                    <select name="node" id="node" class="form-control reviews-node-select" onchange="this.form.submit()">
                        <?php foreach ($allowedNodes as $nodeKey => $nodeLabel): ?>
                            <option value="<?php echo e($nodeKey); ?>" <?php echo $selectedNode === $nodeKey ? 'selected' : ''; ?>>
                                <?php echo e($nodeLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>

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
                <p><?php echo $isAdminView ? 'No hay pedidos registrados aún.' : 'Todavía no tienes pedidos registrados.'; ?></p>
                <?php if (!$isAdminView): ?>
                    <a href="<?php echo e(url('products')); ?>" class="btn btn-primary">Ir a productos</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive reviews-table-wrap">
                <table class="reviews-table reviews-orders-table">
                    <thead>
                        <tr>
                            <th>Número de Orden</th>
                            <?php if ($isAdminView): ?>
                                <th>Cliente ID</th>
                            <?php endif; ?>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Entrega</th>
                            <th>Estado</th>
                            <?php if ($isAdminView): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                            <?php
                                $pedidoId = (string) ($pedido['id'] ?? '');
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
                            <tr data-id="<?php echo e($pedidoId); ?>">
                                <td><?php echo e($pedido['numero_orden'] ?? '-'); ?></td>
                                <?php if ($isAdminView): ?>
                                    <td><?php echo e($pedido['cliente_id'] ?? '-'); ?></td>
                                <?php endif; ?>
                                <td><?php echo e($fecha); ?></td>
                                <td>$<?php echo number_format((int) ($pedido['total_pagado'] ?? 0), 0, ',', '.'); ?></td>
                                <td><?php echo e($pedido['tipo_entrega'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo e($estadoClass); ?>">
                                        <?php echo e(ucfirst(str_replace('_', ' ', $estado))); ?>
                                    </span>
                                </td>
                                <?php if ($isAdminView): ?>
                                    <td>
                                        <form method="post" action="<?php echo e(url('orders/status')); ?>" class="order-status-form">
                                            <input type="hidden" name="pedido_id" value="<?php echo e($pedidoId); ?>">
                                            <input type="hidden" name="node" value="<?php echo e($selectedNode); ?>">
                                            <select name="estado_pedido" class="form-control order-status-select" required>
                                                <option value="pendiente" <?php echo $estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                                <option value="confirmado" <?php echo $estado === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                                                <option value="en_camino" <?php echo $estado === 'en_camino' ? 'selected' : ''; ?>>En camino</option>
                                                <option value="entregado" <?php echo $estado === 'entregado' ? 'selected' : ''; ?>>Entregado</option>
                                                <option value="cancelado" <?php echo $estado === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm">Actualizar</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>