<?php
declare(strict_types=1);

$content   = $content ?? '';
$nodeInfo  = $data['node_info'] ?? Database::getInstance()->getNodeInfo();
$config    = $data['config'] ?? getNodeConfig();
$pageTitle = $data['pageTitle'] ?? 'SisDist Marketplace';

// Resolver carrito actual para header
$sessionToken = $_SESSION['cart_token'] ?? null;
$clienteId    = $_SESSION['cliente_id'] ?? null;

$carrito = $data['carrito'] ?? getCarrito($sessionToken, $clienteId);
$carritoCount = (int) ($carrito['total_items'] ?? 0);

$dataContext = [
    'node_type'        => $nodeInfo['type'] ?? '',
    'node_name'        => $nodeInfo['name'] ?? '',
    'display_name'     => getDisplayName(),
    'can_write_local'  => canWriteLocal(),
    'can_write_matriz' => canWriteMatriz(),
];

if ($content === '' || !is_file($content)) {
    http_response_code(500);
    throw new RuntimeException('Template de contenido no válido: ' . $content);
};

if (!empty($data) && is_array($data)) {
    extract($data, EXTR_SKIP);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>

    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')); ?>">

    <meta name="node-type" content="<?= e($nodeInfo['type'] ?? ''); ?>">
    <meta name="node-name" content="<?= e($nodeInfo['name'] ?? ''); ?>">
</head>
<body data-node="<?= e($nodeInfo['type'] ?? ''); ?>" class="body-<?= e($nodeInfo['type'] ?? ''); ?>">

    <?php require __DIR__ . '/partials/header.php'; ?>

    <main class="container mt-4">
        <?php require $content; ?>
    </main>

    <?php require __DIR__ . '/partials/footer.php'; ?>

    <script>
        window.APP_BASE_URL = <?= jsonForHtml(appBaseUrl()); ?>;
        window.NODE_INFO = <?= jsonForHtml($nodeInfo); ?>;
        window.CONFIG = <?= jsonForHtml($config); ?>;

        console.log('[NODE] Iniciando en nodo:', window.NODE_INFO.type, '-', window.NODE_INFO.name);
    </script>
</body>
</html>