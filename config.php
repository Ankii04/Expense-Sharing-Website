<?php
// Database configuration - works for both local and Railway
// Railway automatically provides these environment variables
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'expense_maker');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');

// Google OAuth configuration (optional)
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET');

// Application settings
define('APP_NAME', 'Expense Maker');
define('APP_URL', getenv('RAILWAY_PUBLIC_DOMAIN') ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN') : 'http://localhost:8000');

// Session configuration - only set if session not started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', getenv('RAILWAY_ENVIRONMENT') ? 1 : 0); // HTTPS on Railway
}

// Error reporting
if (getenv('RAILWAY_ENVIRONMENT')) {
    // Production - log errors, don't display
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    // Development - show errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show debug info in development
    if (!getenv('RAILWAY_ENVIRONMENT')) {
        die("Connection failed: " . $e->getMessage() . "<br>Host: " . DB_HOST . "<br>Database: " . DB_NAME);
    }
    
    // In production, check if Railway MySQL is configured
    if (!getenv('MYSQLHOST')) {
        die("Database not configured. Please add MySQL service in Railway.");
    }
    
    // TEMPORARY DEBUGGING: Show exact error
    die("Database connection failed: " . $e->getMessage() . "<br>Host: " . DB_HOST . "<br>Port: " . DB_PORT . "<br>User: " . DB_USER . "<br>DB: " . DB_NAME);
    
    // die("Database connection failed. Please check Railway MySQL service is running.");
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
