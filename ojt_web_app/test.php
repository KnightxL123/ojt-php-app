<?php
require_once 'config/DBconfig.php';
echo "Database connection: ";
try {
    $conn->query("SELECT 1");
    echo "✅ SUCCESS";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage();
}
?>
