<?php
session_start();
include 'config/DBconfig.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement_id = intval($_POST['announcement_id']);
    $forward_to_id = intval($_POST['forward_to_id']);

    $stmt = $conn->prepare("INSERT INTO announcement_recipients (announcement_id, recipient_id, forwarded_from_id) VALUES (?, ?, ?)");
    $stmt->bind_param('iii', $announcement_id, $forward_to_id, $_SESSION['user_id']);
    $stmt->execute();
    header('Location: user_inbox.php');
    exit;
}
?>

