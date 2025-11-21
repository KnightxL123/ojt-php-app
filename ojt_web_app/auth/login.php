<?php
session_start();

// Include database configuration
include '../config/DBconfig.php';


// Redirect logged in users
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: ../admin/admin_panel.php');
            break;
        case 'coordinator':
            header('Location: ../coordinator/coordinator_panel.php');
            break;
        default:
            header('Location: ../student/user_panel.php');
    }
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database connection using config variables
    $conn = new mysqli($host, $dbuser, $dbpass, $dbname);
    
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }
    
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $stmt = $conn->prepare("SELECT id, username, password_hash, role, department_id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password_hash'])) {
            // For coordinators, check if their department is active
            if ($user['role'] === 'coordinator') {
                $dept_id = $user['department_id'];
                
                $stmt2 = $conn->prepare("SELECT status FROM departments WHERE id = ?");
                $stmt2->bind_param("i", $dept_id);
                $stmt2->execute();
                $dept_result = $stmt2->get_result();
                
                if ($dept_result->num_rows > 0) {
                    $dept_status = $dept_result->fetch_assoc()['status'];
                    
                    if ($dept_status === 'inactive') {
                        $error = "Login failed: Your department has been deactivated. Please contact administrator.";
                    } else {
                        // Department is active, proceed with login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['department_id'] = $user['department_id'];
                        
                        header('Location: ../coordinator/coordinator_panel.php');
                        exit;
                    }
                } else {
                    $error = "Login failed: Department not found.";
                }
                $stmt2->close();
            } else {
                // For admin and regular users, proceed normally
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/admin_panel.php');
                } else {
                    header('Location: ../student/user_panel.php');
                }
                exit;
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/auth/login.css">
    <title>OJT Management System - Login</title>
    <style>
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            text-align: center;
        }
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-box">
            <img src="/ojt-management-system/backend/assets/images/plsplogo.jpg" alt="logo" class="logo">
            <h2>Login</h2>
            <?php
            // Display error from form processing
            if (!empty($error)) {
                echo '<div class="message error-message">' . htmlspecialchars($error) . '</div>';
            }
            
            // Display messages from URL parameters
            if (isset($_GET['error'])) {
                echo '<div class="message error-message">' . htmlspecialchars($_GET['error']) . '</div>';
            }
            if (isset($_GET['msg'])) {
                echo '<div class="message success-message">' . htmlspecialchars($_GET['msg']) . '</div>';
            }
            ?>
            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <input type="text" name="username" placeholder="Username" required autocomplete="username" />
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required autocomplete="current-password" />
                </div>
                <button type="submit" class="login-btn">Login</button>
            </form>
            <div class="links">
                <p><a href="password_reset_request.php">Forgot Password?</a></p>
                <p>Don't have an account? <a href="register.php">Register as Coordinator</a></p>
            </div>
        </div>
    </div>
</body>

</html>

