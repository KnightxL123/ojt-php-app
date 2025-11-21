<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config/DBconfig.php';

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit(0);

$input = json_decode(file_get_contents('php://input'), true);
$endpoint = $_GET['action'] ?? '';

switch ($endpoint) {
    case 'login': handleLogin($input); break;
    case 'validate_student': validateStudent($input); break;
    case 'register': registerStudent($input); break;
    default: echo json_encode(['error' => 'Endpoint not found']);
}

function handleLogin($input) {
    global $conn;
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['password_hash'] === $password) {
        $token = base64_encode(json_encode([
            'id' => $user['id'], 'email' => $user['email'], 
            'role' => $user['role'], 'exp' => time() + 86400
        ]));
        
        echo json_encode([
            'token' => $token,
            'user' => [
                'id' => $user['id'], 'username' => $user['username'], 
                'email' => $user['email'], 'role' => $user['role'],
                'departmentId' => $user['department_id']
            ]
        ]);
    } else {
        echo json_encode(['error' => 'Invalid credentials']);
    }
}

function validateStudent($input) {
    global $conn;
    $studentId = $input['student_id'] ?? '';
    
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
        $userStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $userStmt->execute([$studentId]);
        $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingUser) {
            echo json_encode(['valid' => false, 'error' => 'Student already has an account']);
        } else {
            echo json_encode(['valid' => true, 'student' => $student]);
        }
    } else {
        echo json_encode(['valid' => false, 'error' => 'Student ID not found']);
    }
}

function registerStudent($input) {
    global $conn;
    $studentId = $input['student_id'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['status' => 'error', 'message' => 'Student ID not found']);
        return;
    }
    
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $checkStmt->execute([$email, $studentId]);
    
    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['status' => 'error', 'message' => 'Email or Student ID already registered']);
        return;
    }
    
    $insertStmt = $conn->prepare("
        INSERT INTO users (username, email, password_hash, role, created_at) 
        VALUES (?, ?, ?, 'user', NOW())
    ");
    
    if ($insertStmt->execute([$studentId, $email, $password])) {
        echo json_encode(['status' => 'success', 'message' => 'Registration successful!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
    }
}
?>
