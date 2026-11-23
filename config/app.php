<?php
date_default_timezone_set('Asia/Jakarta');

/**
 * Application Configuration
 * TerraWatch Environmental Reporting Platform
 * Mendukung SDG 15: Life on Land
 */

// Application Info
define('APP_NAME', 'TerraWatch');
define('APP_VERSION', '1.0.0');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// APP_URL dihitung otomatis dari lokasi folder proyek, jadi tidak
// bergantung pada nama folder atau port server.
$docRoot  = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
$appRoot  = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$basePath = ($docRoot !== '' && stripos($appRoot, $docRoot) === 0)
    ? substr($appRoot, strlen($docRoot))
    : '';
define('APP_URL', $protocol . $host . $basePath);

// File Upload Constraints
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024);         // 2 MB per file
define('MAX_FILES_COUNT', 5);                     // 1 sampai 5 foto per laporan
define('MAX_TOTAL_UPLOAD_SIZE', 8 * 1024 * 1024); // 8 MB total per pengiriman
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Upload Sub-directories
define('UPLOAD_REPORTS_DIR', UPLOAD_DIR . 'reports/');
define('UPLOAD_ARTICLES_DIR', UPLOAD_DIR . 'articles/');
define('UPLOAD_PROFILES_DIR', UPLOAD_DIR . 'profiles/');

// Official Color Palette (Minimal Natural SDG 15)
define('COLOR_PINE', '#34483B');
define('COLOR_SAGE', '#789078');
define('COLOR_SOFT_LEAF', '#B5C2AA');
define('COLOR_CLOUD_CREAM', '#F6F6F0');
define('COLOR_STONE_BEIGE', '#D6CBB5');
define('COLOR_TERRACOTTA', '#C17B58');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
