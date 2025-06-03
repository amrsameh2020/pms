<?php
// config.php

// --- Database Credentials ---
// Replace these with your actual database credentials
// define('DB_HOST', 'localhost');
// define('DB_USERNAME', 'amrsam99_inventory_db'); // Your database username
// define('DB_PASSWORD', 'Z123456z@');            // Your database password
// define('DB_NAME', 'amrsam99_inventory_db');    // Your database name
// define('DB_CHARSET', 'utf8mb4');               // Character encoding for the database

//Replace these with your actual database credentials
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root'); // Your database username
define('DB_PASSWORD', '');            // Your database password
define('DB_NAME', 'pms');    // Your database name
define('DB_CHARSET', 'utf8mb4');               // Character encoding for the database

// --- Application Base URL ---
// This should be the root URL of your application on the web.
// Example: http://localhost/pms or https://yourdomain.com/app
// Ensure this path is correct for your environment.
if (!defined('APP_BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

    // __DIR__ is the directory of the current file (config.php).
    // $_SERVER['DOCUMENT_ROOT'] is the web server's document root.
    // We calculate the project's path relative to the document root.
    // This assumes config.php is in the project's root directory (e.g., /pms/config.php).
    $project_path_from_doc_root = str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\'), '', str_replace('\\', '/', __DIR__));
    
    define('APP_BASE_URL', rtrim($protocol . $host . $project_path_from_doc_root, '/'));
}

// --- Timezone Setting ---
date_default_timezone_set('Asia/Riyadh'); // Set your desired timezone

// --- Error Reporting ---
// For Development: Display all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// For Production, you might want to log errors to a file instead of displaying them:
/*
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0); // Report no errors to the screen
ini_set('log_errors', 1); // Enable error logging
// Ensure this path is correct and writable by the server.
// It's best if this log file is outside the public web root or in a protected logs/ directory.
$log_path = __DIR__ . '/../logs/php-error.log'; // Example: logs directory one level above config.php's directory
if (!file_exists(dirname($log_path))) {
    if (!mkdir(dirname($log_path), 0775, true) && !is_dir(dirname($log_path))) {
        // Fallback if logs directory cannot be created
        $log_path = __DIR__ . '/php-error.log'; // Log in the same directory as config.php (less secure)
    }
}
ini_set('error_log', $log_path);
*/

// Note: Constants like APP_NAME, ITEMS_PER_PAGE, and ZATCA settings
// will be loaded from the database in db_connect.php and defined there.
?>
