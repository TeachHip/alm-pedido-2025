<?php
// member-login.php - Member (customer) login
require_once 'includes/maintenance.php';
enforceMaintenanceMode();

require_once 'includes/member-auth.php';

$error = '';
$returnTo = $_GET['return_to'] ?? $_POST['return_to'] ?? '';
// Only allow absolute-but-same-site paths -- never redirect off-site.
// Must KEEP the leading slash: when the app lives under a subdirectory
// (e.g. /pedido/), stripping it turns an absolute path into a relative
// one, which the browser then resolves against member-login.php's own
// directory instead of the domain root -- producing a doubled
// /pedido/pedido/... URL (found via real testing on the live server,
// 2026-08-25).
if ($returnTo === '' || $returnTo[0] !== '/' || strpos($returnTo, '//') === 0) {
    $returnTo = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = loginMember($phone, $password);
    if ($result === true) {
        header('Location: ' . $returnTo);
        exit;
    } elseif ($result === 'locked') {
        $error = 'Demasiados intentos fallidos. Cuenta bloqueada temporalmente, inténtalo de nuevo en unos minutos.';
    } else {
        $error = 'Teléfono o contraseña incorrectos';
    }
}

if (isMemberLoggedIn()) {
    header('Location: ' . $returnTo);
    exit;
}

$pageTitle = 'Iniciar sesión - AlMercáu';
include 'partials/head.php';
include 'partials/header.php';
?>

<div class="container">
    <div class="login-form">
        <a href="<?php echo htmlspecialchars($returnTo); ?>" class="login-close" aria-label="Cerrar">✕</a>
        <h2>Iniciar sesión</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="member-login.php">
            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo); ?>">
            <input type="tel" name="phone" placeholder="Teléfono" required autocomplete="tel">
            <div class="password-field">
                <input type="password" id="login-password" name="password" placeholder="Contraseña" required autocomplete="current-password">
                <button type="button" class="password-toggle" aria-label="Mostrar contraseña" onclick="togglePasswordVisibility('login-password', this)"><i class="fas fa-eye"></i></button>
            </div>
            <button type="submit" class="btn">Entrar</button>
        </form>
        <p class="login-hint">¿No tienes cuenta? Pásate por AlMercáu y te damos de alta en persona.</p>
    </div>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>
