<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$host = 'localhost';
$dbname = 'OJT';
$dbuser = 'root';
$dbpass = '';

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$message = '';
$sections = [];

// Get all sections for the dropdown
$result = $conn->query("SELECT s.id, CONCAT(d.name, ' - ', s.name) AS section_full
                        FROM sections s
                        JOIN departments d ON s.department_id = d.id
                        ORDER BY d.name, s.name");
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section_id = intval($_POST['section_id']);
    $student_name = trim($_POST['student_name']);

    if ($section_id === 0 || $student_name === '') {
        $message = 'Please select a section and enter the student name.';
    } else {
        $stmt = $conn->prepare("INSERT INTO students (section_id, name) VALUES (?, ?)");
        $stmt->bind_param('is', $section_id, $student_name);
        if ($stmt->execute()) {
            $message = 'Student added successfully.';
        } else {
            $message = 'Error adding student: ' . $conn->error;
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
    <title>Add Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>Add Student</h1>
    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="mb-3">
            <label for="section_id" class="form-label">Section</label>
            <select class="form-select" name="section_id" id="section_id" required>
                <option value="">Select Section</option>
                <?php foreach ($sections as $sec): ?>
                    <option value="<?php echo $sec['id']; ?>"><?php echo htmlspecialchars($sec['section_full']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="student_name" class="form-label">Student Name</label>
            <input type="text" class="form-control" name="student_name" id="student_name" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Student</button>
        <a href="documents.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
