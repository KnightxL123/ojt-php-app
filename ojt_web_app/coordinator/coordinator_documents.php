<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: ../../auth/login.php?error=' . urlencode("Please log in to access the documents."));
    exit;
}

// Only allow coordinators to access this page
if ($_SESSION['role'] !== 'coordinator') {
    header('Location: ../../auth/login.php?error=' . urlencode("Unauthorized access. Coordinator role required."));
    exit;
}

include 'config/DBconfig.php'
// Function to get status class
function getStatusClass($status) {
    if (strpos($status, 'Approved') !== false) return 'status-approved';
    if (strpos($status, 'Pending') !== false) return 'status-pending';
    if (strpos($status, 'Rejected') !== false) return 'status-rejected';
    return 'status-not-submitted';
}

// Function to display status
function displayStatus($status) {
    return $status ? $status : 'Not Submitted';
}

try {
    $conn = new mysqli($host, $dbuser, $dbpass, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");

    // Get coordinator's department
    $coordinator_id = $_SESSION['user_id'] ?? null;
    $department_id = null;
    $department_name = '';

    if ($coordinator_id) {
        $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
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
                throw new Exception("Prepare failed: " . $conn->error);
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

    // Get parameters from URL
    $program_id = isset($_GET['program']) ? intval($_GET['program']) : null;
    $section_id = isset($_GET['section']) ? intval($_GET['section']) : null;

    // Initialize breadcrumbs
    $breadcrumbs = ['Documents' => 'coordinator_documents.php'];

    // --- Level 1: Show all programs in coordinator's department ---
    if (!$program_id && !$section_id) {
        try {
            $breadcrumbs[$department_name] = "coordinator_documents.php";

            // Get programs with section counts
            $sql = "SELECT p.id, p.name, COUNT(s.id) as section_count 
                    FROM programs p 
                    LEFT JOIN sections s ON p.id = s.program_id 
                    WHERE p.department_id = ? 
                    GROUP BY p.id 
                    ORDER BY p.name";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('i', $department_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $programs = [];
            while ($row = $result->fetch_assoc()) {
                $programs[] = $row;
            }
            $stmt->close();

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $conn->close();
            header('Location: coordinator_documents.php');
            exit;
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>Documents - <?php echo htmlspecialchars($department_name); ?></title>
            <link rel="stylesheet" href="../../assets/css/dash.css" />
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
            <style>
                .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
                .folder { display: flex; flex-direction: column; align-items: center; text-decoration: none; 
                        background: #f8f9fa; padding: 20px; border-radius: 8px; color: #333; 
                        transition: transform 0.2s, box-shadow 0.2s; height: 100%; }
                .folder:hover { transform: translateY(-5px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
                .folder i { font-size: 3rem; margin-bottom: 0.5rem; color: #007bff; }
                .folder .count { font-size: 0.8rem; color: #6c757d; }
                nav.breadcrumb { background: none; padding-left: 0; }
            </style>
        </head>
        <body>
        <header class="header">
            <img src="../../assets/images/PLSP.png" alt="Logo" class="header-logo" />
            <div class="header-title">
                <h2><?php echo htmlspecialchars($department_name); ?> Department</h2>
                <p>Coordinator Documents</p>
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
                    <li><a href="coordinator_documents.php" class="active"><i class="bi bi-folder"></i> Documents</a></li>
                    <li><a href="coordinator_managesection.php"><i class="bi bi-people"></i> Manage Section</a></li>
                    <li><a href="coordinator_partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
                </ul>
            </nav>

            <div class="dashboard-content">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <?php foreach($breadcrumbs as $name => $link): ?>
                            <?php if ($link === end($breadcrumbs)) : ?>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($name); ?></li>
                            <?php else: ?>
                                <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($name); ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Programs in <?php echo htmlspecialchars($department_name); ?></h1>
                </div>

                <div class="folder-grid">
                    <?php foreach ($programs as $program): ?>
                        <a href="coordinator_documents.php?program=<?php echo $program['id']; ?>" class="folder">
                            <i class="bi bi-book"></i>
                            <span><?php echo htmlspecialchars($program['name']); ?></span>
                            <span class="count"><?php echo $program['section_count']; ?> section(s)</span>
                        </a>
                    <?php endforeach; ?>
                    <?php if(empty($programs)): ?>
                        <div class="alert alert-info w-100">No programs found in this department.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </body>
        </html>
        <?php
        $conn->close();
        exit;
    }

    // --- Level 2: Show sections in a program ---
    if ($program_id && !$section_id) {
        try {
            // Get program info
            $sql = "SELECT p.name AS program_name 
                    FROM programs p 
                    WHERE p.id = ? AND p.department_id = ?";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('ii', $program_id, $department_id);
            $stmt->execute();
            $stmt->bind_result($program_name);
            if (!$stmt->fetch()) {
                throw new Exception("Program not found in your department");
            }
            $stmt->close();

            $breadcrumbs[$department_name] = "coordinator_documents.php";
            $breadcrumbs[$program_name] = "coordinator_documents.php?program=$program_id";

            // Get sections with student counts
            $sql = "SELECT s.id, s.name, COUNT(st.id) as student_count 
                    FROM sections s 
                    LEFT JOIN students st ON s.id = st.section_id 
                    WHERE s.program_id = ? 
                    GROUP BY s.id 
                    ORDER BY s.name";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('i', $program_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $sections = [];
            while ($row = $result->fetch_assoc()) {
                $sections[] = $row;
            }
            $stmt->close();

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $conn->close();
            header("Location: coordinator_documents.php");
            exit;
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>Documents - <?php echo htmlspecialchars($program_name); ?></title>
            <link rel="stylesheet" href="../../assets/css/dash.css" />
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
            <style>
                .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
                .folder { display: flex; flex-direction: column; align-items: center; text-decoration: none; 
                        background: #f8f9fa; padding: 20px; border-radius: 8px; color: #333; 
                        transition: transform 0.2s, box-shadow 0.2s; height: 100%; }
                .folder:hover { transform: translateY(-5px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
                .folder i { font-size: 3rem; margin-bottom: 0.5rem; color: #007bff; }
                .folder .count { font-size: 0.8rem; color: #6c757d; }
                nav.breadcrumb { background: none; padding-left: 0; }
            </style>
        </head>
        <body>
        <header class="header">
            <img src="../../assets/images/PLSP.png" alt="Logo" class="header-logo" />
            <div class="header-title">
                <h2><?php echo htmlspecialchars($department_name); ?> Department</h2>
                <p>Coordinator Documents</p>
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
                    <li><a href="coordinator_documents.php" class="active"><i class="bi bi-folder"></i> Documents</a></li>
                    <li><a href="coordinator_managesection.php"><i class="bi bi-people"></i> Manage Section</a></li>
                    <li><a href="coordinator_partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
                </ul>
            </nav>

            <div class="dashboard-content">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <?php foreach($breadcrumbs as $name => $link): ?>
                            <?php if ($link === end($breadcrumbs)) : ?>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($name); ?></li>
                            <?php else: ?>
                                <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($name); ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Sections in <?php echo htmlspecialchars($program_name); ?></h1>
                </div>

                <div class="folder-grid">
                    <?php foreach ($sections as $section): ?>
                        <a href="coordinator_documents.php?program=<?php echo $program_id; ?>&section=<?php echo $section['id']; ?>" class="folder">
                            <i class="bi bi-people-fill"></i>
                            <span><?php echo htmlspecialchars($section['name']); ?></span>
                            <span class="count"><?php echo $section['student_count']; ?> student(s)</span>
                        </a>
                    <?php endforeach; ?>
                    <?php if(empty($sections)): ?>
                        <div class="alert alert-info w-100">No sections found in this program.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </body>
        </html>
        <?php
        $conn->close();
        exit;
    }

    // --- Level 3: Show students in a section with document status ---
    if ($program_id && $section_id) {
        try {
            // Get program and section info
            $sql = "SELECT p.name AS program_name, s.name AS section_name 
                    FROM programs p 
                    JOIN sections s ON p.id = s.program_id 
                    WHERE p.id = ? AND s.id = ? AND p.department_id = ?";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('iii', $program_id, $section_id, $department_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if (!$result->num_rows) {
                throw new Exception("Section not found in your department");
            }
            $section_info = $result->fetch_assoc();
            $stmt->close();

            $breadcrumbs[$department_name] = "coordinator_documents.php";
            $breadcrumbs[$section_info['program_name']] = "coordinator_documents.php?program=$program_id";
            $breadcrumbs[$section_info['section_name']] = "coordinator_documents.php?program=$program_id&section=$section_id";

            // Get students with document status
            $sql = "SELECT 
                    st.id, 
                    st.student_id,
                    st.name AS student_name,
                    doc.id as doc_id,
                    doc.certificate_of_completion,
                    doc.daily_time_record,
                    doc.performance_evaluation,
                    doc.narrative_report,
                    doc.printed_journal,
                    doc.company_profile,
                    doc.ojt_evaluation_form
                    FROM students st
                    LEFT JOIN documents doc ON st.id = doc.student_id
                    WHERE st.section_id = ?
                    ORDER BY st.name";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('i', $section_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $students = [];
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            $stmt->close();

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $conn->close();
            header("Location: coordinator_documents.php?program=$program_id");
            exit;
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>Documents - <?php echo htmlspecialchars($section_info['section_name']); ?></title>
            <link rel="stylesheet" href="../../assets/css/dash.css" />
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
            <style>
                .status-approved { color: #28a745; font-weight: bold; }
                .status-pending { color: #ffc107; font-weight: bold; }
                .status-rejected { color: #dc3545; font-weight: bold; }
                .status-not-submitted { color: #6c757d; }
                .table-responsive { overflow-x: auto; }
                .table th { white-space: nowrap; vertical-align: middle; }
                .table td { vertical-align: middle; }
                .breadcrumb { background: none; padding-left: 0; }
                .section-header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .action-buttons .btn { margin: 2px; }
                .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
                .btn-group-sm > .btn { padding: 0.25rem 0.5rem; font-size: 0.875rem; border-radius: 0.2rem; }
            </style>
        </head>
        <body>
        <header class="header">
            <img src="../../assets/images/PLSP.png" alt="Logo" class="header-logo" />
            <div class="header-title">
                <h2><?php echo htmlspecialchars($department_name); ?> Department</h2>
                <p>Coordinator Documents</p>
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
                    <li><a href="coordinator_documents.php" class="active"><i class="bi bi-folder"></i> Documents</a></li>
                    <li><a href="coordinator_managesection.php"><i class="bi bi-people"></i> Manage Section</a></li>
                    <li><a href="coordinator_partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="../shared/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
                </ul>
            </nav>

            <div class="dashboard-content">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <?php foreach ($breadcrumbs as $name => $link): ?>
                            <?php if ($link === end($breadcrumbs)) : ?>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($name); ?></li>
                            <?php else: ?>
                                <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($name); ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <div class="section-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1><?php echo htmlspecialchars($section_info['section_name']); ?></h1>
                            <p class="mb-1"><strong>Program:</strong> <?php echo htmlspecialchars($section_info['program_name']); ?></p>
                            <p class="mb-0"><strong>Department:</strong> <?php echo htmlspecialchars($department_name); ?></p>
                        </div>
                        <div>
                            <span class="badge bg-primary"><?php echo count($students); ?> students</span>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Certificate</th>
                                <th>DTR</th>
                                <th>Performance</th>
                                <th>Narrative</th>
                                <th>Journal</th>
                                <th>Company</th>
                                <th>Evaluation</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                    <td class="<?php echo getStatusClass(displayStatus($student['certificate_of_completion'])); ?>">
                                        <?php echo htmlspecialchars(displayStatus($student['certificate_of_completion'])); ?>
                                    </td>
                                    <td class="<?php echo getStatusClass(displayStatus($student['daily_time_record'])); ?>">
                                        <?php echo htmlspecialchars(displayStatus($student['daily_time_record'])); ?>
                                    </td>
                                    <td class="<?php echo getStatusClass(displayStatus($student['performance_evaluation'])); ?>">
                                        <?php echo htmlspecialchars(displayStatus($student['performance_evaluation'])); ?>
                                    </td>
                                    <td class="<?php echo getStatusClass(displayStatus($student['narrative_report'])); ?>">
                                        <?php echo htmlspecialchars(displayStatus($student['narrative_report'])); ?>
                                    </td>
                                    <td class="<?php echo getStatusClass(displayStatus($student['printed_journal'])); ?>">
                                        <?php echo htmlspecialchars(displayStatus($student['printed_journal'])); ?>
                                    </td>
                                    <td class="<?php echo getStatusClass(displayStatus($student['company_profile'])); ?>">
                                        <?php echo htmlspecialchars(displayStatus($student['company_profile'])); ?>
                                    </td>
                                    <td class="<?php echo getStatusClass(displayStatus($student['ojt_evaluation_form'])); ?>">
                                        <?php echo htmlspecialchars(displayStatus($student['ojt_evaluation_form'])); ?>
                                    </td>
                                    <td class="action-buttons">
                                        <div class="d-flex flex-column gap-1">
                                            <a href="view_student_documents.php?student_id=<?php echo $student['id']; ?>" 
                                               class="btn btn-sm btn-info" 
                                               title="View Documents">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="11" class="text-center">
                                        No students found in this section.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        $conn->close();
        exit;
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Fallback: redirect to documents root
$conn->close();
header('Location: coordinator_documents.php');
exit;

?>
