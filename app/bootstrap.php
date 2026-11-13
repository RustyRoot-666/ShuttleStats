<?php
/**
 * Bootstrap aplikasi: session, error handling, muat semua kelas & helper.
 */
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (app_config('timezone')) {
    date_default_timezone_set(app_config('timezone'));
}

if (app_config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// Header keamanan dasar
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/Analyzer.php';
require_once __DIR__ . '/PhpZip.php';
require_once __DIR__ . '/XlsxReader.php';
require_once __DIR__ . '/XlsxWriter.php';
require_once __DIR__ . '/Importer.php';
require_once __DIR__ . '/Exporter.php';

// Pastikan basis data siap (skema + seeder)
Database::pdo();
