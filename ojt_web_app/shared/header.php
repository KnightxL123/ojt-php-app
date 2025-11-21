<?php
include 'config/DBconfig.php';

$res = $conn->prepare("SELECT COUNT(*) AS unread FROM announcement_recipients WHERE recipient_id = ? AND is_read = 0");
$res->bind_param('i', $_SESSION['user_id']);
$res->execute();
$res->bind_result($unread_count);
$res->fetch();
$res->close();
?>
<a href="user_inbox.php">
    Inbox 
    <?php if ($unread_count > 0): ?>
        <span style="background: red; color: white; padding: 2px 6px; border-radius: 50%;"><?php echo $unread_count; ?></span>
    <?php endif; ?>
</a>

