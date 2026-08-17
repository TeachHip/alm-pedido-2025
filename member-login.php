<?php
// member-login.php - Member (customer) login
require_once 'includes/member-auth.php';

$error = '';
$returnTo = $_GET['return_to'] ?? $_POST['return_to'] ?? '';
// Only allow relative paths within the app -- never redirect off-site
if ($returnTo === '' || $returnTo[0] !== '/' || strpos($returnTo, '//') === 0) {
    $returnTo = 'index.php';
} else {
    $returnTo = ltrim($returnTo, '/');
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
        <h2>Iniciar sesión</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="member-login.php">
            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo); ?>">
            <input type="tel" name="phone" placeholder="Teléfono" required autocomplete="tel">
            <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
            <button type="submit" class="btn">Entrar</button>
        </form>
        <p class="login-hint">¿No tienes cuenta? Pásate por AlMercáu y te damos de alta en persona.</p>
    </div>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>
