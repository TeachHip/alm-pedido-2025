<?php
// assets/head.php - Reusable head section
$pageTitle = $pageTitle ?? 'AlMercáu - Carro de la compra para mercantes';
$pageDescription = $pageDescription ?? 'Catálogo online de alimentos para la comunidad local';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="favicon.ico?v=1" type="image/x-icon">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">

    <meta property="og:title" content="AlMercáu - Carro de la compra para mercantes">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://almercau.org/pedido/">
    <meta property="og:image" content="https://almercau.org/pedido/imgs/og.png">

    <link rel="stylesheet" href="assets/style.css?v=<?php echo filemtime('assets/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Preload critical resources -->
    <link rel="preload" href="assets/script.js" as="script">
    <script src="assets/script.js" defer onerror="console.error('Script failed to load')"></script>
</head>

<body>