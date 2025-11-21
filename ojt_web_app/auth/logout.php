<?php
session_start();
session_unset();  // Unset all session variables
session_destroy();  // Destroy the session
header("Location: /ojt-management-system/backend/api/auth/login.php");  // Redirect to login page
exit();
?>
