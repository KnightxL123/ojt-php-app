<?php
$conn = new mysqli("localhost", "root", "", "your_database_name");
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM departments WHERE id = $id");
}
header("Location: manage.php");
exit;
