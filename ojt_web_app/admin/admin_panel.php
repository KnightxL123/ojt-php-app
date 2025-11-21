<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

include 'config/DBconfig.php';

// Gather statistics for dashboard (example student stats)
$totalStudents = 0;
$maleStudents = 'N/A';
$femaleStudents = 'N/A';

$res = $conn->query("SELECT COUNT(*) as total FROM students");
if ($res) {
    $row = $res->fetch_assoc();
    $totalStudents = $row['total'] ?? 0;
}

$res = $conn->query("SELECT COUNT(*) as male FROM students WHERE gender = 'Male'");
if ($res) {
    $row = $res->fetch_assoc();
    $maleStudents = $row['male'] ?? 0;
}
$res = $conn->query("SELECT COUNT(*) as female FROM students WHERE gender = 'Female'");
if ($res) {
    $row = $res->fetch_assoc();
    $femaleStudents = $row['female'] ?? 0;
}

// Document statuses for dashboard
$docStatuses = [
    'Not Signed In' => 0,
    'Approved' => 0,
    'Pending' => 0,
    'Rejected' => 0,
];
$statusList = implode("','", array_keys($docStatuses));
$sql = "SELECT status, COUNT(*) AS count FROM documents WHERE status IN ('$statusList') GROUP BY status";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $status = $row['status'];
        $count = (int)$row['count'];
        if (isset($docStatuses[$status])) {
            $docStatuses[$status] = $count;
        }
    }
}

// Programs completion rates for dashboard
$programsCompletion = [];
$sql = "SELECT p.name,
        COUNT(sp.student_id) as total_students,
        SUM(CASE WHEN sp.completed = TRUE THEN 1 ELSE 0 END) as completed_students
        FROM programs p
        LEFT JOIN student_programs sp ON p.id = sp.program_id
        GROUP BY p.id ORDER BY p.name";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $total = (int)$row['total_students'];
        $completed = (int)$row['completed_students'];
        $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
        $programsCompletion[] = [
            'name' => $row['name'],
            'completion' => $percentage,
        ];
    }
}

$conn->close();

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/ojt-management-system/backend/assets/css/admin/dash.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .stat-card {
            flex: 1;
            min-width: 150px;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 0 10px #ddd;
            text-align: center;
        }
        .stat-card h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .chart-container {
            max-width: 800px;
            margin: 0 auto 3rem;
        }
        .dashboard-content h1,
        .dashboard-content p {
            color: #212529;
        }
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
        <li><a href="admin_panel.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li><a href="../documents/documents.php"><i class="bi bi-folder"></i> Documents</a></li>
        <li><a href="../admin/monitoring.php"><i class="bi bi-clipboard-data"></i> Monitoring</a></li>
        <li><a href="../partnership/partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
        <li><a href="../admin/manage.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
        <li><a href="../announcements/admin_inbox.php"><i class="bi bi-envelope-paper"></i> Sent Announcements</a></li>
        <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
    </ul>
</nav>

    <main class="dashboard-content">
        <h1>Welcome to the Dashboard</h1>
        <p>This is where you can see reports, graphs, and other statistics.</p>

        <div class="stats-container">
            <div class="stat-card">
                <h3>Total Students</h3>
                <p class="stat-value"><?php echo $totalStudents; ?></p>
            </div>
            <div class="stat-card">
                <h3>Male Students</h3>
                <p class="stat-value"><?php echo $maleStudents; ?></p>
            </div>
            <div class="stat-card">
                <h3>Female Students</h3>
                <p class="stat-value"><?php echo $femaleStudents; ?></p>
            </div>
        </div>

        <div class="stats-container">
            <?php foreach ($docStatuses as $status => $count): ?>
                <div class="stat-card">
                    <h3><?php echo sanitize($status); ?></h3>
                    <p class="stat-value"><?php echo $count; ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="chart-container">
            <h3>Programs Completion Status</h3>
            <canvas id="sectionChart"></canvas>
        </div>
    </main>

</div>
<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to log out?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
        <a href="../auth/logout.php" class="btn btn-danger">Yes</a>
      </div>
    </div>
  </div>
</div>

<script>
    const ctx = document.getElementById('sectionChart').getContext('2d');
    const data = {
        labels: <?php echo json_encode(array_column($programsCompletion, 'name')); ?>,
        datasets: [{
            label: 'Completion Rate (%)',
            data: <?php echo json_encode(array_column($programsCompletion, 'completion')); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            borderRadius: 5,
            maxBarThickness: 40,
        }]
    };

    const config = {
        type: 'bar',
        data: data,
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: (value) => value + '%'
                    },
                    title: {
                        display: true,
                        text: 'Completion Rate (%)'
                    }
                }
            },
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: context => context.parsed.y + '%'
                    }
                }
            }
        }
    };

    const sectionChart = new Chart(ctx, config);
</script>

</body>

</html>
