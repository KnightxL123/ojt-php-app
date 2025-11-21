<?php
session_start();

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit;
}

// Get the document ID from the URL
$doc_id = isset($_GET['doc_id']) ? $_GET['doc_id'] : 0;

if ($doc_id > 0) {
    // Connect to the database
    $host = 'localhost';
    $dbname = 'OJT';
    $dbuser = 'root';
    $dbpass = '';
    $conn = new mysqli($host, $dbuser, $dbpass, $dbname);
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }

    // Fetch the document details from the database
    $sql = "SELECT * FROM documents WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $file_path = $row['file_path']; // Assuming you store the path to the file in the 'file_path' column

        // Check if the file exists
        if (file_exists($file_path)) {
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));

            // Output the file
            readfile($file_path);
            exit;
        } else {
            echo "File not found.";
        }
    } else {
        echo "Document not found.";
    }

    $conn->close();
} else {
    echo "Invalid document ID.";
}
?>
