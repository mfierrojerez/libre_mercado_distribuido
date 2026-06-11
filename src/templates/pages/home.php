<?php
$pageTitle = 'Inicio - SisDist Marketplace';
$productos = $productos ?? [];
$productosDestacados = array_slice($productos, 0, 6);
?>

<section class="hero">
    <h1>Bienvenido a SisDist Marketplace</h1>
    <p>Encuentra productos disponibles en múltiples sucursales, con precios actualizados y stock consolidado.</p>
    <a href="<?php echo e(url('products')); ?>" class="btn btn-primary">Ver catálogo completo</a>
</section>

<section class="featured-products">
    <h2>Productos destacados</h2>
    <div class="product-grid">
        <?php if (empty($productosDestacados)): ?>
            <div class="no-products"><p>No hay productos disponibles en este momento.</p></div>
        <?php else: ?>
            <?php foreach ($productosDestacados as $producto): ?>
                <?php 
                    $cardMode = 'compact';
                    include __DIR__ . '/../partials/product-card.php'; 
                ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
