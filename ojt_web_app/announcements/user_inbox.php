<?php
session_start();

// Simple check for login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

include 'config/DBconfig.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_order = (isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC'])) ? $_GET['sort_order'] : 'DESC';

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total announcements for this user with search
$sql_count = "SELECT COUNT(*) AS total FROM announcements a 
              JOIN announcement_recipients ar ON a.id = ar.announcement_id 
              WHERE ar.recipient_id = ?";
$params = [$_SESSION['user_id']];
$types = "i";

if ($search !== '') {
    $sql_count .= " AND (a.title LIKE ? OR a.message LIKE ?)";
    $like_search = "%$search%";
    $params[] = $like_search;
    $params[] = $like_search;
    $types .= "ss";
}

$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$result_count = $stmt_count->get_result()->fetch_assoc();
$total = $result_count['total'];
$total_pages = ceil($total / $limit);

// Prepare query for fetching announcements
$sql = "SELECT a.id, a.title, a.message, ar.is_read, a.created_at 
        FROM announcements a 
        JOIN announcement_recipients ar ON a.id = ar.announcement_id 
        WHERE ar.recipient_id = ?";

$params = [$_SESSION['user_id']];
$types = "i";

if ($search !== '') {
    $sql .= " AND (a.title LIKE ? OR a.message LIKE ?)";
    $params[] = $like_search;
    $params[] = $like_search;
    $types .= "ss";
}

$sql .= " ORDER BY a.created_at $sort_order LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$announcements = $stmt->get_result();

// Mark announcement as read
if (isset($_POST['mark_read'])) {
    $announcement_id = intval($_POST['announcement_id']);
    $update_stmt = $conn->prepare("UPDATE announcement_recipients SET is_read = 1 WHERE announcement_id = ? AND recipient_id = ?");
    $update_stmt->bind_param("ii", $announcement_id, $_SESSION['user_id']);
    $update_stmt->execute();
    header("Location: user_inbox.php?" . http_build_query($_GET));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>User Inbox</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        /* Reset and base */
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
        }

        /* Header */
        .header {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 100;
        }
        .header img {
            height: 60px;
        }
        .search-bar input {
            width: 300px;
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .header-icons a {
            font-size: 28px;
            margin-left: 20px;
            color: #333;
            text-decoration: none;
        }
        .header-icons a:hover {
            color: #28a745;
        }

        /* Layout container */
        .container {
            display: flex;
            height: calc(100vh - 80px);
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            background: #0da80d;
            color: white;
            width: 250px;
            padding: 20px;
            border-radius: 15px 0 0 15px;
            box-shadow: 2px 0 8px rgba(0,0,0,0.2);
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }
        .sidebar ul li {
            margin-bottom: 15px;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            border-radius: 10px;
            transition: background 0.3s;
        }
        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: #2c3e50;
            cursor: pointer;
        }

        /* Content */
        .content {
            flex-grow: 1;
            padding: 20px 30px;
            background: white;
            border-radius: 0 15px 15px 0;
            overflow-y: auto;
        }

        /* Announcement cards */
        .announcement {
            border: 1px solid #ddd;
            padding: 15px 20px;
            border-radius: 7px;
            margin-bottom: 15px;
            background: #f9f9f9;
            transition: background 0.3s;
        }
        .announcement.unread {
            background: #e8f7e8;
            border-color: #28a745;
        }
        .announcement h5 {
            margin: 0 0 10px 0;
        }
        .announcement small {
            color: #555;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
            color: #333;
            text-decoration: none;
            user-select: none;
        }
        .pagination a:hover {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        .pagination .active {
            background: #28a745;
            color: white;
            border-color: #28a745;
            pointer-events: none;
        }

        /* Buttons */
        button.btn-sm {
            padding: 4px 10px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

<header class="header">
    <img src="../image/PLSP.png" alt="Logo" />
    <form class="search-bar" method="get" action="user_inbox.php" style="margin:0;">
        <input type="text" name="search" placeholder="Search announcements..." value="<?php echo htmlspecialchars($search); ?>" />
        <select name="sort_order" style="margin-left:10px; padding:8px; border-radius:5px; border:1px solid #ccc;">
            <option value="DESC" <?php if ($sort_order == 'DESC') echo 'selected'; ?>>Newest First</option>
            <option value="ASC" <?php if ($sort_order == 'ASC') echo 'selected'; ?>>Oldest First</option>
        </select>
        <button type="submit" class="btn btn-success btn-sm" style="margin-left:10px;">Search</button>
    </form>
    <div class="header-icons">
        <a href="#"><i class="bi bi-bell"></i></a>
        <a href="profile.php"><i class="bi bi-person-circle"></i></a>
    </div>
</header>

<div class="container">
    <nav class="sidebar">
        <ul>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="admin_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a href="documents.php"><i class="bi bi-folder"></i> Documents</a></li>
                <li><a href="monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li>
                <li><a href="partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                <li><a href="admin_inbox.php" class="active"><i class="bi bi-envelope-paper"></i> Sent Announcements</a></li>
                <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
            <?php else: ?>
                <li><a href="user_panel.php"><i class="bi bi-person"></i> Dashboard</a></li>
                <li><a href="documents.php"><i class="bi bi-folder"></i> Documents</a></li>
                <li><a href="monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li>
                <li><a href="partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                <li><a href="user_inbox.php" class="active"><i class="bi bi-inbox"></i> Inbox</a></li>
                <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <main class="content">
        <h2>Inbox</h2>

        <?php if ($announcements->num_rows === 0): ?>
            <p>No announcements found.</p>
        <?php else: ?>
            <?php while ($row = $announcements->fetch_assoc()): ?>
                <div class="announcement <?php echo $row['is_read'] ? '' : 'unread'; ?>">
                    <h5><?php echo htmlspecialchars($row['title']); ?></h5>
                    <p><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                    <small>Sent at: <?php echo htmlspecialchars($row['created_at']); ?></small>
                    <?php if (!$row['is_read']): ?>
                        <form method="post" style="margin-top:10px;">
                            <input type="hidden" name="announcement_id" value="<?php echo $row['id']; ?>" />
                            <button type="submit" name="mark_read" class="btn btn-primary btn-sm">Mark as Read</button>
                        </form>
                    <?php else: ?>
                        <span class="badge bg-success" style="margin-top:10px; display:inline-block;">Read</span>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>

            <!-- Pagination -->
            <nav aria-label="Page navigation">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Prev</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </main>
</div>

</body>
</html>
<?php

$stmt->close();
