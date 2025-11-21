<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$dept_id = isset($_GET['department']) ? intval($_GET['department']) : null;
$section_id = isset($_GET['section']) ? intval($_GET['section']) : null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
    <h2>Upload Document</h2>
    <form action="handle_upload.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="department_id" value="<?php echo $dept_id; ?>">
        <input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
        <div class="mb-3">
            <label>Document Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Select File</label>
            <input type="file" name="document" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</body>
</html>
