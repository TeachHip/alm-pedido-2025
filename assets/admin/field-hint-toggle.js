// Toggle a hint box open/closed when its "?" button is clicked. Pairs with
// .field-hint-toggle / .field-hint in assets/admin/forms.css.
// Usage: <button type="button" class="field-hint-toggle" aria-expanded="false" aria-controls="hint-x">?</button>
//        <div class="field-hint" id="hint-x">...</div>
// Event-delegated on document, so any number of these work with no
// per-page wiring.
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.field-hint-toggle');
    if (!btn) return;

    const hint = document.getElementById(btn.getAttribute('aria-controls'));
    if (!hint) return;

    const isOpen = hint.classList.toggle('field-hint-open');
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
});
