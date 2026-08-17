// assets/legal-modal.js - Open/close logic for the "Aviso legal y política
// de privacidad" modal (partials/legal-modal.php, included from
// partials/footer.php on every front-end page). Closes on the X button,
// the "Cerrar" button, or a click on the backdrop outside the dialog.
(function () {
    const trigger = document.getElementById('legal-modal-trigger');
    const backdrop = document.getElementById('legal-modal-backdrop');
    const modal = document.getElementById('legal-modal');
    const closeX = document.getElementById('legal-modal-close-x');
    const closeBtn = document.getElementById('legal-modal-close-btn');

    if (!trigger || !backdrop || !modal) return;

    function openModal(e) {
        if (e) e.preventDefault();
        backdrop.classList.add('is-open');
        modal.classList.add('is-open');
    }

    function closeModal() {
        backdrop.classList.remove('is-open');
        modal.classList.remove('is-open');
    }

    trigger.addEventListener('click', openModal);
    if (closeX) closeX.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
})();
