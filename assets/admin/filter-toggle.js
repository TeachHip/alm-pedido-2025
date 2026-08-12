// Generic "click to filter table rows by a boolean flag, persisted across
// navigation via a cookie" widget. Originally built once for products.php's
// "Mostrar solo visibles" and duplicated by hand for members.php's
// "Mostrar solo activos" -- extracted here so a third use doesn't repeat it
// again. Cookie is 1-year, path=/ (see the cache/cookie-path incident this
// pattern already caused once for products.php -- path must be / to survive
// any subfolder deployment).
function initFilterToggle(options) {
    const btn = document.getElementById(options.buttonId);
    if (!btn) return;

    function getCookie(name) {
        const match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return match ? match.pop() : null;
    }

    function setCookie(name, value) {
        const expires = new Date();
        expires.setFullYear(expires.getFullYear() + 1);
        document.cookie = name + '=' + value + '; expires=' + expires.toUTCString() + '; path=/; samesite=lax';
    }

    function applyFilter(onlyTrue) {
        document.querySelectorAll(options.rowSelector).forEach(function(row) {
            row.style.display = (onlyTrue && row.getAttribute(options.dataAttr) !== '1') ? 'none' : '';
        });
    }

    let onlyTrue = getCookie(options.cookieName) === '1';
    btn.textContent = onlyTrue ? options.showAllLabel : options.filterLabel;
    applyFilter(onlyTrue);

    btn.addEventListener('click', function() {
        onlyTrue = !onlyTrue;
        btn.textContent = onlyTrue ? options.showAllLabel : options.filterLabel;
        applyFilter(onlyTrue);
        setCookie(options.cookieName, onlyTrue ? '1' : '0');
    });

    // Re-apply immediately when an adminToggle() elsewhere on the page (see
    // toggle-indicator.js) changes the same data-* flag this filter reads --
    // otherwise a row toggled via AJAX keeps showing/hiding based on its
    // state from page load until the next full reload.
    document.addEventListener('admintoggle:changed', function(e) {
        if (e.detail && e.detail.dataAttr === options.dataAttr) {
            applyFilter(onlyTrue);
        }
    });
}
