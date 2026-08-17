<?php
/**
 * App version — single source of truth. Shown in the site header
 * (partials/header.php) and used to cache-bust every static JS/CSS file
 * across the app (public + admin), so browsers fetch fresh assets instead
 * of serving a stale cached copy after a deploy.
 *
 * Major.minor scheme (since 2026-08-12): the major number tracks the
 * project's own initiative naming (v10 = the automated order-to-invoice/
 * payment upgrade, bank + SMS APIs) -- bump it to v11 once that whole
 * scope is fulfilled, not before. The minor number bumps on every release
 * that touches JS/CSS/behavior within the current major version, same as
 * this constant always worked -- this is what prevents returning visitors
 * from running stale cached scripts after a deploy (caused a real bug
 * once already).
 */
define('APP_VERSION', 'v10.2 ☀️branu');

// URL-safe form for ?v=... query strings (APP_VERSION may contain spaces/emoji).
define('APP_VERSION_SAFE', rawurlencode(APP_VERSION));
