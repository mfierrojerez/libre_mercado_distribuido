<header class="site-header">
    <nav class="main-nav">
        <a href="<?= e(url()); ?>" class="logo">SisDist Marketplace</a>

        <ul class="nav-links">
            <li><a href="<?= e(url()); ?>">Inicio</a></li>
            <li><a href="<?= e(url('products')); ?>">Productos</a></li>
            <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
                <li><a href="<?= e(url('admin-orders')); ?>">Pedidos</a></li>
                <li><a href="<?= e(url('inventory')); ?>">Inventario</a></li>
            <?php else: ?>
                <li><a href="<?= e(url('reviews')); ?>">Mis pedidos</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="node-indicator" title="<?= e($dataContext['display_name'] ?? ''); ?>">
        <?php
            $canWriteLocal  = $dataContext['can_write_local'] ?? false;
            $canWriteMatriz = $dataContext['can_write_matriz'] ?? false;

            if ($canWriteMatriz) {
                $badgeClass = 'badge-success';
                $badgeText  = ($dataContext['display_name'] ?? '') . ' · Matriz';
            } elseif ($canWriteLocal) {
                $badgeClass = 'badge-info';
                $badgeText  = ($dataContext['display_name'] ?? '') . ' · Local';
            } else {
                $badgeClass = 'badge-secondary';
                $badgeText  = $dataContext['display_name'] ?? '';
            }
        ?>
        <span class="node-badge <?= e($badgeClass); ?>">
            <?= e($badgeText); ?>
        </span>
    </div>

    <div class="header-actions">
        <a href="<?= e(url('cart')); ?>" class="cart-link">
            🛒 Carrito (<?= (int) ($carritoCount ?? 0); ?>)
        </a>

        <?php if (isAuthenticated()): ?>
            <span class="user-greeting">
                Hola, <?= e(currentUserName() ?? 'Cliente'); ?>
            </span>

            <form method="post" action="<?= e(url('logout')); ?>" class="logout-form">
                <button type="submit" class="btn btn-secondary">Cerrar sesión</button>
            </form>
        <?php else: ?>
            <a href="<?= e(url('login')); ?>" class="btn btn-secondary">Iniciar sesión</a>
            <a href="<?= e(url('register')); ?>" class="btn btn-primary">Crear cuenta</a>
        <?php endif; ?>
    </div>
</header>