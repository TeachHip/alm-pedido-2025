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
        <button type="button" id="member-menu-toggle" class="member-menu-toggle" aria-label="Menú" aria-expanded="false" aria-controls="member-menu-overlay">
            <span></span><span></span><span></span>
        </button>
        <div id="member-menu-overlay" class="member-menu-overlay" hidden>
            <button type="button" id="member-menu-close" class="member-menu-close" aria-label="Cerrar menú">✕</button>
            <?php if ($loggedInMember): ?>
            <div class="member-menu-greeting">Hola, <?php echo htmlspecialchars($loggedInMember['alias']); ?></div>
            <nav class="member-menu-nav">
                <a href="member-logout.php" class="member-menu-item">Cerrar sesión</a>
            </nav>
            <?php else: ?>
            <nav class="member-menu-nav">
                <a href="member-login.php?return_to=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/'); ?>" class="member-menu-item">Iniciar sesión</a>
            </nav>
            <?php endif; ?>
        </div>
        <script src="assets/member-menu.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    </header>
