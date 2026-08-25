<?php
/**
 * includes/maintenance.php - Site-wide "maintenance mode" gate for
 * customer-facing pages, bypassable by an admin session.
 *
 * Replaces the earlier .htaccess-based testing-only gate (2026-08-24) --
 * this one runs inside the app itself (via MAINTENANCE_MODE, hand-set in
 * includes/config/environment.php) instead of blocking all PHP execution
 * outright, so it doubles as a real, reusable maintenance-window tool
 * going forward, not just a pre-launch device -- no .htaccess edit needed
 * to flip it, just that one file.
 *
 * Call enforceMaintenanceMode() as the very first thing on every
 * customer-facing entry point (same pattern as requireAdminAuth(): called
 * explicitly per-page, not auto-included everywhere). Never call it from
 * admin/*.php (has its own auth gate and must always stay reachable, even
 * during maintenance -- otherwise nobody could ever turn it back off) or
 * from paygold-notify.php (a server-to-server webhook, not a browser visit,
 * must never be blocked).
 *
 * $responseType: 'html' (default) for full page loads, 'json' for AJAX
 * endpoints (save-cart.php) that expect a JSON body back either way.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/environment.php';

function enforceMaintenanceMode($responseType = 'html') {
    if (!MAINTENANCE_MODE) {
        return;
    }

    // Admin session bypasses -- checked after confirming maintenance mode
    // is even on.
    if (isAdminLoggedIn()) {
        return;
    }

    http_response_code(503);

    if ($responseType === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'La tienda está en mantenimiento. Vuelve en un rato.']);
        exit;
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AlMercáu</title>
<style>
    body { font-family: Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f0f0f0; text-align: center; padding: 20px; }
    .maintenance-box { background: white; padding: 30px; border-radius: 10px; max-width: 400px; }
    .maintenance-logo { width: 80px; height: 80px; border-radius: 16px; margin-bottom: 10px; }
    .maintenance-box h2 { margin-top: 0; }
</style>
</head>
<body>
    <div class="maintenance-box">
        <img src="imgs/og.png" alt="AlMercáu" class="maintenance-logo">
        <h2>Estamos actualizando la tienda</h2>
        <p>Vuelve en un rato. Si te urge, escríbenos o pásate por AlMercáu.</p>
    </div>
</body>
</html>
    <?php
    exit;
}
