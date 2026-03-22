<?php
/**
 * Centralized database configuration and connection bootstrap.
 *
 * This file is shared by both the Marketing Site and Admin Portal.
 * For production, configure credentials via either:
 *   - MYSQL_URL or DATABASE_URL (e.g. Railway: mysql://user:pass@host:port/dbname)
 *   - or DB_HOST, DB_USER, DB_PASS, DB_NAME
 */

$db_url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: '';

if ($db_url !== '' && (strpos($db_url, 'mysql://') === 0 || strpos($db_url, 'mysql2://') === 0)) {
    $parts = parse_url($db_url);
    $db_host = $parts['host'] ?? 'localhost';
    $db_user = isset($parts['user']) ? rawurldecode($parts['user']) : 'root';
    $db_pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
    $db_name = isset($parts['path']) ? trim(rawurldecode($parts['path']), '/') : 'techzone_db';
    $db_port = isset($parts['port']) ? (int) $parts['port'] : null;
    $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4';
    if ($db_port !== null && $db_port > 0) {
        $dsn .= ';port=' . $db_port;
    }
} else {
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') ?: '';
    $db_name = getenv('DB_NAME') ?: 'techzone_db';
    $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4';
}

// Create a single PDO instance
try {
    $conn = new PDO($dsn, $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Optional: set default fetch mode for cleaner code
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // In a portfolio project it's better to fail clearly than to hide errors
    die("Database connection failed: " . $e->getMessage());
}


