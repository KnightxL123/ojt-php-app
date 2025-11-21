<?php
// Database configuration for Render PostgreSQL
$db_host = 'dpg-d4fnncvpmn1nc73f3k1rg-a.singapore-postgres.render.com';
$db_name = 'ojt_system';
$db_user = 'ojt_user';
$db_pass = 'uIR37XPSCdh0V5xDMxmy03UdfuXYJEPH';

try {
    // PostgreSQL connection
    $conn = new PDO("pgsql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
