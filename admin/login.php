<?php
// admin/login.php - Database authentication
include dirname(__FILE__) . '/../includes/auth.php';

// Reset switch: uploading an empty file named 'fluffy.flag' to the app root
// via FTP clears every admin/worker failed-attempt counter, then deletes
// itself -- deliberately obscure name (not "unlock.flag"), since its mere
// presence is the trigger. No longer a rescue for a "locked out" admin
// (authenticate() below uses an escalating delay instead of a hard lock,
// precisely because that has no recovery path when the account being
// locked is the only admin there is) -- kept as a way to clear the delay
// clock back to zero if ever wanted.
$unlockFlagPath = dirname(__FILE__) . '/../fluffy.flag';
if (file_exists($unlockFlagPath)) {
    require_once dirname(__FILE__) . '/../includes/repositories/UserRepository-DB.php';
    (new UserRepository())->unlockAll();
    @unlink($unlockFlagPath);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = loginAdmin($username, $password);
    if ($result === true) {
        header('Location: index.php');
        exit;
    } else {
        // A wrong password may have just taken several seconds to answer
        // (see UserRepository::registerFailedLoginWithDelay()) -- never a
        // hard lockout for admin, so there's no separate 'locked' case here.
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