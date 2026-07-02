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

    <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
    <?php
        $statesFile = __DIR__ . '/../config/node_states.json';
        $states = is_file($statesFile) ? (json_decode(file_get_contents($statesFile), true) ?: []) : [];
        $norteState = $states['norte'] ?? 'ONLINE';
        $surState = $states['sur'] ?? 'ONLINE';
        $centroState = $states['centro'] ?? 'ONLINE';
    ?>
    <div class="admin-node-simulator" style="display: flex; gap: 10px; align-items: center; background: #fee; padding: 5px 10px; border-radius: 5px; border: 1px solid #fcc;">
        <strong style="color: #c00; font-size: 0.9em;">Simulador:</strong>
        <label style="font-size: 0.8em;">Norte <select class="node-state-select" data-node="norte"><option value="ONLINE" <?= $norteState==='ONLINE'?'selected':''?>>ON</option><option value="OFFLINE" <?= $norteState==='OFFLINE'?'selected':''?>>OFF</option></select></label>
        <label style="font-size: 0.8em;">Sur <select class="node-state-select" data-node="sur"><option value="ONLINE" <?= $surState==='ONLINE'?'selected':''?>>ON</option><option value="OFFLINE" <?= $surState==='OFFLINE'?'selected':''?>>OFF</option></select></label>
        <label style="font-size: 0.8em;">Centro <select class="node-state-select" data-node="centro"><option value="ONLINE" <?= $centroState==='ONLINE'?'selected':''?>>ON</option><option value="OFFLINE" <?= $centroState==='OFFLINE'?'selected':''?>>OFF</option></select></label>
        
        <button id="btnSyncReverse" class="btn" style="background:#ffc107; color:#000; border:none; padding:3px 8px; border-radius:3px; font-size:0.8em; cursor:pointer;" onclick="syncReverse()">
            Sincronizar Ventas Huérfanas
        </button>
        <script>
            function syncReverse() {
                const btn = document.getElementById('btnSyncReverse');
                btn.disabled = true;
                btn.innerText = 'Sincronizando...';

                fetch('<?= e(url("admin/sync-reverse")); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToastAdmin(`Sincronización completada. Órdenes: ${data.recovered}`);
                    } else {
                        showToastAdmin(`Error: ${data.error}`, true);
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToastAdmin('Error en la solicitud.', true);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerText = 'Sincronizar Ventas Huérfanas';
                });
            }

        <script>
            function showToastAdmin(msg, isError = false) {
                const toast = document.createElement('div');
                toast.textContent = msg;
                toast.style.position = 'fixed';
                toast.style.bottom = '20px';
                toast.style.right = '20px';
                toast.style.padding = '15px';
                toast.style.background = isError ? '#f44336' : '#4CAF50';
                toast.style.color = 'white';
                toast.style.borderRadius = '5px';
                toast.style.zIndex = '99999';
                toast.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 4000);
            }

            document.querySelectorAll('.node-state-select').forEach(sel => {
                sel.addEventListener('change', function() {
                    const node = this.dataset.node;
                    const state = this.value;
                    
                    this.disabled = true; // prevent double click while syncing
                    const originalBg = this.style.background;
                    this.style.background = '#e0e0e0';

                    fetch('<?= e(url("admin/node-state")); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ node: node, state: state })
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.disabled = false;
                        this.style.background = originalBg;
                        
                        if (data.success) {
                            showToastAdmin(data.message);
                            if (state === 'OFFLINE' || data.message.includes('sincronizado')) {
                                setTimeout(() => location.reload(), 2000);
                            }
                        } else {
                            showToastAdmin('Error: ' + data.error, true);
                        }
                    })
                    .catch(err => {
                        this.disabled = false;
                        this.style.background = originalBg;
                        showToastAdmin('Error de red al cambiar estado', true);
                    });
                });
            });
        </script>
    </div>
    <?php endif; ?>

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