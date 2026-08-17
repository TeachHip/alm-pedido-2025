    <footer>
        <div class="container">
            <p>AlMercáu - Desarrollo 4tres.com <?php echo date('Y'); ?></p>
            <p><a href="#" id="legal-modal-trigger">Aviso legal y política de privacidad</a></p>
        </div>
    </footer>
    <?php
    require_once __DIR__ . '/../includes/version.php';
    include __DIR__ . '/legal-modal.php';
    ?>
    <script src="assets/legal-modal.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>