<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'user'])) {
    header('Location: ../auth/login.php');
    exit;
}

include 'config/DBconfig.php';


function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

if (!isset($_GET['partner_id']) || !is_numeric($_GET['partner_id'])) {
    die("Invalid partner ID.");
}

$partner_id = (int)$_GET['partner_id'];

// Get partner info
$stmt = $conn->prepare("SELECT id, name, logo_url FROM partners WHERE id = ?");
if (!$stmt) {
    die("Error preparing partner statement: " . $conn->error);
}
$stmt->bind_param('i', $partner_id);
$stmt->execute();
$partnerResult = $stmt->get_result();
if ($partnerResult->num_rows === 0) {
    die("Partner not found.");
}
$partner = $partnerResult->fetch_assoc();
$stmt->close();

// Debug: Check what partner name we have
$partner_name = $partner['name'];
error_log("Partner name: " . $partner_name);

// Get all departments to see what we're matching against
$dept_result = $conn->query("SELECT id, name FROM departments");
$departments = [];
while ($row = $dept_result->fetch_assoc()) {
    $departments[] = $row;
}
error_log("Available departments: " . print_r($departments, true));

// Try to find matching department
$matching_dept_id = null;
foreach ($departments as $dept) {
    if (strpos(strtolower($partner_name), strtolower($dept['name'])) !== false || 
        strpos(strtolower($dept['name']), strtolower($partner_name)) !== false) {
        $matching_dept_id = $dept['id'];
        break;
    }
}

if ($matching_dept_id) {
    // Get programs for the matching department
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.logo_url, p.female_count, p.male_count 
        FROM programs p 
        WHERE p.department_id = ? 
        ORDER BY p.name
    ");
    
    if (!$stmt) {
        die("Error preparing programs statement: " . $conn->error);
    }
    
    $stmt->bind_param('i', $matching_dept_id);
    $stmt->execute();
    $programsResult = $stmt->get_result();
    
    if (!$programsResult) {
        die("Error fetching programs: " . $conn->error);
    }
    
    $programs = [];
    while ($row = $programsResult->fetch_assoc()) {
        $programs[] = $row;
    }
    $stmt->close();
} else {
    // If no department match found, show empty programs
    $programs = [];
    error_log("No matching department found for partner: " . $partner_name);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Programs / Courses for <?php echo escape($partner['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
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

        /* Program Box Styles */
        .program-box {
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            max-width: 280px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: white;
            transition: all 0.3s ease;
        }

        .program-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .program-box img {
            max-width: 120px;
            height: auto;
            margin-bottom: 1rem;
            border-radius: 8px;
        }

        .program-box h5 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .program-box p {
            margin-bottom: 0.5rem;
            color: #555;
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
            
            .program-box {
                max-width: 100%;
                margin: 10px;
            }
        }

        @media (max-width: 576px) {
            .content {
                padding: 10px;
            }
            
            .program-box {
                padding: 1rem;
                margin: 5px;
            }
            
            .program-box img {
                max-width: 100px;
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

        /* Custom styles */
        .page-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
        }

        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            padding: 1rem;
        }

        @media (max-width: 768px) {
            .programs-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        .back-btn {
            margin-top: 2rem;
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: 500;
        }

        .partner-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid var(--primary-color);
            text-align: center;
        }
        
        .no-programs {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 0.9rem;
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
                <li><a href="../admin/monitoring.php"><i class="bi bi-clipboard-data"></i> <span class="menu-text">Monitoring</span></a></li>
                <li class="active"><a href="partnership.php"><i class="bi bi-handshake"></i> <span class="menu-text">Partnership</span></a></li>
                <li><a href="../admin/manage.php"><i class="bi bi-diagram-3"></i> <span class="menu-text">Departments</span></a></li>
                <li><a href="../announcements/admin_inbox.php"><i class="bi bi-envelope-paper"></i> <span class="menu-text">Sent Announcements</span></a></li>
                <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> <span class="menu-text">Log Out</span></a></li>
            <?php elseif ($_SESSION['role'] === 'user'): ?>
                <li><a href="../student/user_panel.php"><i class="bi bi-person"></i> <span class="menu-text">Dashboard</span></a></li>
                <li><a href="../documents/documents.php"><i class="bi bi-folder"></i> <span class="menu-text">Documents</span></a></li>
                <li><a href="../student/monitoring.php"><i class="bi bi-clipboard-data"></i> <span class="menu-text">Monitoring</span></a></li>
                <li class="active"><a href="partnership.php"><i class="bi bi-handshake"></i> <span class="menu-text">Partnership</span></a></li>
                <li><a href="../student/manage.php"><i class="bi bi-diagram-3"></i> <span class="menu-text">Departments</span></a></li>
                <li><a href="../announcements/user_inbox.php"><i class="bi bi-inbox"></i> <span class="menu-text">Inbox</span></a></li>
                <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> <span class="menu-text">Log Out</span></a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="content">
        <div class="partner-header">
            <h1 class="page-title"><?php echo escape($partner['name']); ?></h1>
            <p class="lead">Programs and Courses</p>
            
            <?php if (isset($matching_dept_id)): ?>
                <small class="text-muted">Showing programs from matching department</small>
            <?php else: ?>
                <small class="text-muted">No matching department found</small>
            <?php endif; ?>
        </div>

        <?php if (empty($programs)): ?>
            <div class="no-programs">
                <i class="bi bi-folder-x" style="font-size: 3rem; color: #6c757d;"></i>
                <h3>No Programs Found</h3>
                <p>No programs available for <?php echo escape($partner['name']); ?>.</p>
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    Partner: <?php echo escape($partner['name']); ?><br>
                    Available Departments: 
                    <?php 
                    foreach ($departments as $dept) {
                        echo escape($dept['name']) . ", ";
                    }
                    ?>
                </div>
            </div>
        <?php else: ?>
            <div class="programs-grid">
                <?php foreach ($programs as $program): ?>
                    <div class="program-box">
                        <?php if (!empty($program['logo_url'])): ?>
                            <img src="<?php echo escape($program['logo_url']); ?>" 
                                 alt="<?php echo escape($program['name']); ?>"
                                 onerror="this.src='/ojt-management-system/backend/assets/images/plsplogo.jpg'">
                        <?php else: ?>
                            <img src="/ojt-management-system/backend/assets/images/plsplogo.jpg" 
                                 alt="<?php echo escape($program['name']); ?>">
                        <?php endif; ?>
                        <h5><?php echo escape($program['name']); ?></h5>
                        <p><i class="bi bi-gender-female text-danger"></i> Female Students: <?php echo (int)$program['female_count']; ?></p>
                        <p><i class="bi bi-gender-male text-primary"></i> Male Students: <?php echo (int)$program['male_count']; ?></p>
                        <p class="fw-bold text-success">Total: <?php echo ((int)$program['female_count'] + (int)$program['male_count']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="partners_year.php?year=<?php echo escape(date('Y')); ?>" class="btn btn-secondary back-btn">
                <i class="bi bi-arrow-left"></i> Back to Partnerships
            </a>
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

?>
