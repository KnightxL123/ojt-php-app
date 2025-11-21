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


$email = trim($_POST['email'] ?? '');

if ($email === '') {
    header('Location: password_reset_request.php?error=' . urlencode('Please enter your email.'));
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: password_reset_request.php?error=' . urlencode('Invalid email address.'));
    exit;
}

// Check if email exists
$stmt = $conn->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    $stmt->close();
    // To avoid email enumeration, show generic message
    header('Location: password_reset_request.php?msg=' . urlencode('If the email exists, a reset link has been sent.'));
    exit;
}

$stmt->bind_result($user_id, $username);
$stmt->fetch();
$stmt->close();

// Generate secure token
$token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', time() + 3600); // expire in 1 hour

// Insert or update token for user
$stmt = $conn->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)');
$stmt->bind_param('iss', $user_id, $token, $expires_at);
if (!$stmt->execute()) {
    $stmt->close();
    header('Location: password_reset_request.php?error=' . urlencode('Failed to create reset token.'));
    exit;
}
$stmt->close();

// Send reset email
$reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
    . "://{$_SERVER['HTTP_HOST']}"
    . dirname($_SERVER['PHP_SELF'])
    . "/password_reset.php?token=$token";

// Email headers and body
$to = $email;
$subject = "Password Reset Request";
$message = "Hello $username,\n\n";
$message .= "We received a request to reset your password. Please click the link below to reset it:\n\n";
$message .= "$reset_link\n\n";
$message .= "This link is valid for 1 hour.\n\n";
$message .= "If you did not request this, please ignore this email.\n\nRegards,\nYour Website Team";

$headers = 'From: no-reply@yourdomain.com' . "\r\n" .
    'Reply-To: no-reply@yourdomain.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

$mail_sent = mail($to, $subject, $message, $headers);

// For demo or development, uncomment below to show reset link if email fails for testing:
// echo "Reset Link (for testing): $reset_link";

if ($mail_sent) {
    header('Location: password_reset_request.php?msg=' . urlencode('If the email exists, a reset link has been sent.'));
} else {
    header('Location: password_reset_request.php?error=' . urlencode('Failed to send reset email.'));
}
exit;
?>
