<?php
// admin/partials/header.php - Shared page header for admin pages: closes the
// <head> opened by head.php, opens <body>, renders the title/back/logout bar,
// the persistent section nav, and the standard success/deleted/error banners.
//
// Caller may set before including:
//   $pageH1         (string)  page heading, defaults to ''
//   $activeNav      (string)  one of 'products'|'antiguos'|'sections'|'producers'|'members'|'orders'|'product-summary'|'settings' to highlight
//   $backUrl        (string)  defaults to 'index.php'
//   $backLabel      (string)  defaults to '← Volver'
//   $showBackLink   (bool)    defaults to true
//   $successMessage (string)  shown when ?success is present, defaults to 'Guardado correctamente'
//   $deletedMessage (string)  shown when ?deleted is present, defaults to 'Eliminado correctamente'
$pageH1 = $pageH1 ?? '';
$activeNav = $activeNav ?? '';
$backUrl = $backUrl ?? 'index.php';
$backLabel = $backLabel ?? '← Volver';
$showBackLink = $showBackLink ?? true;

// Top-level nav items, some grouped: a group's own button links straight
// to its primary page (unchanged behavior/URL for every existing page),
// and its 'children' render as a smaller second row of pills -- but only
// once one of the group's pages is actually active, so the common case
// (most pages aren't part of a group) stays a single clean row.
$navGroups = [
    'carrito' => [
        'label' => 'Carrito',
        'href' => 'products.php',
        'children' => [
            'products' => ['label' => 'Productos', 'href' => 'products.php'],
            'antiguos' => ['label' => '🗄️ Antiguos', 'href' => 'products-antiguos.php'],
            'sections' => ['label' => 'Secciones', 'href' => 'sections.php'],
            'producers' => ['label' => 'Productores', 'href' => 'producers.php'],
        ],
    ],
    'members' => ['label' => '👥 Miembros', 'href' => 'members.php'],
    'orders' => [
        'label' => '📋 Pedidos',
        'href' => 'orders.php',
        'children' => [
            'orders' => ['label' => 'Pedido mercantes', 'href' => 'orders.php'],
            'product-summary' => ['label' => 'Pedido productores', 'href' => 'product-summary.php'],
        ],
    ],
    'settings' => ['label' => '⚙️ Configuración', 'href' => 'settings.php'],
];

// Which top-level group (if any) owns the current page -- either directly,
// or via one of its children -- and that group's children, if any, to show
// as the second-row sub-nav.
$activeGroupKey = null;
$activeChildren = null;
foreach ($navGroups as $groupKey => $group) {
    $children = $group['children'] ?? null;
    if ($groupKey === $activeNav || ($children && isset($children[$activeNav]))) {
        $activeGroupKey = $groupKey;
        $activeChildren = $children;
        break;
    }
}
?>
</head>
<body>
    <div class="admin-header">
        <h1><?php echo htmlspecialchars($pageH1); ?></h1>
        <div class="admin-header-actions">
            <?php if ($showBackLink): ?>
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="back-link-btn"><?php echo htmlspecialchars($backLabel); ?></a>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <nav class="admin-nav">
        <?php foreach ($navGroups as $key => $item): ?>
        <a href="<?php echo $item['href']; ?>" class="nav-btn<?php echo $key === $activeGroupKey ? ' active' : ''; ?>"><?php echo $item['label']; ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($activeChildren): ?>
    <nav class="admin-subnav">
        <?php foreach ($activeChildren as $childKey => $child): ?>
        <a href="<?php echo $child['href']; ?>" class="subnav-btn<?php echo $childKey === $activeNav ? ' active' : ''; ?>"><?php echo $child['label']; ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-message">✅ <?php echo htmlspecialchars($successMessage ?? 'Guardado correctamente'); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="success-message">✅ <?php echo htmlspecialchars($deletedMessage ?? 'Eliminado correctamente'); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="error-message">❌ <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
