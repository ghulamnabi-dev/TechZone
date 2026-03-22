<?php
/**
 * Centralized database configuration and connection bootstrap.
 *
 * This file is shared by both the Marketing Site and Admin Portal.
 * For production, configure credentials via environment variables:
 *   DB_HOST, DB_USER, DB_PASS, DB_NAME
 */

// Database connection settings (with environment variable overrides)
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'techzone_db';

// Create a single PDO instance
try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Optional: set default fetch mode for cleaner code
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // In a portfolio project it's better to fail clearly than to hide errors
    die("Database connection failed: " . $e->getMessage());
}


