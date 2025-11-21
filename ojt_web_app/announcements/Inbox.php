<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

include 'config/DBconfig.php';

$res = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = $res->fetch_all(MYSQLI_ASSOC);
$conn->close();

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Inbox</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }

        /* Header */
        .header {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 30px;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Logo */
        .header-logo {
            height: 80px;
        }

        /* Search Bar */
        .search-bar input {
            width: 350px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        /* Icons */
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
            min-height: calc(100vh - 110px);
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #0da80d;
            color: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            margin: 15px;
        }

        /* Sidebar Navigation */
        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar ul li {
            padding: 20px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar ul li a {
            text-decoration: none;
            color: white;
            display: block;
            font-weight: bold;
        }

        .sidebar ul li:hover {
            background: #2c3e50;
            border-radius: 10px;
            cursor: pointer;
        }

        /* Content Area */
        .content {
            flex: 1;
            padding: 20px;
            margin: 15px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Inbox Header */
        .inbox-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        /* Search Box */
        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 300px;
        }

        .search-box button {
            background: #0da80d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        /* Sort Dropdown */
        .sort-dropdown select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
        }

        /* Message Item */
        .message-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }

        .message-item:hover {
            background: #f8f9fa;
        }

        .message-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .message-content {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-logo">
            <img src="logo.png" alt="Logo" class="header-logo">
        </div>
        <div class="search-bar">
            <input type="text" placeholder="Search...">
        </div>
        <div class="header-icons">
            <a href="#"><i class="bi bi-bell"></i></a>
            <a href="#"><i class="bi bi-person-circle"></i></a>
        </div>
    </div>

    <div class="main-container">
        <nav class="sidebar">
            <ul>
                <li><a href="admin_panel.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a href="documents.php"><i class="bi bi-folder"></i> Documents</a></li>
                <li><a href="monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li>
                <li><a href="Inbox.php" class="active"><i class="bi bi-inbox"></i> Inbox</a></li>
                <li><a href="partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
                <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
            </ul>
        </nav>

        <div class="content">
            <div class="inbox-header">
                <h1><i class="bi bi-inbox"></i> Inbox</h1>
                <div class="sort-dropdown">
                    <select>
                        <option>Newest First ▼</option>
                        <option>Oldest First ▲</option>
                    </select>
                </div>
            </div>

            <div class="search-box">
                <input type="text" placeholder="Search messages...">
                <button>Search</button>
            </div>

            <div class="messages-list mt-4">
                <?php if (empty($announcements)): ?>
                    <div class="message-item">
                        <div class="message-content">No messages found.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $a): ?>
                        <div class="message-item">
                            <div class="message-title"><?php echo escape($a['title']); ?></div>
                            <div class="message-content"><?php echo escape($a['content']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>
