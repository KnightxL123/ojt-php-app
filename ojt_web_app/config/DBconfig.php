<?php
// config/DBconfig.php
// Database configuration for Render PostgreSQL
$host = getenv('DB_HOST') ?: 'dpg-d4fnncvpmn1nc73f3k1rg-a.singapore-postgres.render.com';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'ojt_system';
$dbuser = getenv('DB_USER') ?: 'ojt_user';
$dbpass = getenv('DB_PASS') ?: 'uIR37XPSCdh0V5xDMxmy03UdfuXYJEPH';

try {
    // PDO connection string for PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $conn = new PDO($dsn, $dbuser, $dbpass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed: ' . $e->getMessage());
}
?>
