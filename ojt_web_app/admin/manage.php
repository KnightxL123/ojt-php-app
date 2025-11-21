<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

include 'config/DBconfig.php';

// Initialize messages
$success_message = '';
$error_message = '';

// Handle Department Status Toggle
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['toggle_dept_status'])) {
    try {
        $dept_id = intval($_GET['id']);
        $current_status = $conn->query("SELECT status FROM departments WHERE id = $dept_id")->fetch_assoc()['status'];
        $new_status = $current_status == 'active' ? 'inactive' : 'active';
        
        $stmt = $conn->prepare("UPDATE departments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $dept_id);
        
        if ($stmt->execute()) {
            $success_message = "Department status updated successfully!";
        } else {
            throw new Exception("Error updating department status: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Program Status Toggle
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['toggle_prog_status'])) {
    try {
        $prog_id = intval($_GET['id']);
        $current_status = $conn->query("SELECT status FROM programs WHERE id = $prog_id")->fetch_assoc()['status'];
        $new_status = $current_status == 'active' ? 'inactive' : 'active';
        
        $stmt = $conn->prepare("UPDATE programs SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $prog_id);
        
        if ($stmt->execute()) {
            $success_message = "Program status updated successfully!";
        } else {
            throw new Exception("Error updating program status: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Department Addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_department'])) {
    try {
        $dept_name = trim($_POST['department_name']);
        
        if (empty($dept_name)) {
            throw new Exception("Department name cannot be empty");
        }
        
        if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $dept_name)) {
            throw new Exception("Invalid department name format");
        }
        
        $stmt = $conn->prepare("INSERT INTO departments (name, status) VALUES (?, 'active')");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $dept_name);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        $success_message = "Department added successfully!";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Program Addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_program'])) {
    try {
        $program_name = trim($_POST['program_name']);
        $logo_url = trim($_POST['logo_url']) ?: null;
        $department_id = intval($_POST['department_id']);

        // Validate inputs
        if (empty($program_name)) {
            throw new Exception("Program name cannot be empty");
        }
        
        if (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $program_name)) {
            throw new Exception("Invalid program name format");
        }
        
        if ($department_id <= 0) {
            throw new Exception("Invalid department");
        }

        // Check if program already exists in this department
        $check_stmt = $conn->prepare("SELECT id FROM programs WHERE name = ? AND department_id = ?");
        $check_stmt->bind_param("si", $program_name, $department_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            throw new Exception("This program already exists in the selected department");
        }
        $check_stmt->close();

        // Insert new program
        $stmt = $conn->prepare("INSERT INTO programs (name, department_id, logo_url, status) VALUES (?, ?, ?, 'active')");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sis", $program_name, $department_id, $logo_url);
        
        if ($stmt->execute()) {
            $success_message = "Program added successfully!";
        } else {
            throw new Exception("Error adding program: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Department Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_department'])) {
    try {
        $dept_id = intval($_POST['dept_id']);
        
        // First check if department has any programs
        $check_stmt = $conn->prepare("SELECT COUNT(*) as program_count FROM programs WHERE department_id = ?");
        $check_stmt->bind_param("i", $dept_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($result['program_count'] > 0) {
            throw new Exception("Cannot delete department with existing programs. Please delete or move the programs first.");
        }
        
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->bind_param("i", $dept_id);
        
        if ($stmt->execute()) {
            $success_message = "Department deleted successfully!";
        } else {
            throw new Exception("Error deleting department: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Program Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_program'])) {
    try {
        $prog_id = intval($_POST['prog_id']);
        
        // Check if program has any students
        $check_stmt = $conn->prepare("SELECT COUNT(*) as student_count FROM student_programs WHERE program_id = ?");
        $check_stmt->bind_param("i", $prog_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($result['student_count'] > 0) {
            throw new Exception("Cannot delete program with enrolled students. Please reassign or remove the students first.");
        }
        
        $stmt = $conn->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->bind_param("i", $prog_id);
        
        if ($stmt->execute()) {
            $success_message = "Program deleted successfully!";
        } else {
            throw new Exception("Error deleting program: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch active departments with error handling
try {
    $departments = $conn->query("SELECT * FROM departments ORDER BY name ASC");
    if (!$departments) {
        throw new Exception("Failed to fetch departments: " . $conn->error);
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $departments = []; // Empty array to prevent errors in view
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Departments</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
         body {
            margin: 0;
            font-family: Arial, sans-serif;
        }
        .header {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 30px;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
        }
        .header-logo {
            height: 80px;
        }
        .search-bar input {
            width: 300px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .header-icons a {
            font-size: 24px;
            color: #333;
            margin-left: 15px;
            text-decoration: none;
        }
        .header-icons a:hover {
            color: #28a745;
        }
        .main-container {
            display: flex;
            min-height: calc(100vh - 110px);
        }
        .sidebar {
            width: 250px;
            background: #0da80d;
            color: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            margin: 15px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            padding: 20px;
            text-align: center;
            font-size: 18px;
        }
        .sidebar ul li a {
            text-decoration: none;
            color: white;
            display: block;
            font-weight: bold;
        }
        .sidebar ul li:hover {
            background: #2c3e50;
            border-radius: 10px;
            cursor: pointer;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
        }
        .card-header {
            transition: background-color 0.3s ease;
        }
        .card-header:hover {
            background-color: #198754 !important;
        }
        .no-programs {
            color: #6c757d;
            font-style: italic;
        }
        .program-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
        }
        .program-actions {
            display: flex;
            gap: 5px;
        }
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

<header class="header">
    <img src="/ojt-management-system/backend/assets/images/PLSP.png" alt="Logo" class="header-logo">
    <div class="search-bar">
        <input type="text" placeholder="Search...">
    </div>
    <div class="header-icons">
        <a href="#"><i class="bi bi-bell"></i></a>
        <a href="../student/profile.php"><i class="bi bi-person-circle"></i></a>
    </div>
</header>

<div class="main-container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <ul>
            <li><a href="admin_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="../documents/documents.php"><i class="bi bi-folder"></i> Documents</a></li>
            <li><a href="monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li>
            <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
            <li><a href="manage.php" class="active"><i class="bi bi-diagram-3"></i> Department</a></li>
            <li><a href="../announcements/admin_inbox.php"><i class="bi bi-envelope-paper"></i> Sent Announcements</a></li>
            <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
        </ul>
    </nav>

    <div class="content p-4" style="flex: 1;">
        <h3 class="mb-4">Manage Departments</h3>

        <!-- Status Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Add Department Form -->
        <form method="POST" class="d-flex gap-2 mb-4">
            <input type="text" name="department_name" class="form-control w-25" 
                   placeholder="Enter department name" required
                   pattern="[a-zA-Z0-9\s\-]+" title="Only letters, numbers, spaces and hyphens allowed">
            <button type="submit" name="add_department" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Add Department
            </button>
        </form>

        <!-- Department List -->
        <?php if ($departments && $departments->num_rows > 0): ?>
            <?php while ($dept = $departments->fetch_assoc()): ?>
                <div class="card mb-3">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><?= htmlspecialchars($dept['name']) ?></h5>
                            <span class="status-badge status-<?= $dept['status'] ?>">
                                <?= ucfirst($dept['status']) ?>
                            </span>
                        </div>
                        <div>
                            <a href="edit_department.php?id=<?= $dept['id'] ?>" class="btn btn-warning btn-sm me-2">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <a href="?toggle_dept_status&id=<?= $dept['id'] ?>" 
                               class="btn btn-sm <?= $dept['status'] == 'active' ? 'btn-secondary' : 'btn-info' ?> me-2">
                                <i class="bi bi-power"></i> <?= $dept['status'] == 'active' ? 'Deactivate' : 'Activate' ?>
                            </a>
                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" 
                                    data-bs-target="#deleteDeptModal" 
                                    data-dept-id="<?= $dept['id'] ?>"
                                    data-dept-name="<?= htmlspecialchars($dept['name']) ?>">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <strong>Programs:</strong>
                        <ul class="mt-2">
                            <?php
                            $dept_id = $dept['id'];
                            $programs_result = $conn->query("SELECT * FROM programs WHERE department_id = $dept_id ORDER BY name ASC");
                            
                            if ($programs_result && $programs_result->num_rows > 0):
                                while ($prog = $programs_result->fetch_assoc()):
                            ?>
                                <li class="program-item">
                                    <div>
                                        <span><?= htmlspecialchars($prog['name']) ?></span>
                                        <span class="status-badge status-<?= $prog['status'] ?> ms-2">
                                            <?= ucfirst($prog['status']) ?>
                                        </span>
                                    </div>
                                    <div class="program-actions">
                                        <a href="edit_program.php?id=<?= $prog['id'] ?>" class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?toggle_prog_status&id=<?= $prog['id'] ?>" 
                                           class="btn btn-sm btn-outline-<?= $prog['status'] == 'active' ? 'secondary' : 'info' ?>">
                                            <i class="bi bi-power"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteProgModal"
                                                data-prog-id="<?= $prog['id'] ?>"
                                                data-prog-name="<?= htmlspecialchars($prog['name']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </li>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <li class="no-programs">No programs listed.</li>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- Add Program Button -->
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                    data-bs-target="#addProgramModal" 
                                    data-dept-id="<?= $dept['id'] ?>"
                                    data-dept-name="<?= htmlspecialchars($dept['name']) ?>">
                                <i class="bi bi-plus"></i> Add Program
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info">
                No departments found. Please add a department to get started.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1" aria-labelledby="addProgramModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProgramModalLabel">Add New Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage.php">
                <input type="hidden" name="department_id" id="modalDeptId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="programName" class="form-label">Program Name</label>
                        <input type="text" class="form-control" id="programName" name="program_name" required
                               pattern="[a-zA-Z0-9\s\-]+" title="Only letters, numbers, spaces and hyphens allowed">
                    </div>
                    <div class="mb-3">
                        <label for="programLogo" class="form-label">Logo URL (optional)</label>
                        <input type="url" class="form-control" id="programLogo" name="logo_url"
                               placeholder="https://example.com/logo.png">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_program" class="btn btn-primary">Save Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Department Modal -->
<div class="modal fade" id="deleteDeptModal" tabindex="-1" aria-labelledby="deleteDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteDeptModalLabel">Confirm Department Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage.php">
                <input type="hidden" name="dept_id" id="deleteDeptId">
                <div class="modal-body">
                    <p>Are you sure you want to delete the department "<span id="deleteDeptName"></span>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_department" class="btn btn-danger">Delete Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Program Modal -->
<div class="modal fade" id="deleteProgModal" tabindex="-1" aria-labelledby="deleteProgModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteProgModalLabel">Confirm Program Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage.php">
                <input type="hidden" name="prog_id" id="deleteProgId">
                <div class="modal-body">
                    <p>Are you sure you want to delete the program "<span id="deleteProgName"></span>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_program" class="btn btn-danger">Delete Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize modals
    document.addEventListener('DOMContentLoaded', function() {
        // Add Program Modal
        var addProgramModal = document.getElementById('addProgramModal');
        if (addProgramModal) {
            addProgramModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var deptId = button.getAttribute('data-dept-id');
                var deptName = button.getAttribute('data-dept-name');
                
                var modalTitle = addProgramModal.querySelector('.modal-title');
                modalTitle.textContent = 'Add Program to ' + deptName;
                
                var modalDeptId = addProgramModal.querySelector('#modalDeptId');
                modalDeptId.value = deptId;
            });
        }

        // Delete Department Modal
        var deleteDeptModal = document.getElementById('deleteDeptModal');
        if (deleteDeptModal) {
            deleteDeptModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var deptId = button.getAttribute('data-dept-id');
                var deptName = button.getAttribute('data-dept-name');
                
                document.getElementById('deleteDeptId').value = deptId;
                document.getElementById('deleteDeptName').textContent = deptName;
            });
        }

        // Delete Program Modal
        var deleteProgModal = document.getElementById('deleteProgModal');
        if (deleteProgModal) {
            deleteProgModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var progId = button.getAttribute('data-prog-id');
                var progName = button.getAttribute('data-prog-name');
                
                document.getElementById('deleteProgId').value = progId;
                document.getElementById('deleteProgName').textContent = progName;
            });
        }
    });
</script>
</body>
</html>

<?php
// Close database connection
$conn->close();

?>
