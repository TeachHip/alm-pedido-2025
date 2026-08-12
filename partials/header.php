    <?php
    require_once __DIR__ . '/../includes/version.php';
    require_once __DIR__ . '/../includes/member-auth.php';
    $loggedInMember = isMemberLoggedIn() ? getLoggedInMember() : null;
    ?>
    <header>
	<a href="./" style="text-decoration: none; color: #fff; display:block">
        <div class="container">
            <h1 style="text-decoration: none; color: #fff;">AlMercáu<small style="font-size: 0.35em; font-weight: normal;"><?php echo htmlspecialchars(APP_VERSION); ?></small></h1>
            <p class="subtitle">Del productor al barrio. Laviada, Gijón</p>
        </div>
		</a>
        <button type="button" id="member-menu-toggle" class="member-menu-toggle" aria-label="Menú" aria-expanded="false" aria-controls="member-menu-panel">
            <span></span><span></span><span></span>
        </button>
        <div id="member-menu-backdrop" class="member-menu-backdrop"></div>
        <div id="member-menu-panel" class="member-menu-panel">
            <div class="member-menu-panel-header">
                <span class="member-menu-greeting"><?php echo $loggedInMember ? 'Hola, ' . htmlspecialchars($loggedInMember['alias']) : 'Menú'; ?></span>
                <button type="button" id="member-menu-close" class="member-menu-close" aria-label="Cerrar menú">✕</button>
            </div>
            <nav class="member-menu-nav">
                <?php if ($loggedInMember): ?>
                <a href="member-logout.php" class="member-menu-item">Cerrar sesión</a>
                <?php else: ?>
                <a href="member-login.php?return_to=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/'); ?>" class="member-menu-item">Iniciar sesión</a>
                <?php endif; ?>
            </nav>
        </div>
        <script src="assets/member-menu.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    </header>
