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

    try {
        // Check if department exists
        $stmt = $conn->prepare("SELECT id FROM departments WHERE id = ? AND status = 'active' AND deleted_at IS NULL");
        $stmt->execute([$department_id]);
        if ($stmt->rowCount() === 0) {
            header('Location: register.php?error=' . urlencode('Invalid department selected.'));
            exit;
        }

        // For advisers, check program and section
        if ($role === 'user') {
            // Check program exists and belongs to department
            $stmt = $conn->prepare("SELECT id FROM programs WHERE id = ? AND department_id = ? AND status = 'active'");
            $stmt->execute([$program_id, $department_id]);
            if ($stmt->rowCount() === 0) {
                header('Location: register.php?error=' . urlencode('Invalid program selected.'));
                exit;
            }
            
            // Check section exists and belongs to program
            $stmt = $conn->prepare("SELECT id FROM sections WHERE id = ? AND program_id = ?");
            $stmt->execute([$section_id, $program_id]);
            if ($stmt->rowCount() === 0) {
                header('Location: register.php?error=' . urlencode('Invalid section selected.'));
                exit;
            }
        }

        // Check username or email exists
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            header('Location: register.php?error=' . urlencode('Username or email already taken.'));
            exit;
        }

        // Hash password and insert new user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        if ($role === 'coordinator') {
            $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash, role, department_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$username, $email, $password_hash, $role, $department_id]);
        } else {
            // For advisers, we'll store the section_id in a separate table
            $conn->beginTransaction();
            
            try {
                // Insert user
                $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
                $stmt->execute([$username, $email, $password_hash, $role]);
                $user_id = $conn->lastInsertId();
                
                // Insert adviser
                $stmt = $conn->prepare('INSERT INTO advisers (name, email, department_id) VALUES (?, ?, ?)');
                $stmt->execute([$username, $email, $department_id]);
                $adviser_id = $conn->lastInsertId();
                
                // Link adviser to section
                $stmt = $conn->prepare('INSERT INTO section_adviser (section_id, adviser_id) VALUES (?, ?)');
                $stmt->execute([$section_id, $adviser_id]);
                
                $conn->commit();
                
                header('Location: login.php?msg=' . urlencode('Registration successful. Please login.'));
                exit;
            } catch (Exception $e) {
                $conn->rollBack();
                header('Location: register.php?error=' . urlencode('Registration failed. Try again.'));
                exit;
            }
        }

        header('Location: login.php?msg=' . urlencode('Registration successful. Please login.'));
        exit;
    } catch (PDOException $e) {
        header('Location: register.php?error=' . urlencode('Database error: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: register.php');
    exit;
}
?>
