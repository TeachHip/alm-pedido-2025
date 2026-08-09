<?php
// admin/index.php - Stable path inclusion
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

$pageTitle = 'Admin Panel - AlMercáu';
$pageH1 = 'Panel de Administración';
$showBackLink = false;
include dirname(__FILE__) . '/partials/head.php';
include dirname(__FILE__) . '/partials/header.php';
?>

<div class="admin-stats">
    <?php
    require_once dirname(__FILE__) . '/../includes/repositories/ProductRepository-DB.php';
    require_once dirname(__FILE__) . '/../includes/repositories/SectionRepository-DB.php';
    
    try {
        $productRepo = new ProductRepository();
        $sectionRepo = new SectionRepository();
        
        $allProducts = $productRepo->getAll();
        $totalProducts = count($allProducts);
        $visibleProducts = count(array_filter($allProducts, function($p) { return $p['visible']; }));
        $totalSections = count($sectionRepo->getAll());
    } catch (Exception $e) {
        $totalProducts = 0;
        $visibleProducts = 0;
        $totalSections = 0;
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
        <p><?php echo $totalSections; ?></p>
    </div>
</div>
</body>
</html>