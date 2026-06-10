<?php $pageTitle = 'Crear cuenta - SisDist Marketplace'; ?>

<section class="auth-page">
    <h1>Crear cuenta</h1>

    <?php if ($msg = getFlash('error')): ?>
        <div class="alert alert-danger"><?php echo e($msg); ?></div>
    <?php endif; ?>

    <?php if ($msg = getFlash('success')): ?>
        <div class="alert alert-success"><?php echo e($msg); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo e(url('register')); ?>" class="auth-form">
        <div class="form-group">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" required>
        </div>

        <div class="form-group">
            <label for="apellido">Apellido</label>
            <input type="text" id="apellido" name="apellido" required>
        </div>

        <div class="form-group">
            <label for="rut">RUT</label>
            <input type="text" id="rut" name="rut" required>
        </div>

        <div class="form-group">
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono">
        </div>

        <div class="form-group">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary">Crear cuenta</button>
    </form>

    <p>
        ¿Ya tienes cuenta?
        <a href="<?php echo e(url('login')); ?>">Iniciar sesión</a>
    </p>
</section>