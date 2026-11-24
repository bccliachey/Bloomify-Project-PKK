<?php
/**
 * Bloomify - global configuration.
 *
 * This file is included by every page (directly or through includes).
 * It starts the session, sets the timezone, resolves the application
 * base URL automatically, and stores database credentials.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

define('APP_ROOT', dirname(__DIR__));

/*
 * Resolve BASE_URL from the document root so the project can live in
 * http://localhost/Bloomify/ or directly in http://localhost/.
 */
$documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/'))
    : '';
$applicationRoot = str_replace('\\', '/', APP_ROOT);

$baseUrl = '';
if ($documentRoot !== '' && strpos($applicationRoot, $documentRoot) === 0) {
    $baseUrl = substr($applicationRoot, strlen($documentRoot));
}
define('BASE_URL', rtrim($baseUrl, '/'));

/*
 * Private storage must live outside the public document root so payment proof
 * images cannot be downloaded by guessing their URL.
 */
$storageRoot = '';
if ($documentRoot !== '' && $documentRoot !== $applicationRoot) {
    $candidate = dirname($documentRoot);
    $candidate = str_replace('\\', '/', rtrim($candidate, '/'));
    if ($candidate !== '' && $candidate !== $documentRoot && strpos($candidate, $documentRoot . '/') !== 0) {
        $storageRoot = $candidate . '/bloomify-storage';
    }
}
if ($storageRoot === '') {
    $storageRoot = str_replace('\\', '/', dirname($applicationRoot)) . '/bloomify-storage';
}
define('STORAGE_ROOT', $storageRoot);

/* Database credentials - adjust if your Laragon MySQL/MariaDB uses a password. */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'bloomify');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* Business constants. */
define('MIN_STEMS', 3);
define('DELIVERY_FEE', 20000);
define('WRAPPING_STEP', 10000);
define('WRAPPING_STEMS_PER_STEP', 5);
define('WRAPPING_FREE_STEMS', 5);
define('LOW_STOCK_FALLBACK', 10);

/* Show errors while developing locally. Set to false in production. */
ini_set('display_errors', '1');
error_reporting(E_ALL);
