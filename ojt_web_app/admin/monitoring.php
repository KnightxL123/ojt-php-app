<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'user'])) {
    header('Location: login.php');
    exit;
}
include 'config/DBconfig.php';
// Initialize breadcrumbs
$breadcrumbs = ['Monitoring' => 'monitoring.php'];

// Function to escape output
function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Get parameters from URL
$deptId = isset($_GET['dept']) ? intval($_GET['dept']) : null;
$programId = isset($_GET['program']) ? intval($_GET['program']) : null;
$sectionId = isset($_GET['section']) ? intval($_GET['section']) : null;

// --- Level 1: Show all departments ---
if (!$deptId && !$programId && !$sectionId) {
    $res = $conn->query("SELECT id, name FROM departments ORDER BY name");
    $departments = [];
    while ($row = $res->fetch_assoc()) {
        $departments[] = $row;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Monitoring - Departments</title>
        <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/monitoring.css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
        <style>
            .container { max-width: 1000px; margin: 30px auto; }
            .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.5rem; }
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
        <img src="../image/PLSP.png" alt="Logo" class="header-logo" />
        <div class="search-bar">
            <input type="text" placeholder="Search..." />
        </div>
        <div class="header-icons">
            <a href="#"><i class="bi bi-bell"></i></a>
            <a href="profile.php"><i class="bi bi-person-circle"></i></a>
        </div>
    </header>

    <div class="main-container">
        <nav class="sidebar"> 
            <ul> 
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="admin_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li> 
                    <li><a href="documents.php"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="monitoring.php" class="active"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="admin_inbox.php"><i class="bi bi-envelope-paper"></i> Sent Announcements</a></li>
                    <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php elseif ($_SESSION['role'] === 'user'): ?>
                    <li><a href="user_panel.php"><i class="bi bi-person"></i> Dashboard</a></li>
                    <li><a href="documents.php"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="monitoring.php" class="active"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="user_inbox.php"><i class="bi bi-inbox"></i> Inbox</a></li>
                    <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php endif; ?>
            </ul> 
        </nav>

        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Departments</h1>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <?php endif; ?>
            </div>

            <div class="folder-grid">
                <?php foreach ($departments as $dept): 
                    // Count programs in department
                    $countStmt = $conn->prepare("SELECT COUNT(*) FROM programs WHERE department_id = ?");
                    $countStmt->bind_param('i', $dept['id']);
                    $countStmt->execute();
                    $countStmt->bind_result($programCount);
                    $countStmt->fetch();
                    $countStmt->close();
                ?>
                    <a class="folder" href="monitoring.php?dept=<?php echo $dept['id']; ?>">
                        <i class="bi bi-building"></i>
                        <span><?php echo escape($dept['name']); ?></span>
                        <span class="count"><?php echo $programCount; ?> program(s)</span>
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
if ($deptId && !$programId && !$sectionId) {
    // Get department name
    $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->bind_param('i', $deptId);
    $stmt->execute();
    $stmt->bind_result($deptName);
    if (!$stmt->fetch()) {
        $stmt->close();
        $conn->close();
        header('Location: monitoring.php');
        exit;
    }
    $stmt->close();

    $breadcrumbs[$deptName] = "monitoring.php?dept=$deptId";

    // Get programs in this department
    $stmt = $conn->prepare("SELECT id, name FROM programs WHERE department_id = ? ORDER BY name");
    $stmt->bind_param('i', $deptId);
    $stmt->execute();
    $res = $stmt->get_result();
    $programs = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Monitoring - Programs in <?php echo escape($deptName); ?></title>
        <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/monitoring.css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
        <style>
            .container { max-width: 1000px; margin: 30px auto; }
            .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.5rem; }
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
        <img src="../image/PLSP.png" alt="Logo" class="header-logo" />
        <div class="search-bar">
            <input type="text" placeholder="Search..." />
        </div>
        <div class="header-icons">
            <a href="#"><i class="bi bi-bell"></i></a>
            <a href="profile.php"><i class="bi bi-person-circle"></i></a>
        </div>
    </header>

    <div class="main-container">
        <nav class="sidebar"> 
            <ul> 
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="admin_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li> 
                    <li><a href="documents.php"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="monitoring.php" class="active"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="admin_inbox.php"><i class="bi bi-envelope-paper"></i> Sent Announcements</a></li>
                    <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php elseif ($_SESSION['role'] === 'user'): ?>
                    <li><a href="user_panel.php"><i class="bi bi-person"></i> Dashboard</a></li>
                    <li><a href="documents.php"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="monitoring.php" class="active"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="user_inbox.php"><i class="bi bi-inbox"></i> Inbox</a></li>
                    <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php endif; ?>
            </ul> 
        </nav>

        <div class="container">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <?php 
                    $lastKey = array_key_last($breadcrumbs);
                    foreach($breadcrumbs as $name => $link): 
                    ?>
                        <?php if ($name === $lastKey): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo escape($name); ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo escape($name); ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Programs in <?php echo escape($deptName); ?></h1>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <?php endif; ?>
            </div>

            <div class="folder-grid">
                <?php foreach ($programs as $prog): 
                    // Count sections in program
                    $countStmt = $conn->prepare("SELECT COUNT(*) FROM sections WHERE program_id = ?");
                    $countStmt->bind_param('i', $prog['id']);
                    $countStmt->execute();
                    $countStmt->bind_result($sectionCount);
                    $countStmt->fetch();
                    $countStmt->close();
                ?>
                    <a class="folder" href="monitoring.php?dept=<?php echo $deptId; ?>&program=<?php echo $prog['id']; ?>">
                        <i class="bi bi-collection"></i>
                        <span><?php echo escape($prog['name']); ?></span>
                        <span class="count"><?php echo $sectionCount; ?> section(s)</span>
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
if ($deptId && $programId && !$sectionId) {
    // Get department name
    $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->bind_param('i', $deptId);
    $stmt->execute();
    $stmt->bind_result($deptName);
    if (!$stmt->fetch()) {
        $stmt->close();
        $conn->close();
        header('Location: monitoring.php');
        exit;
    }
    $stmt->close();

    // Get program name
    $stmt = $conn->prepare("SELECT name FROM programs WHERE id = ? AND department_id = ?");
    $stmt->bind_param('ii', $programId, $deptId);
    $stmt->execute();
    $stmt->bind_result($programName);
    if (!$stmt->fetch()) {
        $stmt->close();
        $conn->close();
        header('Location: monitoring.php?dept=' . $deptId);
        exit;
    }
    $stmt->close();

    $breadcrumbs[$deptName] = "monitoring.php?dept=$deptId";
    $breadcrumbs[$programName] = "monitoring.php?dept=$deptId&program=$programId";

    // Get sections in this program
    $stmt = $conn->prepare("SELECT id, name FROM sections WHERE program_id = ? ORDER BY name");
    $stmt->bind_param('i', $programId);
    $stmt->execute();
    $res = $stmt->get_result();
    $sections = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Monitoring - Sections in <?php echo escape($programName); ?></title>
        <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/monitoring.css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
        <style>
            .container { max-width: 1000px; margin: 30px auto; }
            .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.5rem; }
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
        <img src="../image/PLSP.png" alt="Logo" class="header-logo" />
        <div class="search-bar">
            <input type="text" placeholder="Search..." />
        </div>
        <div class="header-icons">
            <a href="#"><i class="bi bi-bell"></i></a>
            <a href="profile.php"><i class="bi bi-person-circle"></i></a>
        </div>
    </header>

    <div class="main-container">
        <nav class="sidebar"> 
            <ul> 
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="admin_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li> 
                    <li><a href="documents.php"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="monitoring.php" class="active"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="admin_inbox.php"><i class="bi bi-envelope-paper"></i> Sent Announcements</a></li>
                    <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php elseif ($_SESSION['role'] === 'user'): ?>
                    <li><a href="user_panel.php"><i class="bi bi-person"></i> Dashboard</a></li>
                    <li><a href="documents.php"><i class="bi bi-folder"></i> Documents</a></li> 
                    <li><a href="monitoring.php" class="active"><i class="bi bi-clipboard-data"></i> Monitoring</a></li> 
                    <li><a href="partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                    <li><a href="manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                    <li><a href="user_inbox.php"><i class="bi bi-inbox"></i> Inbox</a></li>
                    <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li> 
                <?php endif; ?>
            </ul>  
        </nav>

        <div class="container">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <?php 
                    $lastKey = array_key_last($breadcrumbs);
                    foreach($breadcrumbs as $name => $link): 
                    ?>
                        <?php if ($name === $lastKey): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo escape($name); ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo escape($name); ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Sections in <?php echo escape($programName); ?></h1>
                <p class="mb-0"><strong>Department:</strong> <?php echo escape($deptName); ?></p>
            </div>

            <div class="folder-grid">
                <?php foreach ($sections as $section): 
                    // Count students in section
                    $countStmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE section_id = ?");
                    $countStmt->bind_param('i', $section['id']);
                    $countStmt->execute();
                    $countStmt->bind_result($studentCount);
                    $countStmt->fetch();
                    $countStmt->close();
                ?>
                    <a class="folder" href="monitoring.php?dept=<?php echo $deptId; ?>&program=<?php echo $programId; ?>&section=<?php echo $section['id']; ?>">
                        <i class="bi bi-people-fill"></i>
                        <span><?php echo escape($section['name']); ?></span>
                        <span class="count"><?php echo $studentCount; ?> student(s)</span>
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

// --- Level 4: Show students in a section ---
if ($deptId && $programId && $sectionId) {
    // Get department name
    $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->bind_param('i', $deptId);
    $stmt->execute();
    $stmt->bind_result($deptName);
    if (!$stmt->fetch()) {
        $stmt->close();
        $conn->close();
        header('Location: monitoring.php');
        exit;
    }
    $stmt->close();

    // Get program name
    $stmt = $conn->prepare("SELECT name FROM programs WHERE id = ? AND department_id = ?");
    $stmt->bind_param('ii', $programId, $deptId);
    $stmt->execute();
    $stmt->bind_result($programName);
    if (!$stmt->fetch()) {
        $stmt->close();
        $conn->close();
        header('Location: monitoring.php?dept=' . $deptId);
        exit;
    }
    $stmt->close();

    // Get section name
    $stmt = $conn->prepare("SELECT name FROM sections WHERE id = ? AND program_id = ?");
    $stmt->bind_param('ii', $sectionId, $programId);
    $stmt->execute();
    $stmt->bind_result($sectionName);
    if (!$stmt->fetch()) {
        $stmt->close();
        $conn->close();
        header('Location: monitoring.php?dept=' . $deptId . '&program=' . $programId);
        exit;
    }
    $stmt->close();

    $breadcrumbs[$deptName] = "monitoring.php?dept=$deptId";
    $breadcrumbs[$programName] = "monitoring.php?dept=$deptId&program=$programId";
    $breadcrumbs[$sectionName] = "monitoring.php?dept=$deptId&program=$programId&section=$sectionId";

    // Get adviser assigned to this section
    $stmt = $conn->prepare("SELECT advisers.name FROM advisers 
                           JOIN section_adviser ON advisers.id = section_adviser.adviser_id 
                           WHERE section_adviser.section_id = ?");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $adviserRow = $res->fetch_assoc();
    $adviserName = $adviserRow ? $adviserRow['name'] : 'No adviser assigned';
    $stmt->close();

    // Get students in this section
    $stmt = $conn->prepare("SELECT id, student_id, name, hours_completed, total_hours 
                           FROM students 
                           WHERE section_id = ? 
                           ORDER BY name");
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $students = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Monitoring - <?php echo escape($sectionName); ?></title>
        <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/monitoring.css" />
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

            /* Content Styles */
            .container { 
                max-width: 1200px; 
                margin: 0 auto;
                padding: 20px;
            }
            
            nav.breadcrumb { 
                background: none; 
                padding-left: 0;
                font-size: 0.9rem;
            }
            
            .progress { 
                height: 20px; 
                border-radius: 10px;
                overflow: hidden;
            }
            
            .progress-bar { 
                background: linear-gradient(45deg, #28a745, #20c997);
                transition: width 0.5s ease;
            }
            
            .section-info { 
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 25px;
                border-radius: 10px;
                margin-bottom: 25px;
                border-left: 5px solid var(--primary-color);
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .section-info h1 {
                color: #2c3e50;
                margin-bottom: 15px;
            }
            
            .section-info p {
                margin-bottom: 8px;
                font-size: 1.1rem;
            }
            
            .table th { 
                white-space: nowrap;
                background: #343a40;
                color: white;
                font-weight: 600;
                padding: 15px 12px;
            }
            
            .table td {
                padding: 12px;
                vertical-align: middle;
            }
            
            .progress-cell { 
                min-width: 150px; 
            }
            
            .table-hover tbody tr:hover {
                background-color: rgba(13, 168, 13, 0.1);
                transform: translateY(-1px);
                transition: all 0.2s ease;
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
                
                .container {
                    padding: 10px;
                }
                
                .section-info {
                    padding: 15px;
                }
                
                .table-responsive {
                    font-size: 0.8rem;
                }
                
                .btn-sm {
                    padding: 0.25rem 0.5rem;
                    font-size: 0.7rem;
                }
                
                .progress-cell {
                    min-width: 120px;
                }
            }

            @media (max-width: 576px) {
                .section-info {
                    padding: 12px;
                }
                
                .section-info h1 {
                    font-size: 1.5rem;
                }
                
                .section-info p {
                    font-size: 0.9rem;
                }
                
                .table th, .table td {
                    padding: 8px 4px;
                    font-size: 0.75rem;
                }
                
                .breadcrumb {
                    font-size: 0.8rem;
                }
                
                .progress {
                    height: 16px;
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

            /* Custom button styles */
            .btn-primary {
                background: linear-gradient(45deg, #007bff, #0056b3);
                border: none;
                border-radius: 6px;
                transition: all 0.3s ease;
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,123,255,0.3);
            }
            
            .btn-warning {
                background: linear-gradient(45deg, #ffc107, #e0a800);
                border: none;
                border-radius: 6px;
                transition: all 0.3s ease;
            }
            
            .btn-warning:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(255,193,7,0.3);
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
                    <li><a href="../documents/documents.php"><i class="bi bi-folder"></i> <span class="menu-text">Documents</span></a></li> 
                    <li class="active"><a href="monitoring.php"><i class="bi bi-clipboard-data"></i> <span class="menu-text">Monitoring</span></a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> <span class="menu-text">Partnership</span></a></li>
                    <li><a href="../admin/manage.php"><i class="bi bi-diagram-3"></i> <span class="menu-text">Departments</span></a></li>
                    <li><a href="../announcements/admin_inbox.php"><i class="bi bi-envelope-paper"></i> <span class="menu-text">Sent Announcements</span></a></li>
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> <span class="menu-text">Log Out</span></a></li> 
                <?php elseif ($_SESSION['role'] === 'user'): ?>
                    <li><a href="../student/user_panel.php"><i class="bi bi-person"></i> <span class="menu-text">Dashboard</span></a></li>
                    <li><a href="../documents/documents.php"><i class="bi bi-folder"></i> <span class="menu-text">Documents</span></a></li> 
                    <li class="active"><a href="monitoring.php"><i class="bi bi-clipboard-data"></i> <span class="menu-text">Monitoring</span></a></li> 
                    <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> <span class="menu-text">Partnership</span></a></li>
                    <li><a href="../student/manage.php"><i class="bi bi-diagram-3"></i> <span class="menu-text">Departments</span></a></li>
                    <li><a href="../announcements/user_inbox.php"><i class="bi bi-inbox"></i> <span class="menu-text">Inbox</span></a></li>
                    <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> <span class="menu-text">Log Out</span></a></li> 
                <?php endif; ?>
            </ul>  
        </nav>

        <div class="content">
            <div class="container">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <?php 
                        $lastKey = array_key_last($breadcrumbs);
                        foreach($breadcrumbs as $name => $link): 
                        ?>
                            <?php if ($name === $lastKey): ?>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo escape($name); ?></li>
                            <?php else: ?>
                                <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo escape($name); ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <div class="section-info">
                    <h1><?php echo escape($sectionName); ?></h1>
                    <p><strong>Department:</strong> <?php echo escape($deptName); ?></p>
                    <p><strong>Program:</strong> <?php echo escape($programName); ?></p>
                    <p><strong>Adviser:</strong> <?php echo escape($adviserName); ?></p>
                </div>

                <h2 class="mb-3">Students</h2>
                <?php if (empty($students)): ?>
                    <div class="alert alert-info">No students found in this section.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Hours Completed</th>
                                    <th>Progress</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): 
                                    $progress = ($student['total_hours'] > 0) ? 
                                        round(($student['hours_completed'] / $student['total_hours']) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo escape($student['student_id']); ?></td>
                                        <td><?php echo escape($student['name']); ?></td>
                                        <td><?php echo $student['hours_completed']; ?> / <?php echo $student['total_hours']; ?></td>
                                        <td class="progress-cell">
                                            <div class="progress">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $progress; ?>%" 
                                                     aria-valuenow="<?php echo $progress; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?php echo $progress; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="student_monitoring.php?student_id=<?php echo $student['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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

// Fallback: redirect to monitoring.php root
header('Location: monitoring.php');
exit;

?>
