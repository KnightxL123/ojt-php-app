<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: ../../auth/login.php?error=' . urlencode("Please log in to access the section management."));
    exit;
}

// Only allow coordinators to access this page
if ($_SESSION['role'] !== 'coordinator') {
    header('Location: ../../auth/login.php?error=' . urlencode("Unauthorized access. Coordinator role required."));
    exit;
}

include 'config/DBconfig.php'
// Get coordinator's department
$coordinator_id = $_SESSION['user_id'] ?? null;
$department_id = null;
$department_name = '';

if ($coordinator_id) {
    $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $coordinator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $department_id = $row['department_id'];
    }
    $stmt->close();

    if ($department_id) {
        $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $department_name = $row['name'];
        }
        $stmt->close();
    }
}

if (!$department_id) {
    die("Error: No department assigned to this coordinator.");
}

// Handle Excel import
$import_success = false;
$import_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == UPLOAD_ERR_OK) {
    try {
        // Check if PhpSpreadsheet is available
        $phpspreadsheet_available = @class_exists('PhpOffice\PhpSpreadsheet\IOFactory');
        
        if (!$phpspreadsheet_available) {
            // Fallback to manual CSV parsing
            $file = $_FILES['excel_file']['tmp_name'];
            $section_name = $_POST['section_name'] ?? 'New Section';
            $program_id = $_POST['program_id'] ?? null;
            
            if (empty($section_name) || empty($program_id)) {
                throw new Exception("Section name and program are required.");
            }
            
            // Start transaction
            $conn->begin_transaction();
            
            // Create section
            $stmt = $conn->prepare("INSERT INTO sections (name, department_id, program_id) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sii", $section_name, $department_id, $program_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create section: " . $stmt->error);
            }
            
            $section_id = $conn->insert_id;
            $stmt->close();
            
            // Parse CSV file
            $handle = fopen($file, 'r');
            if (!$handle) {
                throw new Exception("Could not open uploaded file.");
            }
            
            // Skip header row
            $header = fgetcsv($handle);
            
            $imported_count = 0;
            $student_stmt = $conn->prepare("INSERT INTO students (student_id, name, section_id, gender, email) VALUES (?, ?, ?, ?, ?)");
            if (!$student_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) >= 4) {
                    $student_id = trim($row[0]);
                    $email = trim($row[1]);
                    $name = trim($row[2]);
                    $gender = trim($row[3]);
                    
                    // Validate required fields
                    if (!empty($student_id) && !empty($name)) {
                        // Validate gender
                        $gender = in_array(strtolower($gender), ['male', 'female']) ? ucfirst(strtolower($gender)) : 'Male';
                        
                        $student_stmt->bind_param("ssiss", $student_id, $name, $section_id, $gender, $email);
                        if ($student_stmt->execute()) {
                            $imported_count++;
                        }
                    }
                }
            }
            
            fclose($handle);
            $student_stmt->close();
            $conn->commit();
            
            $import_success = true;
            $import_message = "Successfully created section '$section_name' and imported $imported_count students from CSV file";
            
        } else {
            // Use PhpSpreadsheet for Excel files
            require_once __DIR__ . '/../../../vendor/autoload.php';
            
            $file = $_FILES['excel_file']['tmp_name'];
            $section_name = $_POST['section_name'] ?? 'New Section';
            $program_id = $_POST['program_id'] ?? null;
            
            if (empty($section_name) || empty($program_id)) {
                throw new Exception("Section name and program are required.");
            }
            
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Remove header row
            array_shift($rows);
            
            // Start transaction
            $conn->begin_transaction();
            
            // Create section
            $stmt = $conn->prepare("INSERT INTO sections (name, department_id, program_id) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sii", $section_name, $department_id, $program_id);
            $stmt->execute();
            $section_id = $conn->insert_id;
            $stmt->close();
            
            // Prepare student insert statement
            $stmt = $conn->prepare("INSERT INTO students (student_id, name, section_id, gender, email) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $imported_count = 0;
            foreach ($rows as $row) {
                if (count($row) >= 4) {
                    $student_id = trim($row[0] ?? '');
                    $email = trim($row[1] ?? '');
                    $name = trim($row[2] ?? '');
                    $gender = trim($row[3] ?? '');
                    
                    // Validate required fields
                    if (!empty($student_id) && !empty($name)) {
                        // Validate gender
                        $gender = in_array(strtolower($gender), ['male', 'female']) ? ucfirst(strtolower($gender)) : 'Male';
                        
                        $stmt->bind_param("ssiss", $student_id, $name, $section_id, $gender, $email);
                        if ($stmt->execute()) {
                            $imported_count++;
                        }
                    }
                }
            }
            
            $stmt->close();
            $conn->commit();
            
            $import_success = true;
            $import_message = "Successfully created section '$section_name' and imported $imported_count students from Excel file";
        }
        
    } catch (Exception $e) {
        if (isset($conn) && $conn) {
            $conn->rollback();
        }
        $import_success = false;
        $import_message = "Error importing file: " . $e->getMessage();
    }
}

// Handle template download
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_template.csv"');
    header('Pragma: no-cache');
    
    $output = fopen('php://output', 'w');
    
    // Add header
    fputcsv($output, ['student_id', 'email', 'name', 'gender']);
    
    // Add sample data
    fputcsv($output, ['23-23001', 'student1@email.com', 'Juan Dela Cruz', 'Male']);
    fputcsv($output, ['23-23002', 'student2@email.com', 'Maria Santos', 'Female']);
    fputcsv($output, ['23-23003', 'student3@email.com', 'John Smith', 'Male']);
    
    fclose($output);
    exit;
}

// Get programs in the coordinator's department
$programs = [];
$stmt = $conn->prepare("SELECT id, name FROM programs WHERE department_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $programs[] = $row;
    }
    $stmt->close();
}

// Get existing sections
$sections = [];
$stmt = $conn->prepare("
    SELECT s.id, s.name, p.name as program_name, COUNT(st.id) as student_count 
    FROM sections s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN students st ON s.id = st.section_id
    WHERE s.department_id = ?
    GROUP BY s.id, s.name, p.name
    ORDER BY s.name
");
if ($stmt) {
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
    $stmt->close();
}

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo sanitize($department_name); ?> Section Management</title>
    <link rel="stylesheet" href="../../assets/css/dash.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        .section-card {
            border-left: 4px solid #36A2EB;
            margin-bottom: 15px;
            transition: transform 0.3s;
        }
        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .import-area {
            border: 2px dashed #ccc;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .import-area:hover {
            border-color: #36A2EB;
            background-color: #f8f9fa;
        }
        .import-area.dragover {
            border-color: #36A2EB;
            background-color: #e3f2fd;
        }
        .template-info {
            background: #e7f3ff;
            border-left: 4px solid #36A2EB;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<header class="header">
    <img src="../../assets/images/PLSP.png" alt="Logo" class="header-logo">
    <div class="header-title">
        <h2><?php echo sanitize($department_name); ?> Department</h2>
        <p>Section Management</p>
    </div>
    <div class="header-icons">
        <a href="#"><i class="bi bi-bell"></i></a>
        <a href="profile.php"><i class="bi bi-person-circle"></i></a>
    </div>
</header>
<div class="main-container">
    <nav class="sidebar">
        <ul>
            <li><a href="coordinator_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="coordinator_announcement.php"><i class="bi bi-megaphone"></i> Announcement</a></li>
            <li><a href="coordinator_monitor.php"><i class="bi bi-clipboard-data"></i> Monitor</a></li>
            <li><a href="coordinator_documents.php"><i class="bi bi-folder"></i> Documents</a></li>
            <li><a href="coordinator_manages.php" class="active"><i class="bi bi-people"></i> Manage Section</a></li>
            <li><a href="coordinator_partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
            <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
        </ul>
    </nav>

    <main class="dashboard-content">
        <h1>Section Management</h1>
        
        <?php if (!empty($import_message)): ?>
            <div class="alert alert-<?php echo $import_success ? 'success' : 'danger'; ?>">
                <?php echo sanitize($import_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Add New Section Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3>Add New Section</h3>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" id="sectionForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="section_name" class="form-label">Section Name</label>
                            <input type="text" class="form-control" id="section_name" name="section_name" required 
                                   placeholder="e.g., 4A, 4B, 3C">
                        </div>
                        <div class="col-md-6">
                            <label for="program_id" class="form-label">Program</label>
                            <select class="form-select" id="program_id" name="program_id" required>
                                <option value="">Select Program</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?php echo $program['id']; ?>"><?php echo sanitize($program['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Template Download -->
                    <div class="template-info mb-4">
                        <h5><i class="bi bi-info-circle"></i> Import Students from Excel/CSV</h5>
                        <p class="mb-2">Download the template file and fill it with your student data:</p>
                        <a href="?download_template=1" class="btn btn-success btn-sm">
                            <i class="bi bi-download"></i> Download Template
                        </a>
                        <div class="mt-2">
                            <small class="text-muted">
                                <strong>Required columns:</strong> student_id, email, name, gender<br>
                                <strong>Gender values:</strong> Male or Female (defaults to Male if invalid)
                            </small>
                        </div>
                    </div>
                    
                    <!-- File Upload -->
                    <div class="mb-3">
                        <label class="form-label">Upload Student Master List</label>
                        <div class="import-area" id="importArea">
                            <i class="bi bi-cloud-arrow-up" style="font-size: 3rem;"></i>
                            <p class="mt-2 mb-1">Click to select or drag & drop Excel/CSV file</p>
                            <p class="text-muted small mb-0">Supports .xlsx, .xls, .csv files</p>
                            <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" class="d-none">
                        </div>
                        <div id="fileName" class="text-muted small mt-2"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Create Section & Import Students
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Existing Sections -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Existing Sections</h3>
            </div>
            <div class="card-body">
                <?php if (empty($sections)): ?>
                    <div class="alert alert-info">No sections found in your department.</div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($sections as $section): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card section-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo sanitize($section['name']); ?></h5>
                                        <p class="card-text">
                                            <strong>Program:</strong> <?php echo sanitize($section['program_name'] ?? 'N/A'); ?><br>
                                            <strong>Students:</strong> <?php echo $section['student_count']; ?>
                                        </p>
                                        <div class="d-flex gap-2">
                                            <a href="coordinator_viewsection.php?id=<?php echo $section['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="coordinator_edit_section.php?id=<?php echo $section['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    // Handle file selection display
    document.getElementById('importArea').addEventListener('click', function() {
        document.getElementById('excel_file').click();
    });
    
    document.getElementById('excel_file').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'No file selected';
        document.getElementById('fileName').textContent = `Selected file: ${fileName}`;
    });
    
    // Handle drag and drop
    const importArea = document.getElementById('importArea');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        importArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        importArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        importArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        importArea.classList.add('dragover');
    }
    
    function unhighlight() {
        importArea.classList.remove('dragover');
    }
    
    importArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        document.getElementById('excel_file').files = files;
        document.getElementById('fileName').textContent = `Selected file: ${files[0].name}`;
    }
    
    // Form validation
    document.getElementById('sectionForm').addEventListener('submit', function(e) {
        const sectionName = document.getElementById('section_name').value.trim();
        const programId = document.getElementById('program_id').value;
        const fileInput = document.getElementById('excel_file');
        
        if (!sectionName) {
            e.preventDefault();
            alert('Please enter a section name.');
            return;
        }
        
        if (!programId) {
            e.preventDefault();
            alert('Please select a program.');
            return;
        }
        
        if (!fileInput.files.length) {
            e.preventDefault();
            alert('Please select an Excel or CSV file to import students.');
            return;
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();

?>
