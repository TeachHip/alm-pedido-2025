<?php
// admin/edit-member.php - Create/Edit member form
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/MemberRepository-DB.php';

try {
    $memberRepo = new MemberRepository();
    $member = null;
    $isEdit = false;

    if (isset($_GET['member_id'])) {
        $memberId = (int) $_GET['member_id'];
        $member = $memberRepo->findById($memberId);

        if (!$member) {
            die("Miembro no encontrado");
        }

        $isEdit = true;
    }
} catch (Exception $e) {
    error_log("Error loading member: " . $e->getMessage());
    die("Error: No se pudo cargar el miembro.");
}

$pageH1 = ($isEdit ? 'Editar' : 'Nuevo') . ' Miembro';
$pageTitle = $pageH1 . ' - AlMercáu';
$activeNav = 'members';
$backUrl = 'members.php';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/forms.css?v=<?php echo APP_VERSION_SAFE; ?>">
    <script src="../assets/admin/form-validate.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <div class="edit-form">
        <div id="form-error-summary" class="error-message" style="display:none;"></div>
        <form method="POST" action="actions/save-member.php" novalidate>
            <?php if ($isEdit): ?>
            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Teléfono:</label>
                <input type="tel" name="phone" value="<?php echo $member ? htmlspecialchars($member['phone']) : ''; ?>" required>
                <span class="field-error" data-error-for="phone"></span>
            </div>

            <div class="form-group">
                <label>Alias (nombre a mostrar):</label>
                <input type="text" name="alias" value="<?php echo $member ? htmlspecialchars($member['alias']) : ''; ?>" required>
                <span class="field-error" data-error-for="alias"></span>
            </div>

            <div class="form-group">
                <label>Alias interno (solo para uso de AlMercáu, no se muestra a la persona socia):</label>
                <input type="text" name="internal_alias" value="<?php echo $member ? htmlspecialchars($member['internal_alias'] ?? '') : ''; ?>">
            </div>

            <div class="form-group">
                <label>Notas internas:</label>
                <textarea name="notes"><?php echo $member ? htmlspecialchars($member['notes'] ?? '') : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label>Email (opcional):</label>
                <input type="email" name="email" value="<?php echo $member ? htmlspecialchars($member['email'] ?? '') : ''; ?>">
            </div>

            <div class="form-group">
                <label>Tipo de socia:</label>
                <select name="membership_type" required>
                    <option value="paying" <?php echo ($member && $member['membership_type'] === 'paying') ? 'selected' : ''; ?>>Mercante colaborador</option>
                    <option value="non_paying" <?php echo (!$member || $member['membership_type'] === 'non_paying') ? 'selected' : ''; ?>>Mercante</option>
                </select>
                <span class="field-error" data-error-for="membership_type"></span>
            </div>

            <div class="form-group">
                <label><?php echo $isEdit ? 'Nueva contraseña (dejar en blanco para no cambiar):' : 'Contraseña inicial:'; ?></label>
                <input type="password" name="password" <?php echo $isEdit ? '' : 'required'; ?> autocomplete="new-password">
                <span class="field-error" data-error-for="password"></span>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="active" value="1" <?php echo (!$member || $member['active']) ? 'checked' : ''; ?>>
                    Miembro activo
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><?php echo $isEdit ? 'Guardar Cambios' : 'Crear Miembro'; ?></button>
                <a href="members.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
    adminValidateForm(document.querySelector('.edit-form form'), [
        { name: 'phone', message: 'Falta el teléfono.' },
        { name: 'alias', message: 'Falta el alias.' },
        <?php if (!$isEdit): ?>
        { name: 'password', message: 'Falta la contraseña inicial.' },
        <?php endif; ?>
    ]);
    </script>
</body>
</html>
