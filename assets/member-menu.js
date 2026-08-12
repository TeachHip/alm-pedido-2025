// Dropdown member menu anchored under the header (see partials/header.php).
// Always present; content (login vs. logout) depends on session state.
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('member-menu-toggle');
    const closeBtn = document.getElementById('member-menu-close');
    const backdrop = document.getElementById('member-menu-backdrop');
    const panel = document.getElementById('member-menu-panel');
    if (!toggleBtn || !panel) return;

    function openMenu() {
        if (backdrop) backdrop.classList.add('is-open');
        panel.classList.add('is-open');
        toggleBtn.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
        if (backdrop) backdrop.classList.remove('is-open');
        panel.classList.remove('is-open');
        toggleBtn.setAttribute('aria-expanded', 'false');
    }

    toggleBtn.addEventListener('click', function() {
        if (panel.classList.contains('is-open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeMenu);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeMenu);
    }
});
