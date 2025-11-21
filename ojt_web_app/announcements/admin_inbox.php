<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'ojt');

// Check if admin is logged in
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Get all announcements sent by this admin
$stmt = $conn->prepare("SELECT a.id, a.title, a.message, a.created_at, 
    COUNT(ar.id) AS total_sent,
    SUM(CASE WHEN ar.is_read = 1 THEN 1 ELSE 0 END) AS total_read
    FROM announcements a 
    LEFT JOIN announcement_recipients ar ON a.id = ar.announcement_id
    WHERE a.sender_id = ?
    GROUP BY a.id
    ORDER BY a.created_at DESC");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        /* Table Styles */
        .announcement-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .announcement-table .table {
            margin-bottom: 0;
        }

        .announcement-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            font-weight: 600;
        }

        .announcement-table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #dee2e6;
        }

        .announcement-table tbody tr:hover {
            background-color: rgba(13, 168, 13, 0.05);
        }

        .message-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .read-rate {
            font-weight: 600;
        }

        .read-rate.high {
            color: #28a745;
        }

        .read-rate.medium {
            color: #ffc107;
        }

        .read-rate.low {
            color: #dc3545;
        }

        .send-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
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
            
            .announcement-table {
                font-size: 0.8rem;
            }
            
            .message-preview {
                max-width: 150px;
            }
        }

        @media (max-width: 576px) {
            .content {
                padding: 10px;
            }
            
            .stats-card {
                padding: 15px;
            }
            
            .stats-number {
                font-size: 2rem;
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
            <h1 class="page-title">My Sent Announcements</h1>
            <a href="admin_send_announcement.php" class="btn send-btn">
                <i class="bi bi-megaphone"></i> Send New Announcement
            </a>
        </div>

        <?php 
        $total_announcements = $res->num_rows;
        $total_recipients = 0;
        $total_read = 0;
        
        // Calculate totals
        $res->data_seek(0); // Reset pointer
        while ($row = $res->fetch_assoc()) {
            $total_recipients += $row['total_sent'];
            $total_read += $row['total_read'];
        }
        $res->data_seek(0); // Reset pointer again for display
        ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <h3 class="stats-number"><?php echo $total_announcements; ?></h3>
                    <p class="stats-label">Total Announcements Sent</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h3 class="stats-number"><?php echo $total_recipients; ?></h3>
                    <p class="stats-label">Total Recipients</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h3 class="stats-number"><?php echo $total_read; ?></h3>
                    <p class="stats-label">Total Read Count</p>
                </div>
            </div>
        </div>

        <?php if ($total_announcements == 0): ?>
            <div class="empty-state">
                <i class="bi bi-envelope-x" style="font-size: 4rem; color: #dee2e6;"></i>
                <h3>No Announcements Sent</h3>
                <p>You haven't sent any announcements yet. Click the button above to send your first announcement.</p>
            </div>
        <?php else: ?>
            <div class="announcement-table">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Message Preview</th>
                            <th>Sent On</th>
                            <th>Total Sent</th>
                            <th>Read Count</th>
                            <th>Read Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $res->fetch_assoc()): 
                        $read_rate = $row['total_sent'] > 0 ? round(($row['total_read'] / $row['total_sent']) * 100) : 0;
                        $read_rate_class = $read_rate >= 70 ? 'high' : ($read_rate >= 40 ? 'medium' : 'low');
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                            <td class="message-preview" title="<?php echo htmlspecialchars($row['message']); ?>">
                                <?php echo htmlspecialchars(substr($row['message'], 0, 50)); ?>...
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($row['created_at'])); ?></td>
                            <td>
                                <span class="badge bg-primary"><?php echo $row['total_sent']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo $row['total_read']; ?></span>
                            </td>
                            <td>
                                <span class="read-rate <?php echo $read_rate_class; ?>">
                                    <?php echo $read_rate; ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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