<?php
// includes/db.php
declare(strict_types=1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'almatech_sms');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create database connection
try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
    
    // Set charset
    $db->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // In production, you might want to log this instead of displaying it
    die("Database connection failed. Please check configuration.");
}

// Make $db available globally
global $db;