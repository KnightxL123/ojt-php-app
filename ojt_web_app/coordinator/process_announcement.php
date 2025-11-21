<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'coordinator') {
    header('Location: ../../auth/login.php');
    exit;
}

$host = 'localhost';
$dbname = 'ojt';
$dbuser = 'root';
$dbpass = '';

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $sender_id = $_SESSION['user_id'];
    $recipients = $_POST['recipients'] ?? [];

    // Validate input
    if (empty($title) || empty($message)) {
        $_SESSION['error'] = "Title and message are required.";
        header('Location: coordinator_announcement.php');
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert announcement
        $stmt = $conn->prepare("INSERT INTO announcements (sender_id, title, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $sender_id, $title, $message);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create announcement: " . $stmt->error);
        }
        
        $announcement_id = $stmt->insert_id;
        $stmt->close();

        // Handle recipients
        if (in_array('all', $recipients)) {
            // Send to all students in coordinator's department
            $coordinator_stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
            $coordinator_stmt->bind_param("i", $sender_id);
            $coordinator_stmt->execute();
            $result = $coordinator_stmt->get_result();
            $coordinator = $result->fetch_assoc();
            $department_id = $coordinator['department_id'];
            $coordinator_stmt->close();

            if ($department_id) {
                // Get all students in this department
                $student_stmt = $conn->prepare("
                    SELECT s.id FROM students s 
                    JOIN sections sec ON s.section_id = sec.id 
                    WHERE sec.department_id = ?
                ");
                $student_stmt->bind_param("i", $department_id);
                $student_stmt->execute();
                $student_result = $student_stmt->get_result();
                
                while ($student = $student_result->fetch_assoc()) {
                    $recipient_stmt = $conn->prepare("INSERT INTO announcement_recipients (announcement_id, recipient_id) VALUES (?, ?)");
                    $recipient_stmt->bind_param("ii", $announcement_id, $student['id']);
                    $recipient_stmt->execute();
                    $recipient_stmt->close();
                }
                $student_stmt->close();
            }
        }

        // Send to faculty if selected
        if (in_array('faculty', $recipients)) {
            // Get all coordinators in the same department
            $faculty_stmt = $conn->prepare("SELECT id FROM users WHERE department_id = (SELECT department_id FROM users WHERE id = ?) AND role IN ('coordinator', 'admin')");
            $faculty_stmt->bind_param("i", $sender_id);
            $faculty_stmt->execute();
            $faculty_result = $faculty_stmt->get_result();
            
            while ($faculty = $faculty_result->fetch_assoc()) {
                if ($faculty['id'] != $sender_id) { // Don't send to self
                    $recipient_stmt = $conn->prepare("INSERT INTO announcement_recipients (announcement_id, recipient_id) VALUES (?, ?)");
                    $recipient_stmt->bind_param("ii", $announcement_id, $faculty['id']);
                    $recipient_stmt->execute();
                    $recipient_stmt->close();
                }
            }
            $faculty_stmt->close();
        }

        $conn->commit();
        $_SESSION['success'] = "Announcement posted successfully!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error posting announcement: " . $e->getMessage();
    }

    header('Location: coordinator_announcement.php');
    exit;
}
?>