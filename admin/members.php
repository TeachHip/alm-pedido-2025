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
    <script src="../assets/admin/toggle-indicator.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <script src="../assets/admin/filter-toggle.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <style>
        /* Members don't reorder (unlike products/sections), so no drag
           cursor -- just a pointer on the two sortable headers. */
        th.member-sortable { cursor: pointer; user-select: none; }
        /* Brute-force lockout indicator (see LoginLockoutTrait) -- a real
           customer can be locked out of their own account right now with
           no other way for Hop to notice. */
        .locked-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #dc3545;
            margin-right: 5px;
            vertical-align: middle;
        }
    </style>
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

        // Click-to-sort on the ID (numeric) and Alias (text) columns.
        var table = document.querySelector('.products-table table');
        if (!table) return;
        var tbody = table.tBodies[0];
        var cols = { 0: 'num', 2: 'text' };
        var current = { col: null, dir: 1 };

        function valueOf(tr, col) {
            return col === 0
                ? parseInt(tr.dataset.sortId || '0', 10)
                : tr.cells[2].textContent.trim();
        }

        Object.keys(cols).forEach(function(key) {
            var col = parseInt(key, 10);
            var th = table.tHead.rows[0].cells[col];
            th.classList.add('member-sortable');
            var arrow = document.createElement('span');
            arrow.className = 'sort-arrow';
            th.appendChild(arrow);
            th.addEventListener('click', function() {
                current.dir = current.col === col ? -current.dir : 1;
                current.col = col;
                Array.prototype.slice.call(tbody.rows)
                    .sort(function(a, b) {
                        var va = valueOf(a, col), vb = valueOf(b, col);
                        var cmp = cols[col] === 'num'
                            ? va - vb
                            : String(va).localeCompare(String(vb), 'es');
                        return cmp * current.dir;
                    })
                    .forEach(function(r) { tbody.appendChild(r); });
                Object.keys(cols).forEach(function(k) {
                    table.tHead.rows[0].cells[parseInt(k, 10)].querySelector('.sort-arrow').textContent = '';
                });
                arrow.textContent = current.dir === 1 ? ' ▲' : ' ▼';
            });
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
            <div class="table-scroll">
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
                    <?php foreach ($members as $member):
                        $isLocked = $member['locked_until'] && strtotime($member['locked_until']) > time();
                    ?>
                    <tr data-member-id="<?php echo $member['id']; ?>" data-active="<?php echo $member['active'] ? '1' : '0'; ?>" data-sort-id="<?php echo (int) $member['member_number']; ?>">
                        <td><?php echo $member['member_number'] ? MemberRepository::formatMemberNumber($member['member_number']) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($memberRepo->formatPhoneForDisplay($member['phone'])); ?></td>
                        <td><?php if ($isLocked): ?><span class="locked-dot" title="Bloqueado por intentos fallidos"></span><?php endif; ?><?php echo htmlspecialchars($member['alias']); ?></td>
                        <td><?php echo $member['membership_type'] === 'paying' ? 'Mercante colaborador' : 'Mercante'; ?></td>
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
                            <?php if ($isLocked): ?>
                            <a href="actions/unlock-member.php?member_id=<?php echo $member['id']; ?>" class="btn-edit" onclick="return confirm('¿Desbloquear a este miembro? Podrá volver a intentar iniciar sesión inmediatamente.');">🔓 Desbloquear</a>
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
