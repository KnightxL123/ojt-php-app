<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

include 'config/DBconfig.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT * FROM announcements WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$announcement = $res->fetch_assoc();

if ($announcement) {
    // Mark as read
    $conn->query("UPDATE announcements SET is_read=1 WHERE id=$id");
} else {
    $conn->close();
    header('Location: Inbox.php');
    exit;
}

$conn->close();

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Announcement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
    <style>
        .container { max-width: 800px; margin: 30px auto; }
    </style>
</head>
<body>
<div class="container">
    <a href="Inbox.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Back</a>
    <h2><?php echo escape($announcement['title']); ?></h2>
    <p class="text-muted"><?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?></p>
    <hr>
    <p><?php echo nl2br(escape($announcement['message'])); ?></p>
</div>
</body>
</html>

