<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'coordinator') {
    header('Location: login.php');
    exit;
}

// Include database configuration
include 'config/DBconfig.php';

try {
    // Database connection using config variables
    $conn = new mysqli($host, $dbuser, $dbpass, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'])) {
        $doc_id = intval($_POST['doc_id']);
        $action = $_POST['action']; // 'approve' or 'reject'
        $document_type = $_POST['document_type']; // 'all' or specific document type
        
        // Determine the status to set
        $status = ($action === 'approve') ? 'Approved' : 'Rejected';
        
        if ($document_type === 'all') {
            // Update all document statuses for this student
            $sql = "UPDATE documents SET 
                    certificate_of_completion = ?,
                    daily_time_record = ?,
                    performance_evaluation = ?,
                    narrative_report = ?,
                    printed_journal = ?,
                    company_profile = ?,
                    ojt_evaluation_form = ?
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssssssi', 
                $status, $status, $status, 
                $status, $status, $status, 
                $status, $doc_id);
        } else {
            // Update specific document type
            $sql = "UPDATE documents SET $document_type = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $status, $doc_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Documents updated successfully";
        } else {
            $_SESSION['error'] = "Error updating documents: " . $conn->error;
        }
        
        $stmt->close();
        
        // Redirect back to the previous page
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

header('Location: coordinator_documents.php');
exit;

?>
