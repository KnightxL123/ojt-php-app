<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Include DB config
require_once 'config/DBconfig.php';

// Check if database connection is successful
if (!isset($conn)) {
    echo json_encode(['error' => 'Database connection not established', 'valid' => false]);
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$endpoint = $_GET['action'] ?? '';

switch ($endpoint) {
    case 'login':
        handleLogin($input);
        break;
    case 'validate_student':
        validateStudent($input);
        break;
    case 'register':
        registerStudent($input);
        break;
    default:
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

function handleLogin($input) {
    global $conn;
    
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['error' => 'Email and password required']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }
        
        // For demo - using direct password comparison
        // In production, use password_verify()
        if ($user['password_hash'] === $password) {
            $token = base64_encode(json_encode([
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'exp' => time() + (24 * 60 * 60)
            ]));
            
            echo json_encode([
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'departmentId' => $user['department_id']
                ]
            ]);
        } else {
            echo json_encode(['error' => 'Invalid credentials']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function validateStudent($input) {
    global $conn;
    
    $studentId = $input['student_id'] ?? '';
    
    if (empty($studentId)) {
        echo json_encode(['valid' => false, 'error' => 'Student ID required']);
        return;
    }
    
    try {
        // Check if student exists in students table
        $stmt = $conn->prepare("
            SELECT s.*, sec.name as section_name, d.name as department_name 
            FROM students s 
            LEFT JOIN sections sec ON s.section_id = sec.id 
            LEFT JOIN departments d ON sec.department_id = d.id 
            WHERE s.student_id = ?
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            // Check if student already has a user account
            $userStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email LIKE ?");
            $userStmt->execute([$studentId, "%$studentId%"]);
            $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingUser) {
                echo json_encode([
                    'valid' => false,
                    'error' => 'Student already has an account'
                ]);
            } else {
                echo json_encode([
                    'valid' => true,
                    'student' => $student
                ]);
            }
        } else {
            echo json_encode([
                'valid' => false,
                'error' => 'Student ID not found in system'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(['valid' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function registerStudent($input) {
    global $conn;
    
    $studentId = $input['student_id'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($studentId) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        return;
    }
    
    try {
        // First, verify student exists
        $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['status' => 'error', 'message' => 'Student ID not found']);
            return;
        }
        
        // Check if email or username already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $checkStmt->execute([$email, $studentId]);
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingUser) {
            echo json_encode(['status' => 'error', 'message' => 'Email or Student ID already registered']);
            return;
        }
        
        // Create user account for student
        $insertStmt = $conn->prepare("
            INSERT INTO users (username, email, password_hash, role, created_at) 
            VALUES (?, ?, ?, 'user', NOW())
        ");
        
        $inserted = $insertStmt->execute([
            $studentId, 
            $email, 
            $password // In production, use password_hash($password, PASSWORD_DEFAULT)
        ]);
        
        if ($inserted) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Registration successful! You can now login.'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
