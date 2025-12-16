<?php
// admin/index.php - Stable path inclusion
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - AlMercáu</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="admin-header">
        <h1>Panel de Administración</h1>
        <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
    </div>

    <nav class="admin-nav">
        <a href="products.php" class="nav-btn">Gestionar Productos</a>
        <a href="sections.php" class="nav-btn">Gestionar Secciones</a>
    </nav>

<div class="admin-stats">
    <?php
    include dirname(__FILE__) . '/../includes/data-loader.php';
    $appData = loadAppData();
    $totalProducts = 0;
    $visibleProducts = 0;

    if ($appData !== false) {
        foreach ($appData['products'] as $sectionProducts) {
            $totalProducts += count($sectionProducts);
            foreach ($sectionProducts as $product) {
                if ($product['visible'] ?? true) {
                    $visibleProducts++;
                }
            }
        }
    }
    ?>
    <div class="stat-card">
        <h3>Productos Totales</h3>
        <p><?php echo $totalProducts; ?></p>
    </div>
    <div class="stat-card">
        <h3>Productos Visibles</h3>
        <p><?php echo $visibleProducts; ?></p>
    </div>
    <div class="stat-card">
        <h3>Secciones</h3>
        <p><?php echo count($appData['sections'] ?? []); ?></p>
    </div>
</div>
</body>
</html>