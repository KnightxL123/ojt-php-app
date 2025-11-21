<?php
// config/DBconfig.php - MySQL version
$host = getenv('DB_HOST') ?: 'dpg-d4fnncvpmn1nc73f3k1rg-a.singapore-postgres.render.com';
$dbname = getenv('DB_NAME') ?: 'ojt_system';
$dbuser = getenv('DB_USER') ?: 'ojt_user';
$dbpass = getenv('DB_PASS') ?: 'uIR37XPSCdh0V5xDMxmy03UdfuXYJEPH';

try {
    // MySQL connection instead of PostgreSQL
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $conn = new PDO($dsn, $dbuser, $dbpass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed: ' . $e->getMessage());
}
?>
