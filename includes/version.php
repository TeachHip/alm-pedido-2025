<?php
/**
 * App version — single source of truth. Used to cache-bust every static
 * JS/CSS file across the app (public + admin) so browsers fetch fresh
 * assets instead of a stale cached copy after a deploy, and to show the
 * public-facing version in the site header (partials/header.php).
 *
 * Three parts (since 2026-08-26):
 * - APP_VERSION_FULL ("ver1"): major.minor.patch, e.g. "2.6.1" — the
 *   technical number. Major = product generation (2 = this automated
 *   order-to-invoice/payment app; 1 was the pre-login, pre-in-app-payment
 *   version). Minor = a wink at the current year (26 -> .6). Patch =
 *   internal build counter within this major.minor, starting at 1 — bump
 *   it on every release that touches JS/CSS/behavior, same as this
 *   constant always worked. Use this (via APP_VERSION_SAFE) anywhere
 *   cache-busting matters; never shown to the public.
 * - APP_VERSION_PUBLIC ("ver2"): major.minor only, derived from
 *   APP_VERSION_FULL by dropping the patch digit — e.g. "2.6". Shown to
 *   the public (site header) instead of the technical number.
 * - APP_VERSION_ALIAS: a themed nickname shown alongside APP_VERSION_PUBLIC
 *   in the public header.
 */
define('APP_VERSION_FULL', '2.6.2');

$appVersionFullParts = explode('.', APP_VERSION_FULL);
define('APP_VERSION_PUBLIC', $appVersionFullParts[0] . '.' . $appVersionFullParts[1]);

define('APP_VERSION_ALIAS', '☀️branu');

// URL-safe form of the technical version, for ?v=... cache-busting query strings.
define('APP_VERSION_SAFE', rawurlencode(APP_VERSION_FULL));