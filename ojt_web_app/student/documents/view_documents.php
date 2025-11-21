<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'OJT');

// Fetch student documents
$students = $conn->query("SELECT d.*, s.student_name FROM documents d JOIN students s ON d.student_id = s.id");

echo "<h1>Documents Overview</h1>";
echo "<table border='1'>";
echo "<tr><th>Student Name</th><th>Certificate of Completion</th><th>Daily Time Record</th><th>Performance Evaluation</th><th>Other Documents</th></tr>";

while ($student = $students->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($student['student_name']) . "</td>";
    
    // Display links to uploaded documents or 'Not Submitted' if no document is uploaded
    foreach (['certificate_of_completion', 'daily_time_record', 'performance_evaluation', 'narrative_report', 'printed_journal', 'company_profile', 'ojt_evaluation_form'] as $doc_field) {
        $file_path = $student[$doc_field];
        if ($file_path != 'Not Submitted') {
            echo "<td><a href='$file_path' target='_blank'>View Document</a></td>";
        } else {
            echo "<td>Not Submitted</td>";
        }
    }
    
    echo "</tr>";
}
echo "</table>";
?>
