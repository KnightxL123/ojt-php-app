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

$host = 'localhost';
$dbname = 'ojt'; // Changed to lowercase
$dbuser = 'root';
$dbpass = '';

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Get coordinator's department
$coordinator_id = $_SESSION['user_id'] ?? null;
$department_id = null;

if ($coordinator_id) {
    $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
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
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $department_name = $row['name'];
}
$stmt->close();

// Get partners
$partners = [];
$stmt = $conn->prepare("SELECT id, name, year, website_url FROM partners");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $partners[] = $row;
        }
    }
    $stmt->close();
}

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo sanitize($department_name); ?> Partnership</title>
    <link rel="stylesheet" href="../../assets/css/dash.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        .partner-card {
            border-left: 4px solid #36A2EB;
            margin-bottom: 15px;
            transition: transform 0.3s;
        }
        .partner-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
            font-weight: bold;
            margin-bottom: 0;
        }
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
<header class="header">
    <img src="../../assets/images/PLSP.png" alt="Logo" class="header-logo">
    <div class="header-title">
        <h2><?php echo sanitize($department_name); ?> Department</h2>
        <p>Partnership Management</p>
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
            <li><a href="coordinator_documents.php"><i class="bi bi-folder"></i> Documents</a></li>
            <li><a href="coordinator_manages.php"><i class="bi bi-people"></i> Manage Section</a></li>
            <li><a href="coordinator_partnership.php" class="active"><i class="bi bi-handshake"></i> Partnership</a></li>
            <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
        </ul>
    </nav>

    <main class="dashboard-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Industry Partnerships</h1>
            <span class="badge bg-primary"><?php echo count($partners); ?> Partners</span>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo count($partners); ?></div>
                    <div class="stats-label">Total Partners</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stats-number"><?php echo date('Y'); ?></div>
                    <div class="stats-label">Current Year</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stats-number"><?php echo count(array_filter($partners, function($p) { return $p['year'] == date('Y'); })); ?></div>
                    <div class="stats-label">Active This Year</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stats-number"><?php echo count(array_filter($partners, function($p) { return !empty($p['website_url']); })); ?></div>
                    <div class="stats-label">With Website</div>
                </div>
            </div>
        </div>
        
        <!-- Add New Partner -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3><i class="bi bi-plus-circle"></i> Add New Partner</h3>
            </div>
            <div class="card-body">
                <form action="process_partner.php" method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="partner_name" class="form-label">Partner Name</label>
                                <input type="text" class="form-control" id="partner_name" name="partner_name" required 
                                       placeholder="Enter company/organization name">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="year" class="form-label">Partnership Year</label>
                                <input type="number" class="form-control" id="year" name="year" min="2000" max="2099" 
                                       value="<?php echo date('Y'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="website_url" class="form-label">Website URL</label>
                                <input type="url" class="form-control" id="website_url" name="website_url" 
                                       placeholder="https://example.com">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Add Partner
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Current Partners -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3><i class="bi bi-building"></i> Current Partners</h3>
            </div>
            <div class="card-body">
                <?php if (empty($partners)): ?>
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle" style="font-size: 2rem;"></i>
                        <h4>No Partners Yet</h4>
                        <p>Start by adding your first industry partner using the form above.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($partners as $partner): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card partner-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title"><?php echo sanitize($partner['name']); ?></h5>
                                            <p class="card-text">
                                                <span class="badge bg-secondary"><?php echo $partner['year']; ?></span>
                                                <?php if ($partner['website_url']): ?>
                                                <span class="badge bg-info">Has Website</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="partner_details.php?id=<?php echo $partner['id']; ?>">
                                                        <i class="bi bi-eye"></i> View Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="edit_partner.php?id=<?php echo $partner['id']; ?>">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="delete_partner.php?id=<?php echo $partner['id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this partner?')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php if ($partner['website_url']): ?>
                                    <div class="mt-2">
                                        <a href="<?php echo sanitize($partner['website_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right"></i> Visit Website
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mt-4">
            <div class="card-header bg-light">
                <h4 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <a href="partners_report.php" class="btn btn-outline-primary w-100 mb-2">
                            <i class="bi bi-download"></i> Export Partners List
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="partners_by_year.php" class="btn btn-outline-success w-100 mb-2">
                            <i class="bi bi-calendar"></i> View by Year
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="add_multiple_partners.php" class="btn btn-outline-info w-100 mb-2">
                            <i class="bi bi-upload"></i> Bulk Import
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>