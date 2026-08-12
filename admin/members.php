<?php
// admin/members.php - Member management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/MemberRepository-DB.php';

try {
    $memberRepo = new MemberRepository();
    $members = $memberRepo->getAll();
} catch (Exception $e) {
    error_log("Error loading members: " . $e->getMessage());
    die("Error: No se pudieron cargar los miembros.");
}
$pageTitle = 'Gestionar Miembros - AlMercáu';
$pageH1 = 'Gestionar Miembros';
$activeNav = 'members';
$successMessage = 'Miembro guardado correctamente';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/sortable-table.css?v=<?php echo APP_VERSION_SAFE; ?>">
    <script src="../assets/admin/toggle-indicator.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <script src="../assets/admin/filter-toggle.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initFilterToggle({
            buttonId: 'toggle-active-btn',
            cookieName: 'admin_show_only_active_members',
            rowSelector: 'tr[data-member-id]',
            dataAttr: 'data-active',
            filterLabel: 'Mostrar solo activos',
            showAllLabel: 'Mostrar todos'
        });
    });
    </script>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <a href="edit-member.php" class="add-product">+ Añadir Miembro</a>

    <button id="toggle-active-btn" type="button" style="margin-left: 15px; padding: 7px 16px; font-size: 15px; border-radius: 5px; border: 1px solid #bbb; background: #f8f8f8; cursor: pointer;">
        Mostrar solo activos
    </button>

    <div class="products-table">
        <?php if (empty($members)): ?>
        <div class="empty-state">
            <p>No hay miembros registrados todavía.</p>
        </div>
        <?php else: ?>
            <p class="admin-tip">
                💡 <strong>Tip:</strong> Las altas y contraseñas se gestionan en persona, en la tienda.
            </p>
            <table width="100%">
                <thead>
                    <tr>
                        <th width="8%">ID</th>
                        <th width="15%">Teléfono</th>
                        <th width="25%">Alias</th>
                        <th width="15%">Tipo</th>
                        <th width="12%">Activo</th>
                        <th width="25%">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                    <tr data-member-id="<?php echo $member['id']; ?>" data-active="<?php echo $member['active'] ? '1' : '0'; ?>">
                        <td><?php echo $member['member_number'] ? MemberRepository::formatMemberNumber($member['member_number']) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($memberRepo->formatPhoneForDisplay($member['phone'])); ?></td>
                        <td><?php echo htmlspecialchars($member['alias']); ?></td>
                        <td><?php echo $member['membership_type'] === 'paying' ? 'Socia de pago' : 'No socia'; ?></td>
                        <td class="visibility-cell">
                            <a href="#" onclick="return adminToggle('actions/toggle-member-active.php?member_id=<?php echo $member['id']; ?>', this, {valueKey: 'active', trueLabel: 'Activo', falseLabel: 'Inactivo', errorMessage: 'Error al cambiar el estado', dataAttr: 'data-active'});">
                            <?php if ($member['active']): ?>
                            <span class="visible-indicator">✓</span>
                            <br><small>Activo</small>
                            <?php else: ?>
                            <span class="hidden-indicator">✗</span>
                            <br><small>Inactivo</small>
                            <?php endif; ?>
                            </a>
                        </td>
                        <td class="action-buttons">
                            <a href="edit-member.php?member_id=<?php echo $member['id']; ?>" class="btn-edit">Editar</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
