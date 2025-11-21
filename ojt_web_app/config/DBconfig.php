<?php
$db_host = getenv('DB_HOST') ?: 'dpg-d4fnncvpmn1nc73f3k1rg-a.singapore-postgres.render.com';
$db_name = getenv('DB_NAME') ?: 'ojt_system';
$db_user = getenv('DB_USER') ?: 'ojt_user';
$db_pass = getenv('DB_PASS') ?: 'uIR37XPSCdh0V5xDMxmy03UdfuXYJEPH';

try {
    $conn = new PDO("pgsql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
