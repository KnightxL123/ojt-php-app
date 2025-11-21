<?php
session_start();
include 'config/DBconfig.php';
$id = intval($_GET['id']);
$stmt = $conn->prepare("UPDATE announcement_recipients SET is_read = 1 WHERE announcement_id = ? AND recipient_id = ?");
$stmt->bind_param('ii', $id, $_SESSION['user_id']);
$stmt->execute();
header('Location: user_inbox.php');
exit;
?>

