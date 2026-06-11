<?php
$inventario = $inventario ?? [];
$selectedNode = strtolower((string) ($selectedNode ?? ($_GET['node'] ?? 'norte')));
$allowedNodes = ['norte' => 'Norte', 'sur' => 'Sur', 'centro' => 'Centro'];
if (!isset($allowedNodes[$selectedNode])) {
    $selectedNode = 'norte';
}
?>

<h1 class="page-title">Inventario</h1>

<div class="inventory-container">
    <section class="inventory-panel">
        <div class="reviews-panel-header">
            <div>
                <h2>Stock por sucursal</h2>
                <p class="reviews-subtitle">Sucursal activa: <?php echo e($allowedNodes[$selectedNode]); ?></p>
            </div>

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
        </div>

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
                            <th>Ajustar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventario as $item): ?>
                            <tr>
                                <td><?php echo e($item['sku'] ?? '-'); ?></td>
                                <td><?php echo e($item['nombre'] ?? '-'); ?></td>
                                <td><?php echo (int) ($item['stock_norte'] ?? 0); ?></td>
                                <td><?php echo (int) ($item['stock_sur'] ?? 0); ?></td>
                                <td><?php echo (int) ($item['stock_centro'] ?? 0); ?></td>
                                <td><span class="badge badge-info"><?php echo (int) ($item['stock_total'] ?? 0); ?></span></td>
                                <td>
                                    <form method="post" action="<?php echo e(url('inventory')); ?>" class="inventory-adjust-form">
                                        <input type="hidden" name="stock_id" value="<?php echo e($selectedNode === 'norte' ? ($item['stock_id_norte'] ?? '') : ($selectedNode === 'sur' ? ($item['stock_id_sur'] ?? '') : ($item['stock_id_centro'] ?? ''))); ?>">
                                        <input type="hidden" name="producto_id" value="<?php echo e($item['id'] ?? ''); ?>">
                                        <input type="hidden" name="sucursal_id" value="<?php echo e($selectedNode); ?>">
                                        <input type="hidden" name="node" value="<?php echo e($selectedNode); ?>">
                                        <div class="inventory-adjust-group">
                                            <input type="number" name="delta" class="form-control" placeholder="±Stock" required>
                                            <button type="submit" class="btn btn-primary">Guardar</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>