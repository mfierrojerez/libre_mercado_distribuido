<?php
$inventario = $inventario ?? [];
$pageTitle = 'Inventario - SisDist Marketplace';
?>

<h1 class="page-title">Inventario</h1>

<div class="inventory-container">
    <section class="inventory-panel">
        <h2>Stock por sucursal</h2>

        <?php if (empty($inventario)): ?>
            <div class="no-data">
                <p>No hay datos de inventario disponibles.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="reviews-table inventory-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Producto</th>
                            <th>Norte</th>
                            <th>Sur</th>
                            <th>Centro</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventario as $item): ?>
                            <?php
                                $total = (int) ($item['stock_total'] ?? 0);

                                $totalClass = match (true) {
                                    $total <= 0 => 'badge-danger',
                                    $total <= 5 => 'badge-warning',
                                    default => 'badge-success',
                                };
                            ?>
                            <tr>
                                <td><?= e($item['sku'] ?? '-'); ?></td>
                                <td><?= e($item['nombre'] ?? '-'); ?></td>
                                <td><?= (int) ($item['stock_norte'] ?? 0); ?></td>
                                <td><?= (int) ($item['stock_sur'] ?? 0); ?></td>
                                <td><?= (int) ($item['stock_centro'] ?? 0); ?></td>
                                <td>
                                    <span class="badge <?= e($totalClass); ?>">
                                        <?= $total; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <aside class="inventory-summary">
        <h2>Lectura rápida</h2>

        <div class="no-data">
            <p>
                Esta vista es solo para administración y consolida el stock total por producto
                entre Norte, Sur y Centro.
            </p>
            <p>
                Los totales bajos ayudan a detectar quiebres de inventario antes del checkout.
            </p>
        </div>
    </aside>
</div>