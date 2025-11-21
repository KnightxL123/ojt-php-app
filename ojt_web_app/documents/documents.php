e<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'user'])) {
    header('Location: ../auth/login.php');
    exit;
}

include 'config/DBconfig.php'
    
    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Function to escape output
function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Function to get status class
function getStatusClass($status) {
    if (strpos($status, 'Approved') !== false) return 'status-approved';
    if (strpos($status, 'Pending') !== false) return 'status-pending';
    if (strpos($status, 'Rejected') !== false) return 'status-rejected';
    return 'status-not-submitted';
}

// Get parameters from URL
$dept_id = isset($_GET['department']) ? intval($_GET['department']) : null;
$program_id = isset($_GET['program']) ? intval($_GET['program']) : null;
$section_id = isset($_GET['section']) ? intval($_GET['section']) : null;

// Initialize breadcrumbs
$breadcrumbs = ['Documents' => 'documents.php'];

// --- Level 1: Show all departments ---
if (!$dept_id && !$program_id && !$section_id) {
    try {
        // Get all departments with program counts
        $sql = "SELECT d.id, d.name, COUNT(p.id) as program_count 
                FROM departments d 
                LEFT JOIN programs p ON d.id = p.department_id 
                GROUP BY d.id 
                ORDER BY d.name";
        
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Error fetching departments: " . $conn->error);
        }
        
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $departments = [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Documents - Departments</title>
        <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/documents.css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
        <style>
            .container { max-width: 1200px; }
            .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
            .folder { display: flex; flex-direction: column; align-items: center; text-decoration: none; 
                    background: #f8f9fa; padding: 20px; border-radius: 8px; color: #333; 
                    transition: transform 0.2s, box-shadow 0.2s; height: 100%; }
            .folder:hover { transform: translateY(-5px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
            .folder i { font-size: 3rem; margin-bottom: 0.5rem; color: #007bff; }
            .folder .count { font-size: 0.8rem; color: #6c757d; }
            .action-buttons { margin-top: 20px; }
        </style>
    </head>
    <body>
    <header class="header">
        <img src="/ojt-management-system/backend/assets/images/PLSP.png" alt="Logo" class="header-logo" />
        <div class="search-bar">
            <input type="text" placeholder="Search..." />
        </div>
        <div class="header-icons">
            <a href="#"><i class="bi bi-bell"></i></a>
            <a href="../student/profile.php"><i class="bi bi-person-circle"></i></a>
        </div>
    </header>

    <div class="main-container">
        <nav class="sidebar"> 
            <ul> 
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="../admin/admin_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li> 
                    <li><a href="documents.php" class="active"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="../admin/monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="../admin/manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="../announcements/admin_inbox.php"><i class="bi bi-envelope-paper"></i> Sent Announcements</a></li>  
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php elseif ($_SESSION['role'] === 'user'): ?>
                    <li><a href="../student/user_panel.php"><i class="bi bi-person"></i> Dashboard</a></li>
                    <li><a href="documents.php" class="active"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="../student/monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="../student/manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="../announcements/user_inbox.php"><i class="bi bi-inbox"></i> Inbox</a></li>
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php endif; ?>
            </ul>
        </nav>

        <div class="content px-4 py-5 w-100">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo sanitize($error_message); ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Departments</h1>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    
                <?php endif; ?>
            </div>

            <div class="folder-grid">
                <?php foreach($departments as $dept): ?>
                    <a href="documents.php?department=<?php echo $dept['id']; ?>" class="folder">
                        <i class="bi bi-building"></i>
                        <span><?php echo sanitize($dept['name']); ?></span>
                        <span class="count"><?php echo $dept['program_count']; ?> program(s)</span>
                    </a>
                <?php endforeach; ?>
                <?php if(empty($departments)): ?>
                    <div class="alert alert-info w-100">No departments found.</div>
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

// --- Level 2: Show programs in a department ---
if ($dept_id && !$program_id && !$section_id) {
    try {
        // Get department name
        $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->bind_param('i', $dept_id);
        $stmt->execute();
        $stmt->bind_result($dept_name);
        if (!$stmt->fetch()) {
            throw new Exception("Department not found");
        }
        $stmt->close();

        $breadcrumbs[$dept_name] = "documents.php?department=$dept_id";

        // Get programs with section counts
        $sql = "SELECT p.id, p.name, COUNT(s.id) as section_count 
                FROM programs p 
                LEFT JOIN sections s ON p.id = s.program_id 
                WHERE p.department_id = ? 
                GROUP BY p.id 
                ORDER BY p.name";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $dept_id);
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
        header('Location: documents.php');
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Documents - <?php echo sanitize($dept_name); ?></title>
        <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/documents.css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
        <style>
            .container { max-width: 1200px; }
            .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
            .folder { display: flex; flex-direction: column; align-items: center; text-decoration: none; 
                    background: #f8f9fa; padding: 20px; border-radius: 8px; color: #333; 
                    transition: transform 0.2s, box-shadow 0.2s; height: 100%; }
            .folder:hover { transform: translateY(-5px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
            .folder i { font-size: 3rem; margin-bottom: 0.5rem; color: #007bff; }
            .folder .count { font-size: 0.8rem; color: #6c757d; }
            nav.breadcrumb { background: none; padding-left: 0; }
            .action-buttons { margin-top: 20px; }
        </style>
    </head>
    <body>
    <header class="header">
        <img src="/ojt-management-system/backend/assets/images/PLSP.png" alt="Logo" class="header-logo" />
        <div class="search-bar">
            <input type="text" placeholder="Search..." />
        </div>
        <div class="header-icons">
            <a href="#"><i class="bi bi-bell"></i></a>
            <a href="../student/profile.php"><i class="bi bi-person-circle"></i></a>
        </div>
    </header>

    <div class="main-container">
        <nav class="sidebar"> 
            <ul> 
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="../admin/admin_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li> 
                    <li><a href="documents.php" class="active"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="../admin/monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="../admin/manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="../announcements/admin_inbox.php"><i class="bi bi-envelope-paper"></i> Sent Announcements</a></li>  
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php elseif ($_SESSION['role'] === 'user'): ?>
                    <li><a href="../student/user_panel.php"><i class="bi bi-person"></i> Dashboard</a></li>
                    <li><a href="documents.php" class="active"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="../student/monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="../student/manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="../announcements/user_inbox.php"><i class="bi bi-inbox"></i> Inbox</a></li>
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php endif; ?>
            </ul>
        </nav>

        <div class="content px-4 py-5 w-100">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo sanitize($error_message); ?></div>
            <?php endif; ?>

            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <?php foreach($breadcrumbs as $name => $link): ?>
                        <?php if ($link === end($breadcrumbs)) : ?>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo sanitize($name); ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo sanitize($name); ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Programs in <?php echo sanitize($dept_name); ?></h1>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    
                <?php endif; ?>
            </div>

            <div class="folder-grid">
                <?php foreach ($programs as $program): ?>
                    <a href="documents.php?department=<?php echo $dept_id; ?>&program=<?php echo $program['id']; ?>" class="folder">
                        <i class="bi bi-book"></i>
                        <span><?php echo sanitize($program['name']); ?></span>
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

// --- Level 3: Show sections in a program ---
if ($dept_id && $program_id && !$section_id) {
    try {
        // Get department and program info
        $sql = "SELECT d.name AS dept_name, p.name AS program_name 
                FROM departments d 
                JOIN programs p ON d.id = p.department_id 
                WHERE d.id = ? AND p.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $dept_id, $program_id);
        $stmt->execute();
        $stmt->bind_result($dept_name, $program_name);
        if (!$stmt->fetch()) {
            throw new Exception("Program not found");
        }
        $stmt->close();

        $breadcrumbs[$dept_name] = "documents.php?department=$dept_id";
        $breadcrumbs[$program_name] = "documents.php?department=$dept_id&program=$program_id";

        // Get sections with student counts
        $sql = "SELECT s.id, s.name, COUNT(st.id) as student_count 
                FROM sections s 
                LEFT JOIN students st ON s.id = st.section_id 
                WHERE s.program_id = ? 
                GROUP BY s.id 
                ORDER BY s.name";
        
        $stmt = $conn->prepare($sql);
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
        header("Location: documents.php?department=$dept_id");
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Documents - <?php echo sanitize($program_name); ?></title>
        <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/documents.css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
        <style>
            .container { max-width: 1200px; }
            .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
            .folder { display: flex; flex-direction: column; align-items: center; text-decoration: none; 
                    background: #f8f9fa; padding: 20px; border-radius: 8px; color: #333; 
                    transition: transform 0.2s, box-shadow 0.2s; height: 100%; }
            .folder:hover { transform: translateY(-5px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
            .folder i { font-size: 3rem; margin-bottom: 0.5rem; color: #007bff; }
            .folder .count { font-size: 0.8rem; color: #6c757d; }
            nav.breadcrumb { background: none; padding-left: 0; }
            .action-buttons { margin-top: 20px; }
        </style>
    </head>
    <body>
    <header class="header">
        <img src="/ojt-management-system/backend/assets/images/PLSP.png" alt="Logo" class="header-logo" />
        <div class="search-bar">
            <input type="text" placeholder="Search..." />
        </div>
        <div class="header-icons">
            <a href="#"><i class="bi bi-bell"></i></a>
            <a href="../student/profile.php"><i class="bi bi-person-circle"></i></a>
        </div>
    </header>

    <div class="main-container">
        <nav class="sidebar"> 
            <ul> 
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="../admin/admin_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li> 
                    <li><a href="documents.php" class="active"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="../admin/monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="../admin/manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="../announcements/admin_inbox.php"><i class="bi bi-envelope-paper"></i> Sent Announcements</a></li>  
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php elseif ($_SESSION['role'] === 'user'): ?>
                    <li><a href="../student/user_panel.php"><i class="bi bi-person"></i> Dashboard</a></li>
                    <li><a href="documents.php" class="active"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="../student/monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="../student/manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="../announcements/user_inbox.php"><i class="bi bi-inbox"></i> Inbox</a></li>
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php endif; ?>
            </ul> 
        </nav>

        <div class="content px-4 py-5 w-100">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo sanitize($error_message); ?></div>
            <?php endif; ?>

            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <?php foreach($breadcrumbs as $name => $link): ?>
                        <?php if ($link === end($breadcrumbs)) : ?>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo sanitize($name); ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo sanitize($name); ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Sections in <?php echo sanitize($program_name); ?></h1>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    
                <?php endif; ?>
            </div>

            <div class="folder-grid">
                <?php foreach ($sections as $section): ?>
                    <a href="documents.php?department=<?php echo $dept_id; ?>&program=<?php echo $program_id; ?>&section=<?php echo $section['id']; ?>" class="folder">
                        <i class="bi bi-people-fill"></i>
                        <span><?php echo sanitize($section['name']); ?></span>
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

// --- Level 4: Show students in a section with document status ---
if ($dept_id && $program_id && $section_id) {
    try {
        // Get department, program, and section info
        $sql = "SELECT d.name AS dept_name, p.name AS program_name, s.name AS section_name 
                FROM departments d 
                JOIN programs p ON d.id = p.department_id 
                JOIN sections s ON p.id = s.program_id 
                WHERE d.id = ? AND p.id = ? AND s.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iii', $dept_id, $program_id, $section_id);
        $stmt->execute();
        $stmt->bind_result($dept_name, $program_name, $section_name);
        if (!$stmt->fetch()) {
            throw new Exception("Section not found");
        }
        $stmt->close();

        $breadcrumbs[$dept_name] = "documents.php?department=$dept_id";
        $breadcrumbs[$program_name] = "documents.php?department=$dept_id&program=$program_id";
        $breadcrumbs[$section_name] = "documents.php?department=$dept_id&program=$program_id&section=$section_id";

        // Get students with document status
        $sql = "SELECT 
                st.id, 
                st.student_id,
                st.name AS student_name,
                COALESCE(doc.certificate_of_completion, 'Not Submitted') AS certificate_status,
                COALESCE(doc.daily_time_record, 'Not Submitted') AS dtr_status,
                COALESCE(doc.performance_evaluation, 'Not Submitted') AS performance_status,
                COALESCE(doc.narrative_report, 'Not Submitted') AS narrative_status,
                COALESCE(doc.printed_journal, 'Not Submitted') AS journal_status,
                COALESCE(doc.company_profile, 'Not Submitted') AS company_status,
                COALESCE(doc.ojt_evaluation_form, 'Not Submitted') AS evaluation_status
                FROM students st
                LEFT JOIN documents doc ON st.id = doc.student_id
                WHERE st.section_id = ?
                ORDER BY st.name";
        
        $stmt = $conn->prepare($sql);
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
        header("Location: documents.php?department=$dept_id&program=$program_id");
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Documents - <?php echo sanitize($section_name); ?></title>
        <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/documents.css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
        <style>
            :root {
                --sidebar-width: 250px;
                --sidebar-collapsed-width: 70px;
                --header-height: 110px;
                --primary-color: #0da80d;
                --secondary-color: #2c3e50;
            }
            
            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                overflow-x: hidden;
            }

            /* Header */
            .header {
                background: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 15px 30px;
                box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
                height: var(--header-height);
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
            }

            .header-logo {
                height: 80px;
            }

            .search-bar input {
                width: 350px;
                padding: 10px;
                border: 1px solid #ccc;
                border-radius: 5px;
            }

            .header-icons a {
                font-size: 28px;
                color: #333;
                margin-left: 20px;
                text-decoration: none;
            }

            .header-icons a:hover {
                color: #28a745;
            }

            /* Main Layout */
            .main-container {
                display: flex;
                min-height: calc(100vh - var(--header-height));
                margin-top: var(--header-height);
                transition: all 0.3s ease;
            }

            /* Sidebar */
            .sidebar {
                width: var(--sidebar-width);
                background: var(--primary-color);
                color: white;
                padding: 20px 0;
                height: calc(100vh - var(--header-height));
                position: fixed;
                left: 0;
                top: var(--header-height);
                overflow-y: auto;
                transition: all 0.3s ease;
                z-index: 999;
            }

            .sidebar.collapsed {
                width: var(--sidebar-collapsed-width);
            }

            .sidebar-toggle {
                position: absolute;
                top: 10px;
                right: -15px;
                background: var(--primary-color);
                border: none;
                color: white;
                border-radius: 50%;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 1001;
            }

            /* Sidebar Navigation */
            .sidebar ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .sidebar ul li {
                padding: 12px 20px;
                margin: 5px 10px;
                border-radius: 8px;
                transition: all 0.3s ease;
            }

            .sidebar ul li a {
                text-decoration: none;
                color: white;
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: bold;
                white-space: nowrap;
                overflow: hidden;
            }

            .sidebar ul li a i {
                font-size: 1.2rem;
                min-width: 20px;
                text-align: center;
            }

            .sidebar ul li a .menu-text {
                transition: opacity 0.3s ease;
            }

            .sidebar.collapsed ul li a .menu-text {
                opacity: 0;
                width: 0;
            }

            .sidebar ul li:hover {
                background: var(--secondary-color);
                transform: translateX(5px);
            }

            .sidebar ul li.active {
                background: var(--secondary-color);
                border-left: 4px solid #fff;
            }

            /* Main Content */
            .content {
                flex: 1;
                padding: 30px;
                margin-left: var(--sidebar-width);
                transition: all 0.3s ease;
                min-height: calc(100vh - var(--header-height));
            }

            .content.expanded {
                margin-left: var(--sidebar-collapsed-width);
            }

            /* Table Styles */
            .container { max-width: 100%; }
            .status-approved { color: #28a745; font-weight: bold; }
            .status-pending { color: #ffc107; font-weight: bold; }
            .status-rejected { color: #dc3545; font-weight: bold; }
            .status-not-submitted { color: #6c757d; }
            .table-responsive { 
                overflow-x: auto;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .table th { 
                white-space: nowrap; 
                vertical-align: middle;
                background: #343a40;
                color: white;
                font-weight: 600;
            }
            .table td { 
                vertical-align: middle;
                padding: 12px 8px;
            }
            .breadcrumb { 
                background: none; 
                padding-left: 0;
                font-size: 0.9rem;
            }
            .section-header { 
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 25px;
                border-radius: 10px;
                margin-bottom: 25px;
                border-left: 5px solid var(--primary-color);
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }

            /* Mobile Responsive */
            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                    width: 280px;
                }
                
                .sidebar.mobile-open {
                    transform: translateX(0);
                }
                
                .content {
                    margin-left: 0 !important;
                    padding: 15px;
                }
                
                .mobile-menu-btn {
                    display: block !important;
                    position: fixed;
                    top: 20px;
                    left: 20px;
                    z-index: 1002;
                    background: var(--primary-color);
                    color: white;
                    border: none;
                    border-radius: 5px;
                    padding: 8px 12px;
                }
                
                .header {
                    padding: 10px 15px;
                }
                
                .search-bar input {
                    width: 200px;
                }
                
                .header-logo {
                    height: 60px;
                }
                
                .table-responsive {
                    font-size: 0.8rem;
                }
                
                .btn-sm {
                    padding: 0.25rem 0.5rem;
                    font-size: 0.7rem;
                }
            }

            @media (max-width: 576px) {
                .section-header {
                    padding: 15px;
                }
                
                .table th, .table td {
                    padding: 8px 4px;
                    font-size: 0.75rem;
                }
                
                .breadcrumb {
                    font-size: 0.8rem;
                }
            }

            /* Mobile Menu Button */
            .mobile-menu-btn {
                display: none;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 5px;
                padding: 10px 15px;
                font-size: 1.2rem;
            }

            /* Overlay for mobile */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 998;
            }

            .sidebar-overlay.active {
                display: block;
            }
        </style>
    </head>
    <body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn d-lg-none">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <header class="header">
        <img src="/ojt-management-system/backend/assets/images/PLSP.png" alt="Logo" class="header-logo" />
        <div class="search-bar">
            <input type="text" placeholder="Search..." />
        </div>
        <div class="header-icons">
            <a href="#"><i class="bi bi-bell"></i></a>
            <a href="../student/profile.php"><i class="bi bi-person-circle"></i></a>
        </div>
    </header>

    <div class="main-container">
        <nav class="sidebar"> 
            <button class="sidebar-toggle d-none d-lg-block">
                <i class="bi bi-chevron-left"></i>
            </button>
            <ul> 
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="../admin/admin_panel.php"><i class="bi bi-speedometer2"></i> <span class="menu-text">Dashboard</span></a></li> 
                    <li class="active"><a href="documents.php"><i class="bi bi-folder"></i> <span class="menu-text">Documents</span></a></li> 
                    <li><a href="../admin/monitoring.php"><i class="bi bi-clipboard-data"></i> <span class="menu-text">Monitoring</span></a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> <span class="menu-text">Partnership</span></a></li>
                    <li><a href="../admin/manage.php"><i class="bi bi-diagram-3"></i> <span class="menu-text">Departments</span></a></li>
                    <li><a href="../announcements/admin_inbox.php"><i class="bi bi-envelope-paper"></i> <span class="menu-text">Sent Announcements</span></a></li>  
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> <span class="menu-text">Log Out</span></a></li> 
                <?php elseif ($_SESSION['role'] === 'user'): ?>
                    <li><a href="../student/user_panel.php"><i class="bi bi-person"></i> <span class="menu-text">Dashboard</span></a></li>
                    <li class="active"><a href="documents.php"><i class="bi bi-folder"></i> <span class="menu-text">Documents</span></a></li> 
                    <li><a href="../student/monitoring.php"><i class="bi bi-clipboard-data"></i> <span class="menu-text">Monitoring</span></a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> <span class="menu-text">Partnership</span></a></li>
                    <li><a href="../student/manage.php"><i class="bi bi-diagram-3"></i> <span class="menu-text">Departments</span></a></li>
                    <li><a href="../announcements/user_inbox.php"><i class="bi bi-inbox"></i> <span class="menu-text">Inbox</span></a></li>
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> <span class="menu-text">Log Out</span></a></li> 
                <?php endif; ?>
            </ul> 
        </nav>

        <div class="content">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo sanitize($error_message); ?></div>
            <?php endif; ?>

            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <?php foreach ($breadcrumbs as $name => $link): ?>
                        <?php if ($link === end($breadcrumbs)) : ?>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo sanitize($name); ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo sanitize($name); ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="section-header">
                <h1><?php echo sanitize($section_name); ?></h1>
                <p class="mb-1"><strong>Program:</strong> <?php echo sanitize($program_name); ?></p>
                <p><strong>Department:</strong> <?php echo sanitize($dept_name); ?></p>
            </div>

            <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="d-flex justify-content-between mb-4">
                    <a href="export_documents.php?department=<?php echo $dept_id; ?>&program=<?php echo $program_id; ?>&section=<?php echo $section_id; ?>" class="btn btn-success">
                        <i class="bi bi-download"></i> Export to Excel
                    </a>
                </div>
            <?php endif; ?>

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
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo sanitize($student['student_id']); ?></td>
                                <td><?php echo sanitize($student['student_name']); ?></td>
                                <td class="<?php echo getStatusClass($student['certificate_status']); ?>">
                                    <?php echo sanitize($student['certificate_status']); ?>
                                </td>
                                <td class="<?php echo getStatusClass($student['dtr_status']); ?>">
                                    <?php echo sanitize($student['dtr_status']); ?>
                                </td>
                                <td class="<?php echo getStatusClass($student['performance_status']); ?>">
                                    <?php echo sanitize($student['performance_status']); ?>
                                </td>
                                <td class="<?php echo getStatusClass($student['narrative_status']); ?>">
                                    <?php echo sanitize($student['narrative_status']); ?>
                                </td>
                                <td class="<?php echo getStatusClass($student['journal_status']); ?>">
                                    <?php echo sanitize($student['journal_status']); ?>
                                </td>
                                <td class="<?php echo getStatusClass($student['company_status']); ?>">
                                    <?php echo sanitize($student['company_status']); ?>
                                </td>
                                <td class="<?php echo getStatusClass($student['evaluation_status']); ?>">
                                    <?php echo sanitize($student['evaluation_status']); ?>
                                </td>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <td>
                                        <a href="view_documents.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info" title="View Documents">
                                            <i class="bi bi-file-earmark-text"></i> View
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="<?php echo ($_SESSION['role'] === 'admin') ? '11' : '10'; ?>" class="text-center">
                                    No students found in this section.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const content = document.querySelector('.content');
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const sidebarOverlay = document.querySelector('.sidebar-overlay');
            
            // Desktop sidebar toggle
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    content.classList.toggle('expanded');
                    
                    // Rotate toggle icon
                    const icon = this.querySelector('i');
                    if (sidebar.classList.contains('collapsed')) {
                        icon.classList.remove('bi-chevron-left');
                        icon.classList.add('bi-chevron-right');
                    } else {
                        icon.classList.remove('bi-chevron-right');
                        icon.classList.add('bi-chevron-left');
                    }
                });
            }
            
            // Mobile menu toggle
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                    sidebarOverlay.classList.toggle('active');
                });
            }
            
            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    this.classList.remove('active');
                });
            }
            
            // Close sidebar when clicking on a link (mobile)
            const sidebarLinks = document.querySelectorAll('.sidebar a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 768) {
                        sidebar.classList.remove('mobile-open');
                        sidebarOverlay.classList.remove('active');
                    }
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('mobile-open');
                    sidebarOverlay.classList.remove('active');
                }
            });
        });
    </script>
    </body>
    </html>
    <?php
    $conn->close();
    exit;
}

// Fallback: redirect to documents.php root
$conn->close();
header('Location: documents.php');
exit;

?>
