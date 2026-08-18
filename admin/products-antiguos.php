<?php
// admin/products-antiguos.php - Read-mostly reference list of deprecated
// ("antiguo") products: active=0, kept only so historical tickets/orders
// referencing them still resolve. Never shown in the storefront or in
// admin/products.php's normal list -- this page is the only place they
// surface. See ProductRepository::getDeprecated()/getAll().
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/ProductRepository-DB.php';

try {
    $productRepo = new ProductRepository();
    $products = $productRepo->getDeprecated();
} catch (Exception $e) {
    error_log("Error loading deprecated products: " . $e->getMessage());
    die("Error: No se pudieron cargar los productos antiguos.");
}

$pageTitle = 'Productos Antiguos - AlMercáu';
$pageH1 = 'Productos Antiguos';
$activeNav = 'antiguos';
$successMessage = 'Producto restaurado correctamente';
include dirname(__FILE__) . '/partials/head.php';
?>
    <script>
    // Restore removes the product from this list entirely (it's no longer
    // active=0), so this just reloads on success rather than swapping an
    // indicator in place like admin/products.php's visibility toggle does.
    function restoreProduct(url) {
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.success) throw new Error();
                location.reload();
            })
            .catch(function() {
                alert('Error al restaurar el producto');
            });
        return false;
    }
    </script>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <p class="admin-tip">
        💡 Productos marcados como antiguos: no aparecen en la tienda ni en la lista normal de productos,
        se conservan solo para que los tickets de compra antiguos que los mencionan sigan mostrándose correctamente.
        Pulsa "Restaurar" para devolver un producto a la lista normal.
    </p>

    <div class="products-table">
        <?php if (empty($products)): ?>
        <div class="empty-state">
            <p>No hay productos antiguos.</p>
        </div>
        <?php else: ?>
        <table width="100%">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="25%">Nombre</th>
                    <th width="20%">Sección</th>
                    <th width="12%">Precio Socio</th>
                    <th width="12%">Precio Público</th>
                    <th width="10%">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr data-product-id="<?php echo $product['id']; ?>">
                    <td>
                        <?php echo $product['id']; ?>
                        <br>
                        <img src="../primgs/<?php echo htmlspecialchars($product['image']); ?>" width="40" style="width:40px; margin-top: 5px;" alt="">
                    </td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo htmlspecialchars($product['section_name']); ?></td>
                    <td>€<?php echo number_format($product['price_member'], 2); ?></td>
                    <td>€<?php echo number_format($product['price_public'], 2); ?></td>
                    <td class="action-buttons">
                        <a href="#" class="btn-edit" onclick="return restoreProduct('actions/toggle-active.php?product_id=<?php echo $product['id']; ?>');">
                            Restaurar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>
