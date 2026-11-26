<?php

/**
 * Database Configuration
 * TerraWatch Environmental Reporting Platform
 */

define('DB_HOST', 'sql109.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_43534478_terrawatch_db');
define('DB_USER', 'if0_43534478');
define('DB_PASS', 'Zuvika2518');
define('DB_CHARSET', 'utf8mb4');

/**
 * Create and return a PDO database connection
 *
 * @return PDO
 */
function getConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("<div style='font-family:sans-serif;padding:30px;max-width:600px;margin:50px auto;border:1px solid #D6CBB5;background:#F6F6F0;color:#34483B;border-radius:12px;'>
                <h3 style='color:#C17B58;'>Gagal Terhubung ke Database MySQL</h3>
                <p><strong>Pesan Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <hr style='border-color:#D6CBB5;'>
                <p>Pastikan:</p>
                <ol>
                    <li>Modul <strong>MySQL</strong> di XAMPP Control Panel sudah dalam status <strong>Start (Running)</strong>.</li>
                    <li>Database <code>terrawatch_db</code> sudah dibuat di phpMyAdmin.</li>
                </ol>
            </div>");
        }
    }

    return $pdo;
}

// Global PDO instance
$pdo = getConnection();
