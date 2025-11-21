<?php
$db_host = getenv('DB_HOST') ?: 'dpg-d4fnncvpmn1nc73f3k1rg-a.singapore-postgres.render.com';
$db_name = getenv('DB_NAME') ?: 'ojt_system';
$db_user = getenv('DB_USER') ?: 'ojt_user';
$db_pass = getenv('DB_PASS') ?: 'uIR37XPSCdh0V5xDMxmy03UdfuXYJEPH';

// Try PostgreSQL first, fallback to MySQLi
try {
    // PostgreSQL PDO
    $conn = new PDO("pgsql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<!-- Using PostgreSQL -->";
} catch (PDOException $e) {
    // Fallback to MySQLi (for compatibility)
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    echo "<!-- Using MySQLi -->";
}
?>
