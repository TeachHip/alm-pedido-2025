<?php
// admin/partials/header.php - Shared page header for admin pages: closes the
// <head> opened by head.php, opens <body>, renders the title/back/logout bar,
// the persistent section nav, and the standard success/deleted/error banners.
//
// Caller may set before including:
//   $pageH1         (string)  page heading, defaults to ''
//   $activeNav      (string)  one of 'products'|'sections'|'members'|'orders'|'settings' to highlight
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

$navItems = [
    'products' => ['label' => 'Productos', 'href' => 'products.php'],
    'sections' => ['label' => 'Secciones', 'href' => 'sections.php'],
    'members' => ['label' => '👥 Miembros', 'href' => 'members.php'],
    'orders' => ['label' => '📋 Pedidos', 'href' => 'orders.php'],
    'settings' => ['label' => '⚙️ Configuración', 'href' => 'settings.php'],
];
?>
</head>
<body>
    <div class="admin-header">
        <h1><?php echo htmlspecialchars($pageH1); ?></h1>
        <div>
            <?php if ($showBackLink): ?>
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="logout-btn"><?php echo htmlspecialchars($backLabel); ?></a>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <nav class="admin-nav">
        <?php foreach ($navItems as $key => $item): ?>
        <a href="<?php echo $item['href']; ?>" class="nav-btn<?php echo $activeNav === $key ? ' active' : ''; ?>"><?php echo $item['label']; ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-message">✅ <?php echo htmlspecialchars($successMessage ?? 'Guardado correctamente'); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="success-message">✅ <?php echo htmlspecialchars($deletedMessage ?? 'Eliminado correctamente'); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="error-message">❌ <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
