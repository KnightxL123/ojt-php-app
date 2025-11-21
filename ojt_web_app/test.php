<?php
// test_db.php
header('Content-Type: text/plain');

echo "Testing database connection...\n";

try {
    $host = 'dpg-d4fnncvpmn1nc73f3k1rg-a.singapore-postgres.render.com';
    $dbname = 'ojt_system';
    $dbuser = 'ojt_user';
    $dbpass = 'uIR37XPSCdh0V5xDMxmy03UdfuXYJEPH';
    
    // Test PostgreSQL
    echo "Testing PostgreSQL...\n";
    $dsn = "pgsql:host=$host;dbname=$dbname";
    $conn = new PDO($dsn, $dbuser, $dbpass);
    echo "✅ PostgreSQL connected successfully!\n";
    
} catch (Exception $e) {
    echo "❌ PostgreSQL failed: " . $e->getMessage() . "\n";
    
    // Test MySQL as fallback
    echo "Testing MySQL...\n";
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $conn = new PDO($dsn, $dbuser, $dbpass);
        echo "✅ MySQL connected successfully!\n";
    } catch (Exception $e2) {
        echo "❌ MySQL also failed: " . $e2->getMessage() . "\n";
    }
}
?>
