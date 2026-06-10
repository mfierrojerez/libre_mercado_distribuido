<footer class="site-footer">
    <div class="footer-content">
        <p>&copy; <?= date('Y'); ?> SisDist Marketplace. Todos los derechos reservados.</p>

        <nav class="footer-nav">
            <a href="<?= e(url()); ?>">Inicio</a> |
            <a href="<?= e(url('products')); ?>">Productos</a> |
            <a href="<?= e(url('inventory')); ?>">Inventario</a> |
            <a href="<?= e(url('reviews')); ?>">Reseñas</a>
        </nav>
    </div>
</footer>

<script src="<?= e(asset('assets/js/app.js')); ?>"></script>