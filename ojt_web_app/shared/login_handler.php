<?php
session_start();
include 'config/DBconfig.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header('Location: login.php?error=' . urlencode('Please enter username and password.'));
        exit;
    }

    // Prepare statement with department_id included
    $stmt = $conn->prepare('SELECT id, username, password_hash, role, department_id FROM users WHERE username = ? LIMIT 1');
    if (!$stmt) {
        header('Location: login.php?error=' . urlencode('Database error. Please try again later.'));
        exit;
    }
    
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $uname, $password_hash, $role, $department_id);
        $stmt->fetch();

        if (password_verify($password, $password_hash)) {
            // Password matches, set session variables
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $uname;
            $_SESSION['role'] = $role;
            $_SESSION['department_id'] = $department_id; // Store department_id for coordinators

            // Redirect based on role
            switch ($role) {
                case 'admin':
                    header('Location: admin_panel.php');
                    break;
                case 'coordinator':
                    header('Location: coordinator_panel.php');
                    break;
                default:
                    header('Location: user_panel.php');
            }
            exit;
        } else {
            // Password doesn't match
            header('Location: login.php?error=' . urlencode('Invalid username or password.'));
            exit;
        }
    } else {
        // No matching user
        header('Location: login.php?error=' . urlencode('Invalid username or password.'));
        exit;
    }
} else {
    // Only POST requests allowed
    header('Location: login.php');
    exit;
}

?>
