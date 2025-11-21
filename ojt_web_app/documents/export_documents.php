<?php
$dept_id = isset($_GET['department']) ? intval($_GET['department']) : null;
$section_id = isset($_GET['section']) ? intval($_GET['section']) : null;

if (!$dept_id || !$section_id) {
    die("Invalid request: Missing department or section");
}

$conn = new mysqli('localhost', 'root', '', 'OJT');
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Fetch documents filtered by section and department using JOINs
$sql = "
    SELECT d.id, s.name AS student_name, d.updated_at, d.status
    FROM documents d
    JOIN students s ON d.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.id = ? AND sec.department_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $section_id, $dept_id);
$stmt->execute();
$result = $stmt->get_result();

// Prepare CSV export
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="export_documents.csv"');
$output = fopen('php://output', 'w');

// Output headers
fputcsv($output, ['Document ID', 'Student Name', 'Last Updated', 'Status']);

// Output rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['student_name'],
        $row['updated_at'],
        $row['status']
    ]);
}

fclose($output);
$stmt->close();
$conn->close();
exit;
?>
