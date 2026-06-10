<?php $pageTitle = 'Iniciar sesión - SisDist Marketplace'; ?>

<section class="auth-page">
    <h1>Iniciar sesión</h1>

    <?php if ($msg = getFlash('error')): ?>
        <div class="alert alert-danger"><?php echo e($msg); ?></div>
    <?php endif; ?>

    <?php if ($msg = getFlash('success')): ?>
        <div class="alert alert-success"><?php echo e($msg); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo e(url('login')); ?>" class="auth-form">
        <div class="form-group">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary">Ingresar</button>
    </form>

    <p>
        ¿No tienes cuenta?
        <a href="<?php echo e(url('register')); ?>">Crear cuenta</a>
    </p>
</section>