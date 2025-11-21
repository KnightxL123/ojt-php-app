<?php
// config/DBconfig.php - MySQL version
$host = 'dpg-d4fnncvpmn1nc73f3k1rg-a.singapore-postgres.render.com';
$dbname = 'ojt_system';
$dbuser = 'ojt_user';
$dbpass = 'uIR37XPSCdh0V5xDMxmy03UdfuXYJEPH';

try {
    // MySQL connection - PHP has built-in MySQL support
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $conn = new PDO($dsn, $dbuser, $dbpass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Test connection
    $conn->query("SELECT 1");
    
} catch (PDOException $e) {
    // Return JSON error instead of plain text
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed: ' . $e->getMessage(),
        'valid' => false
    ]);
    exit;
}
?>
