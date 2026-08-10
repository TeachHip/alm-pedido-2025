<?php
/**
 * App version — single source of truth. Shown in the site header
 * (partials/header.php) and used to cache-bust every static JS/CSS file
 * across the app (public + admin), so browsers fetch fresh assets instead
 * of serving a stale cached copy after a deploy.
 *
 * Bump APP_VERSION on every release that touches JS/CSS/behavior.
 */
define('APP_VERSION', 'v4 ☀️branu');

// URL-safe form for ?v=... query strings (APP_VERSION may contain spaces/emoji).
define('APP_VERSION_SAFE', rawurlencode(APP_VERSION));
