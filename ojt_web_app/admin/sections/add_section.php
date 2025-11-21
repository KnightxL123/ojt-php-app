<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

include 'config/DBconfig.php';

$message = '';
$departments = [];

// Get all departments for the dropdown
$result = $conn->query("SELECT id, name FROM departments ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_id = intval($_POST['department_id']);
    $section_name = trim($_POST['section_name']);

    if ($department_id === 0 || $section_name === '') {
        $message = 'Please select a department and enter a section name.';
    } else {
        $stmt = $conn->prepare("INSERT INTO sections (department_id, name) VALUES (?, ?)");
        $stmt->bind_param('is', $department_id, $section_name);
        if ($stmt->execute()) {
            $message = 'Section added successfully.';
        } else {
            $message = 'Error adding section: ' . $conn->error;
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
    <title>Add Section</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>Add Section</h1>
    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label for="department_id" class="form-label">Department</label>
            <select class="form-select" name="department_id" id="department_id" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="section_name" class="form-label">Section Name</label>
            <input type="text" class="form-control" name="section_name" id="section_name" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Section</button>
        <a href="documents.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>

