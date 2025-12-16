<?php
// admin/logout.php - Stable path inclusion
include dirname(__FILE__) . '/../includes/auth.php';
logoutAdmin();
header('Location: login.php');
exit;
?>