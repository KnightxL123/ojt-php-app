<?php
session_start();
require 'config/DBconfig.php';

// Validate email
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$role = $_POST['role'] ?? 'adviser';  // default adviser

// Role-based fields
$department_id = null;
$program_id = null;
$section_id = null;

// Coordinator role
if ($role === 'coordinator') {
    $department_id = $_POST['department_id'] ?? null;

// Adviser role
} elseif ($role === 'adviser') {
    $department_id = $_POST['adviser_department_id'] ?? null;
    $program_id = $_POST['program_id'] ?? null;
    $section_id = $_POST['section_id'] ?? null;

// Invalid role (security)
} else {
    header("Location: register.php?error=" . urlencode("Invalid role selected."));
    exit;
}


/* -----------------------------
   BASIC VALIDATION
------------------------------*/
if ($username === '' || $email === '' || $password === '') {
    header("Location: register.php?error=" . urlencode("Please fill all required fields."));
    exit;
}

if (!is_valid_email($email)) {
    header("Location: register.php?error=" . urlencode("Invalid email address."));
    exit;
}

if ($password !== $confirm_password) {
    header("Location: register.php?error=" . urlencode("Passwords do not match."));
    exit;
}

if (strlen($password) < 6) {
    header("Location: register.php?error=" . urlencode("Password must be at least 6 characters."));
    exit;
}

/* -----------------------------
   ROLE VALIDATION
------------------------------*/
if ($role === 'coordinator') {
    if (empty($department_id)) {
        header("Location: register.php?error=" . urlencode("Select a department for coordinator."));
        exit;
    }
}

if ($role === 'adviser') {
    if (empty($department_id) || empty($program_id) || empty($section_id)) {
        header("Location: register.php?error=" . urlencode("Please select department, program, and section."));
        exit;
    }
}

/* -----------------------------
   CHECK IF USERNAME OR EMAIL EXISTS
------------------------------*/
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $email]);

if ($stmt->rowCount() > 0) {
    header("Location: register.php?error=" . urlencode("Username or email is already taken."));
    exit;
}

/* -----------------------------
   VALIDATE DEPARTMENT
------------------------------*/
$stmt = $conn->prepare(
    "SELECT id FROM departments WHERE id = ? AND status = 'active' AND deleted_at IS NULL"
);
$stmt->execute([$department_id]);

if ($stmt->rowCount() === 0) {
    header("Location: register.php?error=" . urlencode("Invalid department selected."));
    exit;
}

/* -----------------------------
   ADVISER: VALIDATE PROGRAM & SECTION
------------------------------*/
if ($role === 'adviser') {

    // Program exists, belongs to department
    $stmt = $conn->prepare(
        "SELECT id FROM programs WHERE id = ? AND department_id = ? AND status = 'active'"
    );
    $stmt->execute([$program_id, $department_id]);

    if ($stmt->rowCount() === 0) {
        header("Location: register.php?error=" . urlencode("Invalid program selected."));
        exit;
    }

    // Section exists, belongs to program
    $stmt = $conn->prepare(
        "SELECT id FROM sections WHERE id = ? AND program_id = ?"
    );
    $stmt->execute([$section_id, $program_id]);

    if ($stmt->rowCount() === 0) {
        header("Location: register.php?error=" . urlencode("Invalid section selected."));
        exit;
    }
}

/* -----------------------------
   REGISTER USER
------------------------------*/
try {

    // HASH PASSWORD
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    if ($role === 'coordinator') {

        // Insert coordinator user
        $stmt = $conn->prepare(
            "INSERT INTO users (username, email, password_hash, role, department_id)
             VALUES (?, ?, ?, 'coordinator', ?)"
        );
        $stmt->execute([$username, $email, $password_hash, $department_id]);

        header("Location: login.php?msg=" . urlencode("Registration successful. You may now log in."));
        exit;
    }

    /* -----------------------------
       REGISTER ADVISER (WITH TRANSACTION)
    ------------------------------*/
    $conn->beginTransaction();

    // Insert user
    $stmt = $conn->prepare(
        "INSERT INTO users (username, email, password_hash, role)
         VALUES (?, ?, ?, 'adviser')"
    );
    $stmt->execute([$username, $email, $password_hash]);
    $user_id = $conn->lastInsertId();

    // Insert adviser (linked to user)
    $stmt = $conn->prepare(
        "INSERT INTO advisers (user_id, name, email, department_id)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$user_id, $username, $email, $department_id]);
    $adviser_id = $conn->lastInsertId();

    // Link adviser to section
    $stmt = $conn->prepare(
        "INSERT INTO section_adviser (section_id, adviser_id)
         VALUES (?, ?)"
    );
    $stmt->execute([$section_id, $adviser_id]);

    $conn->commit();

    header("Location: login.php?msg=" . urlencode("Registration successful. You may now log in."));
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    header("Location: register.php?error=" . urlencode("Error: " . $e->getMessage()));
    exit;
}

?>
