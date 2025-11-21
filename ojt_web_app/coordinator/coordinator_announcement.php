<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: ../../auth/login.php?error=' . urlencode("Please log in to access the dashboard."));
    exit;
}

if ($_SESSION['role'] !== 'coordinator') {
    header('Location: ../../auth/login.php?error=' . urlencode("Unauthorized access. Coordinator role required."));
    exit;
}

include 'config/DBconfig.php'

// Get coordinator's department
$coordinator_id = $_SESSION['user_id'] ?? null;
$department_id = null;

if ($coordinator_id) {
    $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $coordinator_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $department_id = $row['department_id'];
    }
    $stmt->close();
}

if (!$department_id) {
    die("Error: NO DEPARTMENT EXISTS FOR THIS COORDINATOR.");
}

// Get department name
$department_name = '';
$stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $department_name = $row['name'];
}
$stmt->close();

// Get announcements sent by admin to this coordinator or their department
$announcements = [];
$stmt = $conn->prepare("
    SELECT 
        a.id, 
        a.title, 
        a.message, 
        a.created_at, 
        u.username as sender_name,
        u.role as sender_role,
        ar.is_read
    FROM announcements a 
    JOIN users u ON a.sender_id = u.id
    LEFT JOIN announcement_recipients ar ON a.id = ar.announcement_id AND ar.recipient_id = ?
    WHERE u.role = 'admin' 
    AND (
        ar.recipient_id = ? 
        OR EXISTS (
            SELECT 1 FROM announcement_recipients ar2 
            WHERE ar2.announcement_id = a.id 
            AND ar2.recipient_id IN (
                SELECT id FROM users WHERE department_id = ?
            )
        )
        OR EXISTS (
            SELECT 1 FROM announcement_recipients ar3 
            WHERE ar3.announcement_id = a.id 
            AND ar3.recipient_id = 0 -- For all users
        )
    )
    ORDER BY a.created_at DESC
");
$stmt->bind_param("iii", $coordinator_id, $coordinator_id, $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}
$stmt->close();

// Mark announcement as read when viewed
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $announcement_id = $_GET['view'];
    $stmt = $conn->prepare("
        INSERT INTO announcement_recipients (announcement_id, recipient_id, is_read) 
        VALUES (?, ?, 1) 
        ON DUPLICATE KEY UPDATE is_read = 1
    ");
    $stmt->bind_param("ii", $announcement_id, $coordinator_id);
    $stmt->execute();
    $stmt->close();
}

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Get unread count
$unread_count = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) as unread_count 
    FROM announcements a 
    JOIN announcement_recipients ar ON a.id = ar.announcement_id 
    WHERE ar.recipient_id = ? AND ar.is_read = 0
");
$stmt->bind_param("i", $coordinator_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $unread_count = $row['unread_count'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo sanitize($department_name); ?> - Announcements</title>
    <link rel="stylesheet" href="../../assets/css/coordinator/dash.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        .announcement-unread {
            background-color: #f0f8ff;
            border-left: 4px solid #007bff;
        }
        .announcement-read {
            background-color: #f8f9fa;
        }
        .badge-unread {
            background-color: #dc3545;
        }
        .sender-admin {
            color: #dc3545;
            font-weight: bold;
        }
        .announcement-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<header class="header">
    <img src="../../assets/images/PLSP.png" alt="Logo" class="header-logo">
    <div class="header-title">
        <h2><?php echo sanitize($department_name); ?> Department</h2>
        <p>Announcements</p>
    </div>
    <div class="header-icons">
        <a href="#" class="position-relative">
            <i class="bi bi-bell"></i>
            <?php if ($unread_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge badge-unread">
                    <?php echo $unread_count; ?>
                    <span class="visually-hidden">unread messages</span>
                </span>
            <?php endif; ?>
        </a>
        <a href="profile.php"><i class="bi bi-person-circle"></i></a>
    </div>
</header>
<div class="main-container">
    <nav class="sidebar">
        <ul>
            <li><a href="coordinator_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="coordinator_announcement.php" class="active"><i class="bi bi-megaphone"></i> Announcements</a></li>
            <li><a href="coordinator_monitor.php"><i class="bi bi-clipboard-data"></i> Monitor</a></li>
            <li><a href="coordinator_documents.php"><i class="bi bi-folder"></i> Documents</a></li>
            <li><a href="coordinator_managesection.php"><i class="bi bi-people"></i> Manage Section</a></li>
            <li><a href="coordinator_partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
            <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
        </ul>
    </nav>

    <main class="dashboard-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Announcements from Admin</h1>
            <div class="d-flex align-items-center gap-3">
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger"><?php echo $unread_count; ?> unread</span>
                <?php endif; ?>
                <span class="text-muted"><?php echo count($announcements); ?> total</span>
            </div>
        </div>
        
        <?php if (empty($announcements)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-megaphone display-1 text-muted"></i>
                    <h3 class="mt-3">No Announcements</h3>
                    <p class="text-muted">You don't have any announcements from admin yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card announcement-card <?php echo $announcement['is_read'] ? 'announcement-read' : 'announcement-unread'; ?>"
                             onclick="window.location.href='?view=<?php echo $announcement['id']; ?>'">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><?php echo sanitize($announcement['title']); ?></h5>
                                <?php if (!$announcement['is_read']): ?>
                                    <span class="badge bg-primary">New</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-person"></i>
                                        <span class="sender-admin">Admin</span>
                                    </small>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i>
                                        <?php echo date('M j, Y g:i a', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="card-text"><?php echo nl2br(sanitize($announcement['message'])); ?></p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <small class="text-muted">
                                    <?php if ($announcement['is_read']): ?>
                                        <i class="bi bi-check2-all text-success"></i> Read
                                    <?php else: ?>
                                        <i class="bi bi-check2"></i> Click to mark as read
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Information Card -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Announcements Information</h5>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>This page displays announcements sent by administrators.</strong></p>
                <ul class="mb-0">
                    <li>You can only view announcements here</li>
                    <li>Announcements are sent by system administrators</li>
                    <li>New announcements are marked with a "New" badge</li>
                    <li>Click on any announcement to mark it as read</li>
                    <li>Contact administrators if you have questions about any announcement</li>
                </ul>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-refresh page every 30 seconds to check for new announcements
    setInterval(function() {
        // Only refresh if user is not interacting with the page
        if (!document.hidden) {
            window.location.reload();
        }
    }, 30000);

    // Mark as read when clicking on announcement card
    document.addEventListener('DOMContentLoaded', function() {
        const announcementCards = document.querySelectorAll('.announcement-card');
        announcementCards.forEach(card => {
            card.addEventListener('click', function() {
                const url = this.getAttribute('onclick').match(/'([^']+)'/)[1];
                window.location.href = url;
            });
        });
    });
</script>
</body>
</html>

<?php $conn->close(); ?>
