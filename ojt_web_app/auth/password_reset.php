<?php
session_start();
include 'config/DBconfig.php'

if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_panel.php');
    } else {
        header('Location: user_panel.php');
    }
    exit;
}

if (!isset($_GET['token']) || empty($_GET['token'])) {
    header('Location: password_reset_request.php?error=' . urlencode('Invalid or missing token.'));
    exit;
}

$token = $_GET['token'];

// Validate token
$stmt = $conn->prepare('
    SELECT users.username, password_resets.expires_at, password_resets.user_id 
    FROM password_resets 
    JOIN users ON password_resets.user_id = users.id 
    WHERE password_resets.token = ? LIMIT 1');
$stmt->bind_param('s', $token);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows !== 1) {
    $stmt->close();
    header('Location: password_reset_request.php?error=' . urlencode('Invalid or expired token.'));
    exit;
}

$stmt->bind_result($username, $expires_at, $user_id);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Set New Password</title>
<style>
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    margin: 0; padding: 0; height: 100vh;
    display: flex; justify-content: center; align-items: center;
  }
  .container {
    background: rgba(255,255,255,0.15);
    padding: 30px 40px;
    border-radius: 12px;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
    max-width: 360px;
    width: 100%;
    text-align: center;
  }
  h2 {
    margin-bottom: 24px; font-weight: 700; font-size: 28px; letter-spacing: 1.5px;
  }
  input[type="password"] {
    width: 100%; padding: 14px 12px; margin: 8px 0 20px 0;
    border: none; border-radius: 8px; font-size: 16px; outline: none;
  }
  input[type="password"]:focus {
    box-shadow: 0 0 8px #a18cd1;
  }
  button.reset-btn {
    width: 100%; padding: 14px 0;
    background: #764ba2; border: none; border-radius: 8px;
    color: #fff; font-weight: 700; font-size: 18px; cursor: pointer;
    transition: background 0.3s ease;
  }
  button.reset-btn:hover {
    background: #667eea;
  }
  .message {
    margin-top: 20px; font-weight: 600;
    color: #ffcccc;
  }
  a {
    color: #ddd; font-size: 14px; text-decoration: none;
  }
  a:hover {
    text-decoration: underline;
  }
</style>
</head>
<body>
  <div class="container">
    <h2>Set New Password for <?php echo htmlspecialchars($username); ?></h2>
    <?php
    if (isset($_GET['error'])) {
        echo '<div class="message">' . htmlspecialchars($_GET['error']) . '</div>';
    }
    if (isset($_GET['msg'])) {
        echo '<div class="message" style="color:#ccffcc;">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>
    <form action="password_reset_submit.php" method="post" autocomplete="off">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
      <input type="password" name="password" placeholder="New Password" required />
      <input type="password" name="confirm_password" placeholder="Confirm New Password" required />
      <button type="submit" class="reset-btn">Reset Password</button>
    </form>
    <p>Remembered password? <a href="login.php">Login here</a></p>
  </div>
</body>
</html>

