<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'user'])) {
    header('Location: ../auth/login.php');
    exit;
}

$host = 'localhost';
$dbname = 'ojt';
$dbuser = 'root';
$dbpass = '';
$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

if (!isset($_GET['year']) || !is_numeric($_GET['year'])) {
    die("Invalid year parameter.");
}

$year = (int)$_GET['year'];

// Fetch partners (departments) for the selected year
$stmt = $conn->prepare("
    SELECT id, name, logo_url, female_count, male_count 
    FROM partners 
    WHERE year = ?
    ORDER BY name
");
$stmt->bind_param('i', $year);
$stmt->execute();
$result = $stmt->get_result();

$partners = [];
while ($row = $result->fetch_assoc()) {
    $partners[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Partnerships for Year <?php echo escape($year); ?></title>
    <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/partnership.css">
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

        /* Partner Box Styles */
        .partner-box {
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            max-width: 300px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: white;
            transition: all 0.3s ease;
        }

        .partner-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .partner-box img {
            max-width: 150px;
            height: auto;
            margin-bottom: 1rem;
            border-radius: 8px;
        }

        .partner-box h5 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .partner-box p {
            margin-bottom: 0.5rem;
            color: #555;
        }

        .partner-box .btn {
            margin-top: 1rem;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
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
            
            .partner-box {
                max-width: 100%;
                margin: 10px;
            }
        }

        @media (max-width: 576px) {
            .content {
                padding: 10px;
            }
            
            .partner-box {
                padding: 1rem;
                margin: 5px;
            }
            
            .partner-box img {
                max-width: 120px;
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

        .partners-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            padding: 1rem;
        }

        @media (max-width: 768px) {
            .partners-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
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
        <h2 class="page-title">Partnerships for Year <?php echo escape($year); ?></h2>

        <?php if (empty($partners)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> No partnerships found for this year.
            </div>
        <?php else: ?>
            <div class="partners-grid">
                <?php foreach ($partners as $partner): ?>
                    <div class="partner-box">
                        <img src="<?php echo escape($partner['logo_url'] ?: '/ojt-management-system/backend/assets/images/plsplogo.jpg'); ?>" 
                             alt="<?php echo escape($partner['name']); ?>"
                             onerror="this.src='/ojt-management-system/backend/assets/images/plsplogo.jpg'">
                        <h5><?php echo escape($partner['name']); ?></h5>
                        <p><i class="bi bi-gender-female text-danger"></i> Female Students: <?php echo (int)$partner['female_count']; ?></p>
                        <p><i class="bi bi-gender-male text-primary"></i> Male Students: <?php echo (int)$partner['male_count']; ?></p>
                        <p class="fw-bold text-success">Total: <?php echo ((int)$partner['female_count'] + (int)$partner['male_count']); ?></p>
                        <a href="partners_programs.php?partner_id=<?php echo escape($partner['id']); ?>" class="btn btn-primary">
                            <i class="bi bi-eye"></i> View Programs
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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