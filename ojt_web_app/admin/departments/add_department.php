<?php
session_start();
include __DIR__ . '/../init.php';


if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_name = trim($_POST['department_name']);

    if ($department_name === '') {
        $message = 'Department name is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->bind_param('s', $department_name);

        if ($stmt->execute()) {
            $message = 'Department added successfully.';
        } else {
            $message = 'Error adding department: ' . $conn->error;
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Department</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
    <h1 class="mb-4">Add Department</h1>

    <?php if ($message): ?>
        <div class="alert alert-info">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="add_department.php">
        <div class="mb-3">
            <label for="department_name" class="form-label">Department Name</label>
            <input type="text" class="form-control" id="department_name" name="department_name" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Department</button>
        <a href="documents.php" class="btn btn-secondary">Back to Documents</a>
    </form>
</div>

</body>
</html>



