<?php
// admin/edit-producer.php - Create/Edit producer form
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/ProducerRepository-DB.php';

try {
    $producerRepo = new ProducerRepository();
    $producer = null;
    $isEdit = false;

    if (isset($_GET['producer_id'])) {
        $producerId = (int) $_GET['producer_id'];
        $producer = $producerRepo->getById($producerId);

        if (!$producer) {
            die("Productor no encontrado");
        }

        // The placeholder every unassigned product points at -- renaming or
        // deactivating it here would break the 'Sin asignar' fallback logic
        // in ProducerRepository/edit-product.php, so it's not editable.
        if ($producer['name'] === ProducerRepository::PLACEHOLDER_NAME) {
            die("Este productor es automático (marcador para productos sin asignar) y no se puede editar.");
        }

        $isEdit = true;
    }
} catch (Exception $e) {
    error_log("Error loading producer: " . $e->getMessage());
    die("Error: No se pudo cargar el productor.");
}

$pageH1 = ($isEdit ? 'Editar' : 'Nuevo') . ' Productor';
$pageTitle = $pageH1 . ' - AlMercáu';
$activeNav = 'producers';
$backUrl = 'producers.php';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/forms.css?v=<?php echo APP_VERSION_SAFE; ?>">
    <script src="../assets/admin/form-validate.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <div class="edit-form">
        <div id="form-error-summary" class="error-message" style="display:none;"></div>
        <form method="POST" action="actions/save-producer.php" novalidate>
            <?php if ($isEdit): ?>
            <input type="hidden" name="producer_id" value="<?php echo $producer['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Nombre:</label>
                <input type="text" name="name" value="<?php echo $producer ? htmlspecialchars($producer['name']) : ''; ?>" required>
                <span class="field-error" data-error-for="name"></span>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="active" value="1" <?php echo (!$producer || $producer['active']) ? 'checked' : ''; ?>>
                    Productor activo
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><?php echo $isEdit ? 'Guardar Cambios' : 'Crear Productor'; ?></button>
                <a href="producers.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
    adminValidateForm(document.querySelector('.edit-form form'), [
        { name: 'name', message: 'Falta el nombre del productor.' }
    ]);
    </script>
</body>
</html>
