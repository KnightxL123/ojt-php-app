<?php
session_start();
include 'config/DBconfig.php';


function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    
    // Role-specific fields
    $department_id = null;
    $program_id = null;
    $section_id = null;
    
    if ($role === 'coordinator') {
        $department_id = $_POST['department_id'] ?? null;
    } else { // adviser
        $department_id = $_POST['adviser_department_id'] ?? null;
        $program_id = $_POST['program_id'] ?? null;
        $section_id = $_POST['section_id'] ?? null;
    }

    // Basic validation
    if ($username === '' || $email === '' || $password === '') {
        header('Location: register.php?error=' . urlencode('Please fill all required fields.'));
        exit;
    }

    if (!is_valid_email($email)) {
        header('Location: register.php?error=' . urlencode('Invalid email address.'));
        exit;
    }

    if ($password !== $confirm_password) {
        header('Location: register.php?error=' . urlencode('Passwords do not match.'));
        exit;
    }

    if (strlen($password) < 6) {
        header('Location: register.php?error=' . urlencode('Password must be at least 6 characters.'));
        exit;
    }

    // Additional validation for coordinators
    if ($role === 'coordinator' && empty($department_id)) {
        header('Location: register.php?error=' . urlencode('Please select a department for coordinator role.'));
        exit;
    }

    // Additional validation for advisers
    if ($role === 'user') {
        if (empty($department_id)) {
            header('Location: register.php?error=' . urlencode('Please select a department.'));
            exit;
        }
        if (empty($program_id)) {
            header('Location: register.php?error=' . urlencode('Please select a program.'));
            exit;
        }
        if (empty($section_id)) {
            header('Location: register.php?error=' . urlencode('Please select a section.'));
            exit;
        }
    }

    // Check if department exists
    $stmt = $conn->prepare("SELECT id FROM departments WHERE id = ? AND status = 'active' AND deleted_at IS NULL");
    $stmt->bind_param('i', $department_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        header('Location: register.php?error=' . urlencode('Invalid department selected.'));
        exit;
    }
    $stmt->close();

    // For advisers, check program and section
    if ($role === 'user') {
        // Check program exists and belongs to department
        $stmt = $conn->prepare("SELECT id FROM programs WHERE id = ? AND department_id = ? AND status = 'active'");
        $stmt->bind_param('ii', $program_id, $department_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            header('Location: register.php?error=' . urlencode('Invalid program selected.'));
            exit;
        }
        $stmt->close();
        
        // Check section exists and belongs to program
        $stmt = $conn->prepare("SELECT id FROM sections WHERE id = ? AND program_id = ?");
        $stmt->bind_param('ii', $section_id, $program_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            header('Location: register.php?error=' . urlencode('Invalid section selected.'));
            exit;
        }
        $stmt->close();
    }

    // Check username or email exists
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        header('Location: register.php?error=' . urlencode('Username or email already taken.'));
        exit;
    }
    $stmt->close();

    // Hash password and insert new user
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    if ($role === 'coordinator') {
        $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash, role, department_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssi', $username, $email, $password_hash, $role, $department_id);
    } else {
        // For advisers, we'll store the section_id in a separate table
        $conn->begin_transaction();
        
        try {
            // Insert user
            $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $username, $email, $password_hash, $role);
            $stmt->execute();
            $user_id = $conn->insert_id;
            $stmt->close();
            
            // Insert adviser
            $stmt = $conn->prepare('INSERT INTO advisers (name, email, department_id) VALUES (?, ?, ?)');
            $stmt->bind_param('ssi', $username, $email, $department_id);
            $stmt->execute();
            $adviser_id = $conn->insert_id;
            $stmt->close();
            
            // Link adviser to section
            $stmt = $conn->prepare('INSERT INTO section_adviser (section_id, adviser_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $section_id, $adviser_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            header('Location: login.php?msg=' . urlencode('Registration successful. Please login.'));
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            header('Location: register.php?error=' . urlencode('Registration failed. Try again.'));
            exit;
        }
    }

    if ($stmt->execute()) {
        $stmt->close();
        header('Location: login.php?msg=' . urlencode('Registration successful. Please login.'));
        exit;
    } else {
        $stmt->close();
        header('Location: register.php?error=' . urlencode('Registration failed. Try again.'));
        exit;
    }
} else {
    header('Location: register.php');
    exit;
}

?>
