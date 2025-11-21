<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'coordinator') {
    header('Location: login.php');
    exit;
}

include 'config/DBconfig.php';


header('Content-Type: application/json');

try {
    $conn = new mysqli($host, $dbuser, $dbpass, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
        $student_id = intval($_POST['student_id']);
        $action = $_POST['action']; // 'approve' or 'reject'
        
        // Determine the status to set
        $status = ($action === 'approve') ? 'Approved' : 'Rejected';
        
        // Check if student exists
        $stmt = $conn->prepare("SELECT id FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Student not found");
        }
        $stmt->close();
        
        // Update or insert document status
        $sql = "INSERT INTO student_documents (
                    student_id, 
                    registration_status, 
                    monitoring_status, 
                    recommendation_status, 
                    acceptance_status, 
                    training_plan_status, 
                    waiver_status, 
                    moa_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    registration_status = VALUES(registration_status),
                    monitoring_status = VALUES(monitoring_status),
                    recommendation_status = VALUES(recommendation_status),
                    acceptance_status = VALUES(acceptance_status),
                    training_plan_status = VALUES(training_plan_status),
                    waiver_status = VALUES(waiver_status),
                    moa_status = VALUES(moa_status)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isssssss', 
            $student_id, $status, $status, 
            $status, $status, $status, 
            $status, $status);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Requirements updated successfully']);
        } else {
            throw new Exception("Error updating requirements: " . $conn->error);
        }
        
        $stmt->close();
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;

?>
