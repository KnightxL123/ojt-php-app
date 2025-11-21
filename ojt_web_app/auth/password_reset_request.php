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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Password Reset Request</title>
<style>
  body {
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(to bottom, #006400, #008000);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
  }
  .container {
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .reset-box {
    background: rgb(173, 173, 173);
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 0 10px rgb(82, 82, 82);
    width: 400px;
  }
  .logo {
    width: 100px;
    margin: 10px;
  }
  h2 {
    margin: 10px 0;
  }
  input[type="email"] {
    width: 80%;
    padding: 10px;
    margin: 10px 0;
    border-radius: 5px;
    border: 1px solid #ccc;
  }
  .send-btn {
    display: block;
    width: 80%;
    padding: 10px;
    background: green;
    color: white;
    text-align: center;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    margin: 10px auto;
    border: none;
    cursor: pointer;
  }
  .send-btn:hover {
    background: darkgreen;
  }
  p {
    margin-top: 10px;
  }
  p a {
    text-decoration: none;
    color: blue;
  }
  .message {
    margin: 10px 0;
  }
</style>
</head>
<body>
  <div class="container">
    <div class="reset-box">
      <img src="..\image\plsplogo.jpg" alt="logo" class="logo">
      <h2>Password Reset</h2>
      <?php
      if (isset($_GET['error'])) {
          echo '<div class="message" style="color:red;">' . htmlspecialchars($_GET['error']) . '</div>';
      }
      if (isset($_GET['msg'])) {
          echo '<div class="message" style="color:green;">' . htmlspecialchars($_GET['msg']) . '</div>';
      }
      ?>
      <form action="password_reset_handler.php" method="post" autocomplete="off">
        <input type="email" name="email" placeholder="Enter your registered email" required />
        <button type="submit" class="send-btn">Send Reset Link</button>
      </form>
      <p>Remembered password? <a href="login.php">Login here</a></p>
    </div>
  </div>
</body>

</html>
