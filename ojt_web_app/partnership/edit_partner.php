<?php
session_start();
include 'config/DBconfig.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

try {
    $conn = new mysqli("localhost", "root", "", "ojt");
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_program'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $logo_url = trim($_POST['logo_url']) ?: null;
    $department_id = intval($_POST['department_id']);
    $status = trim($_POST['status']);
    
    try {
        if (empty($name)) {
            throw new Exception("Program name cannot be empty");
        }
        
        $stmt = $conn->prepare("UPDATE programs SET name = ?, department_id = ?, logo_url = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sissi", $name, $department_id, $logo_url, $status, $id);
        
        if ($stmt->execute()) {
            $success = "Program updated successfully!";
        } else {
            throw new Exception("Error updating program: " . $stmt->error);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current program data
$program = null;
$departments = [];
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $result = $conn->query("SELECT * FROM programs WHERE id = $id");
    $program = $result->fetch_assoc();
    
    $departments = $conn->query("SELECT * FROM departments ORDER BY name ASC");
}

if (!$program) {
    header('Location: manage.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Program</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 10px;
        }
        .status-active {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #842029;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Edit Program</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="id" value="<?= $program['id'] ?>">
            
            <div class="mb-3">
                <label for="name" class="form-label">Program Name</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?= htmlspecialchars($program['name']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="department_id" class="form-label">Department</label>
                <select class="form-select" id="department_id" name="department_id" required>
                    <?php while ($dept = $departments->fetch_assoc()): ?>
                        <option value="<?= $dept['id'] ?>" <?= $dept['id'] == $program['department_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="logo_url" class="form-label">Logo URL (optional)</label>
                <input type="url" class="form-control" id="logo_url" name="logo_url" 
                       value="<?= htmlspecialchars($program['logo_url']) ?>">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Status</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="status" id="statusActive" 
                           value="active" <?= $program['status'] == 'active' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="statusActive">
                        Active <span class="status-badge status-active">Active</span>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="status" id="statusInactive" 
                           value="inactive" <?= $program['status'] == 'inactive' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="statusInactive">
                        Inactive <span class="status-badge status-inactive">Inactive</span>
                    </label>
                </div>
            </div>
            
            <button type="submit" name="update_program" class="btn btn-primary">Update</button>
            <a href="manage.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>

</html>
