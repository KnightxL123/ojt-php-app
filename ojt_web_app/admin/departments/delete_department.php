<?php
// Include the database config to establish $conn
include 'config/DBconfig.php';

// Check if 'id' is provided in the URL
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);  // Sanitize to integer to prevent basic injection
    
    // Use prepared statement for better security (recommended over direct query)
    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    // Optional: Check if deletion was successful
    // if ($conn->affected_rows > 0) { echo "Deleted successfully."; }
}

// Redirect back to manage.php
header("Location: manage.php");
exit;
?>

