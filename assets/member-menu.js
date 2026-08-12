// Full-screen mobile-style menu triggered by the hamburger button in the
// public header (see partials/header.php) -- only present in the DOM when
// a member is logged in.
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('member-menu-toggle');
    const closeBtn = document.getElementById('member-menu-close');
    const overlay = document.getElementById('member-menu-overlay');
    if (!toggleBtn || !overlay) return;

    function openMenu() {
        overlay.hidden = false;
        toggleBtn.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
        overlay.hidden = true;
        toggleBtn.setAttribute('aria-expanded', 'false');
    }

    toggleBtn.addEventListener('click', function() {
        if (overlay.hidden) {
            openMenu();
        } else {
            closeMenu();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeMenu);
    }
});
