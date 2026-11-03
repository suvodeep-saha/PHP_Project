<?php
/**
 * Database Configuration & PDO Connection
 * Online Assignment Submission System
 * 
 * Demonstrates:
 * - PHP PDO (PHP Data Objects)
 * - Exception Handling (try-catch)
 * - UTF-8 (utf8mb4) character encoding
 * - Prepared statement emulation prevention
 */

// Database credentials for local XAMPP environment
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'online_assignment_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO connection instance.
 *
 * @return PDO
 * @throws PDOException
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            // Throw exceptions on SQL errors for structured try-catch handling
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Return rows as associative arrays ($row['column_name'])
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Use native MySQL prepared statements (prevents SQL injection)
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Persistent connections disabled for predictable state
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if database doesn't exist yet (SQLSTATE[HY000] [1049])
            if ($e->getCode() == 1049) {
                die(
                    '<div style="font-family:Segoe UI, sans-serif; max-width:650px; margin:50px auto; padding:25px; border-left:5px solid #dc3545; background:#fff3f3; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">' .
                    '<h2 style="color:#721c24; margin-top:0;">Database Not Found</h2>' .
                    '<p style="color:#555;">Database <strong>' . DB_NAME . '</strong> does not exist yet.</p>' .
                    '<p>Please run the installer to create the database and seed tables automatically:</p>' .
                    '<a href="' . (isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '') . '/install.php" style="display:inline-block; padding:10px 20px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:4px; font-weight:600;">Launch One-Click Database Installer &rarr;</a>' .
                    '</div>'
                );
            }

            // Connection error (e.g. MySQL not running in XAMPP)
            die(
                '<div style="font-family:Segoe UI, sans-serif; max-width:650px; margin:50px auto; padding:25px; border-left:5px solid #dc3545; background:#fff3f3; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">' .
                '<h2 style="color:#721c24; margin-top:0;">Database Connection Failed</h2>' .
                '<p style="color:#555;">Could not connect to MySQL server at <strong>' . DB_HOST . ':' . DB_PORT . '</strong>.</p>' .
                '<p style="color:#721c24; font-family:monospace; background:#ffebeb; padding:10px; border-radius:4px;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>' .
                '<h4 style="color:#333; margin-bottom:8px;">Troubleshooting Checklist:</h4>' .
                '<ul style="color:#555; line-height:1.6;">' .
                '<li>Open <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to <strong>MySQL</strong> and <strong>Apache</strong>.</li>' .
                '<li>Confirm MySQL is running on port <code>3306</code>.</li>' .
                '<li>Check that username is <code>root</code> with empty password (default XAMPP settings).</li>' .
                '</ul>' .
                '</div>'
            );
        }
    }

    return $pdo;
}

