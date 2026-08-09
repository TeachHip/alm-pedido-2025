<?php
// admin/partials/head.php - Shared <head> opening for admin pages.
// Caller sets $pageTitle before including. Left open (no </head>) so the
// caller can inject page-specific <style>/<script> tags before including
// header.php, which closes it and opens <body>.
$pageTitle = $pageTitle ?? 'Admin - AlMercáu';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="../assets/admin/styles.css">
