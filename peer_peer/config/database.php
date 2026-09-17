<?php
// config/database.php

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'textbook_exchange');

/**
 * Returns a PDO database connection instance.
 * Uses a static variable to implement the Singleton pattern for the request lifecycle.
 * 
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if we are running in the context of setup.php
            if (strpos($e->getMessage(), 'Unknown database') !== false) {
                // Return connection to host only so setup can create the database
                $dsnHost = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
                return new PDO($dsnHost, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            }
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
        }
    }
    return $pdo;
}
