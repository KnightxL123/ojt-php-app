<?php
session_start();
include 'config/DBconfig.php'

$department_id = intval($_POST['department_id']);
$section_id = intval($_POST['section_id']);
$title = $_POST['title'];

$upload_dir = '../uploads';
$section_dir = $upload_dir . "/Section_$section_id";
if (!file_exists($section_dir)) {
    mkdir($section_dir, 0755, true);
}

$file = $_FILES['document'];
$filename = basename($file['name']);
$target_file = "$section_dir/$filename";

if (move_uploaded_file($file['tmp_name'], $target_file)) {
    $stmt = $conn->prepare("INSERT INTO documents (department_id, section_id, title, file_path) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('iiss', $department_id, $section_id, $title, $target_file);
    if ($stmt->execute()) {
        echo "<p>File uploaded and saved successfully. <a href='documents.php?department=$department_id&section=$section_id'>Back</a></p>";
    } else {
        echo "<p>Database error: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Failed to upload file.</p>";
}

