<?php
// config/DBconfig.php
// Database configuration for Render PostgreSQL
$host = getenv('DB_HOST') ?: 'dpg-d4fnncvpm1nc73f3kl7g-a';  // Fallback for local dev
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'ojt-database';
$dbuser = getenv('DB_USER') ?: 'ojt_user';
$dbpass = getenv('DB_PASS') ?: 'uIR37XPSCdh0V5xDMxmy03UdfuXYJEPH';

try {
    // PDO connection string for PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$dbuser;password=$dbpass";
    $conn = new PDO($dsn);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // Enable exceptions for errors
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>


