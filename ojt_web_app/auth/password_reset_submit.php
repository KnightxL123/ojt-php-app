<?php
session_start();

$host = 'localhost';
$dbname = 'OJT';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die('Connection failed: '.$conn->connect_error);
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: password_reset_request.php');
    exit;
}

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($token === '' || $password === '' || $confirm_password === '') {
    header('Location: password_reset.php?token=' . urlencode($token) . '&error=' . urlencode('All fields are required.'));
    exit;
}
if ($password !== $confirm_password) {
    header('Location: password_reset.php?token=' . urlencode($token) . '&error=' . urlencode('Passwords do not match.'));
    exit;
}
if (strlen($password) < 6) {
    header('Location: password_reset.php?token=' . urlencode($token) . '&error=' . urlencode('Password must be at least 6 characters.'));
    exit;
}

// Validate token and get user
$stmt = $conn->prepare('
    SELECT user_id, expires_at FROM password_resets WHERE token = ? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    $stmt->close();
    header('Location: password_reset_request.php?error=' . urlencode('Invalid or expired token.'));
    exit;
}

$stmt->bind_result($user_id, $expires_at);
$stmt->fetch();

if (strtotime($expires_at) < time()) {
    // Token expired, delete it
    $stmt->close();
    $del_stmt = $conn->prepare('DELETE FROM password_resets WHERE token = ?');
    $del_stmt->bind_param('s', $token);
    $del_stmt->execute();
    $del_stmt->close();
    header('Location: password_reset_request.php?error=' . urlencode('Token expired. Please request a new reset.'));
    exit;
}
$stmt->close();

// Update password hash
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->bind_param('si', $hashed_password, $user_id);
if (!$stmt->execute()) {
    $stmt->close();
    header('Location: password_reset.php?token=' . urlencode($token) . '&error=' . urlencode('Failed to reset password.'));
    exit;
}
$stmt->close();

// Delete token after use
$del_stmt = $conn->prepare('DELETE FROM password_resets WHERE token = ?');
$del_stmt->bind_param('s', $token);
$del_stmt->execute();
$del_stmt->close();

header('Location: login.php?msg=' . urlencode('Password reset successful. Please login.'));
exit;
?>
