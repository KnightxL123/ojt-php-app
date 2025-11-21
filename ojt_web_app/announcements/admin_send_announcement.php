<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

include 'config/DBconfig.php';

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Handle sending new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_announcement'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $recipient_type = $_POST['recipient_type'];
    
    if (!empty($title) && !empty($message)) {
        // Insert announcement
        $stmt = $conn->prepare("INSERT INTO announcements (sender_id, title, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $_SESSION['user_id'], $title, $message);
        
        if ($stmt->execute()) {
            $announcement_id = $stmt->insert_id;
            $stmt->close();
            
            // Handle recipients based on type
            if ($recipient_type === 'all') {
                // Send to all users
                $stmt = $conn->prepare("INSERT INTO announcement_recipients (announcement_id, recipient_id) 
                                      SELECT ?, id FROM users WHERE role = 'user'");
                $stmt->bind_param('i', $announcement_id);
                $stmt->execute();
            } elseif ($recipient_type === 'department') {
                // Send to specific department
                $dept_id = $_POST['department_id'];
                $stmt = $conn->prepare("INSERT INTO announcement_recipients (announcement_id, recipient_id) 
                                      SELECT ?, u.id FROM users u 
                                      WHERE u.role = 'user' AND u.department_id = ?");
                $stmt->bind_param('ii', $announcement_id, $dept_id);
                $stmt->execute();
            }
            $stmt->close();
            
            $success_message = "Announcement sent successfully!";
        } else {
            $error_message = "Error sending announcement: " . $conn->error;
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}

// Get sent announcements
$sent_announcements = [];
$result = $conn->query("
    SELECT a.*, COUNT(ar.id) as recipient_count 
    FROM announcements a 
    LEFT JOIN announcement_recipients ar ON a.id = ar.announcement_id 
    WHERE a.sender_id = {$_SESSION['user_id']} 
    GROUP BY a.id 
    ORDER BY a.created_at DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sent_announcements[] = $row;
    }
}

// Get departments for targeting
$departments = [];
$dept_result = $conn->query("SELECT id, name FROM departments WHERE status = 'active'");
while ($row = $dept_result->fetch_assoc()) {
    $departments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sent Announcements</title>
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

        /* Announcement Styles */
        .announcement-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .announcement-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 15px 20px;
        }

        .announcement-body {
            padding: 20px;
        }

        .announcement-meta {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .send-announcement-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .send-announcement-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
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
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
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
            <li><a href="../admin/admin_panel.php"><i class="bi bi-speedometer2"></i> <span class="menu-text">Dashboard</span></a></li>
            <li><a href="../documents/documents.php"><i class="bi bi-folder"></i> <span class="menu-text">Documents</span></a></li>
            <li><a href="../admin/monitoring.php"><i class="bi bi-clipboard-data"></i> <span class="menu-text">Monitoring</span></a></li>
            <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> <span class="menu-text">Partnership</span></a></li>
            <li><a href="../admin/manage.php"><i class="bi bi-diagram-3"></i> <span class="menu-text">Departments</span></a></li>
            <li class="active"><a href="admin_inbox.php"><i class="bi bi-envelope-paper"></i> <span class="menu-text">Sent Announcements</span></a></li>
            <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> <span class="menu-text">Log Out</span></a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Sent Announcements</h1>
            <button class="btn send-announcement-btn" data-bs-toggle="modal" data-bs-target="#sendAnnouncementModal">
                <i class="bi bi-megaphone"></i> Send New Announcement
            </button>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo escape($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo escape($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($sent_announcements)): ?>
            <div class="empty-state">
                <i class="bi bi-envelope-x" style="font-size: 4rem; color: #dee2e6;"></i>
                <h3>No Announcements Sent</h3>
                <p>You haven't sent any announcements yet. Click the button above to send your first announcement.</p>
            </div>
        <?php else: ?>
            <div class="announcements-list">
                <?php foreach ($sent_announcements as $announcement): ?>
                    <div class="card announcement-card">
                        <div class="announcement-header">
                            <h5 class="card-title mb-0"><?php echo escape($announcement['title']); ?></h5>
                        </div>
                        <div class="announcement-body">
                            <div class="announcement-meta">
                                <i class="bi bi-calendar"></i> 
                                <?php echo date('F j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                <span class="mx-2">•</span>
                                <i class="bi bi-people"></i> 
                                <?php echo (int)$announcement['recipient_count']; ?> recipients
                            </div>
                            <p class="card-text"><?php echo nl2br(escape($announcement['message'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Send Announcement Modal -->
<div class="modal fade" id="sendAnnouncementModal" tabindex="-1" aria-labelledby="sendAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sendAnnouncementModalLabel">Send New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required 
                               placeholder="Enter announcement title">
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required 
                                  placeholder="Enter your announcement message"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Send To</label>
                        <div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recipient_type" id="allUsers" value="all" checked>
                                <label class="form-check-label" for="allUsers">
                                    All Users
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recipient_type" id="specificDept" value="department">
                                <label class="form-check-label" for="specificDept">
                                    Specific Department
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="departmentSelect" style="display: none;">
                        <label for="department_id" class="form-label">Select Department</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">Choose a department...</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo escape($dept['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_announcement" class="btn btn-primary">Send Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        
        // Handle recipient type selection
        const recipientRadios = document.querySelectorAll('input[name="recipient_type"]');
        const departmentSelect = document.getElementById('departmentSelect');
        
        recipientRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'department') {
                    departmentSelect.style.display = 'block';
                } else {
                    departmentSelect.style.display = 'none';
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
