<?php
// admin/producers.php - Producer management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/ProducerRepository-DB.php';

try {
    $producerRepo = new ProducerRepository();
    $producers = $producerRepo->getAll();
} catch (Exception $e) {
    error_log("Error loading producers: " . $e->getMessage());
    die("Error: No se pudieron cargar los productores.");
}
$pageTitle = 'Gestionar Productores - AlMercáu';
$pageH1 = 'Gestionar Productores';
$activeNav = 'producers';
$successMessage = 'Productor guardado correctamente';
include dirname(__FILE__) . '/partials/head.php';
?>
    <script src="../assets/admin/toggle-indicator.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <a href="edit-producer.php" class="add-product">+ Añadir Productor</a>

    <p class="admin-tip">
        💡 <strong>Tip:</strong> también se puede crear un productor nuevo escribiendo su nombre directamente en el campo "Productor" al editar un producto.
    </p>

    <div class="products-table">
        <?php if (empty($producers)): ?>
        <div class="empty-state">
            <p>No hay productores registrados todavía.</p>
        </div>
        <?php else: ?>
            <div class="table-scroll">
            <table width="100%">
                <thead>
                    <tr>
                        <th width="10%">ID</th>
                        <th width="45%">Nombre</th>
                        <th width="20%">Activo</th>
                        <th width="25%">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($producers as $producer): ?>
                    <?php $isPlaceholder = $producer['name'] === ProducerRepository::PLACEHOLDER_NAME; ?>
                    <tr>
                        <td><?php echo $producer['id']; ?></td>
                        <td><?php echo htmlspecialchars($producer['name']); ?></td>
                        <td class="visibility-cell">
                            <?php if ($isPlaceholder): ?>
                            <small>—</small>
                            <?php else: ?>
                            <a href="#" onclick="return adminToggle('actions/toggle-producer-active.php?producer_id=<?php echo $producer['id']; ?>', this, {valueKey: 'active', trueLabel: 'Activo', falseLabel: 'Inactivo', errorMessage: 'Error al cambiar el estado'});">
                            <?php if ($producer['active']): ?>
                            <span class="visible-indicator">✓</span>
                            <br><small>Activo</small>
                            <?php else: ?>
                            <span class="hidden-indicator">✗</span>
                            <br><small>Inactivo</small>
                            <?php endif; ?>
                            </a>
                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            <?php if ($isPlaceholder): ?>
                            <small>Automático, no editable</small>
                            <?php else: ?>
                            <a href="edit-producer.php?producer_id=<?php echo $producer['id']; ?>" class="btn-edit">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
