<?php
// admin/login.php - Database authentication
include dirname(__FILE__) . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = loginAdmin($username, $password);
    if ($result === true) {
        header('Location: index.php');
        exit;
    } elseif ($result === 'locked') {
        $error = 'Demasiados intentos fallidos. Cuenta bloqueada temporalmente, inténtalo de nuevo en unos minutos.';
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Admin Login - AlMercáu';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/forms.css?v=<?php echo APP_VERSION_SAFE; ?>">
</head>
<body>
    <div class="edit-form" style="margin-top: 100px;">
        <h2>Admin AlMercáu</h2>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <input type="text" name="username" placeholder="Usuario" required autocomplete="username">
            <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
            <button type="submit" class="btn-save">Entrar</button>
        </form>
    </div>
</body>
</html>