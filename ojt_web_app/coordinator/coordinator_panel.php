<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php?error=' . urlencode("Please log in to access the dashboard."));
    exit;
}

// Only allow coordinators to access this dashboard
if ($_SESSION['role'] !== 'coordinator') {
    header('Location: login.php?error=' . urlencode("Unauthorized access. Coordinator role required."));
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

// Check if department is active
$stmt = $conn->prepare("SELECT status FROM departments WHERE id = ?");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $dept_status = $result->fetch_assoc()['status'];
    
    if ($dept_status === 'inactive') {
        // Department has been deactivated since login
        session_destroy();
        header('Location: login.php?error=' . urlencode("Your department has been deactivated. Please contact administrator."));
        exit;
    }
}
$stmt->close();

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

// Initialize all data variables
$totalStudents = 0;
$maleStudents = 0;
$femaleStudents = 0;
$programStats = [];
$docStatusCounts = [];
$monitoringTrends = [];
$recentDocuments = [];
$recentMonitoring = [];
$documentCompletionStats = [];
$studentHoursStats = [];

// Get total students in department
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM students s
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.department_id = ?
");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $totalStudents = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Get male students count
$stmt = $conn->prepare("
    SELECT COUNT(*) as male 
    FROM students s
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.department_id = ? AND s.gender = 'Male'
");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $maleStudents = $result->fetch_assoc()['male'] ?? 0;
$stmt->close();

// Get female students count
$stmt = $conn->prepare("
    SELECT COUNT(*) as female 
    FROM students s
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.department_id = ? AND s.gender = 'Female'
");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $femaleStudents = $result->fetch_assoc()['female'] ?? 0;
$stmt->close();

// Get program statistics
$stmt = $conn->prepare("
    SELECT p.name, 
           COUNT(st.id) as total_students,
           SUM(CASE WHEN st.gender = 'Male' THEN 1 ELSE 0 END) as male,
           SUM(CASE WHEN st.gender = 'Female' THEN 1 ELSE 0 END) as female
    FROM programs p
    LEFT JOIN sections sec ON p.id = sec.program_id AND sec.department_id = ?
    LEFT JOIN students st ON sec.id = st.section_id
    WHERE p.department_id = ?
    GROUP BY p.id, p.name
");
$stmt->bind_param("ii", $department_id, $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $programStats[] = [
            'name' => $row['name'],
            'total' => (int)$row['total_students'],
            'male' => (int)$row['male'],
            'female' => (int)$row['female']
        ];
    }
}
$stmt->close();

// Get document status counts for department
$stmt = $conn->prepare("
    SELECT 
        d.status, 
        COUNT(*) as count 
    FROM documents d
    JOIN students s ON d.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.department_id = ?
    GROUP BY d.status
");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $docStatusCounts[$row['status']] = (int)$row['count'];
    }
}
$stmt->close();

// Get monitoring trends for department (last 7 days)
$stmt = $conn->prepare("
    SELECT 
        DATE(m.created_at) as date, 
        COUNT(*) as count 
    FROM monitoring m
    JOIN students s ON m.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.department_id = ? 
    AND m.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(m.created_at)
");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $monitoringTrends[$row['date']] = (int)$row['count'];
    }
}
$stmt->close();

// Get recent documents for department
$stmt = $conn->prepare("
    SELECT 
        d.document_name, 
        d.status, 
        s.name as student_name,
        d.updated_at
    FROM documents d
    JOIN students s ON d.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.department_id = ?
    ORDER BY d.updated_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentDocuments[] = [
            'document' => $row['document_name'],
            'status' => $row['status'],
            'student' => $row['student_name'],
            'date' => $row['updated_at']
        ];
    }
}
$stmt->close();

// Get recent monitoring for department
$stmt = $conn->prepare("
    SELECT 
        m.activity, 
        m.status, 
        s.name as student_name,
        m.created_at
    FROM monitoring m
    JOIN students s ON m.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.department_id = ?
    ORDER BY m.created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentMonitoring[] = [
            'activity' => $row['activity'],
            'status' => $row['status'],
            'student' => $row['student_name'],
            'date' => $row['created_at']
        ];
    }
}
$stmt->close();

// Get document completion stats
$stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN d.certificate_of_completion = 'Approved' THEN 1 END) as coc,
        COUNT(CASE WHEN d.daily_time_record = 'Approved' THEN 1 END) as dtr,
        COUNT(CASE WHEN d.performance_evaluation = 'Approved' THEN 1 END) as pe,
        COUNT(CASE WHEN d.narrative_report = 'Approved' THEN 1 END) as nr,
        COUNT(CASE WHEN d.printed_journal = 'Approved' THEN 1 END) as pj,
        COUNT(CASE WHEN d.company_profile = 'Approved' THEN 1 END) as cp,
        COUNT(CASE WHEN d.ojt_program_evaluation = 'Approved' THEN 1 END) as ope,
        COUNT(CASE WHEN d.ojt_evaluation_form = 'Approved' THEN 1 END) as oef
    FROM documents d
    JOIN students s ON d.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.department_id = ?
");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $documentCompletionStats = [
        'Certificate of Completion' => $row['coc'] ?? 0,
        'Daily Time Record' => $row['dtr'] ?? 0,
        'Performance Evaluation' => $row['pe'] ?? 0,
        'Narrative Report' => $row['nr'] ?? 0,
        'Printed Journal' => $row['pj'] ?? 0,
        'Company Profile' => $row['cp'] ?? 0,
        'OJT Program Evaluation' => $row['ope'] ?? 0,
        'OJT Evaluation Form' => $row['oef'] ?? 0
    ];
}
$stmt->close();

// Get student hours statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN s.hours_completed >= s.total_hours THEN 1 END) as completed,
        COUNT(CASE WHEN s.hours_completed >= s.total_hours * 0.75 AND s.hours_completed < s.total_hours THEN 1 END) as almost_completed,
        COUNT(CASE WHEN s.hours_completed >= s.total_hours * 0.5 AND s.hours_completed < s.total_hours * 0.75 THEN 1 END) as halfway,
        COUNT(CASE WHEN s.hours_completed < s.total_hours * 0.5 THEN 1 END) as behind
    FROM students s
    JOIN sections sec ON s.section_id = sec.id
    WHERE sec.department_id = ?
");
$stmt->bind_param("i", $department_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $row = $result->fetch_assoc();
    $studentHoursStats = [
        'Completed' => $row['completed'] ?? 0,
        'Almost Completed (75-99%)' => $row['almost_completed'] ?? 0,
        'Halfway (50-74%)' => $row['halfway'] ?? 0,
        'Behind (<50%)' => $row['behind'] ?? 0
    ];
}
$stmt->close();

function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo sanitize($department_name); ?> Coordinator Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
        }
        .header {
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 30px;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
        }
        .header-logo {
            height: 80px;
        }
        .search-bar input {
            width: 300px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .header-icons a {
            font-size: 24px;
            color: #333;
            margin-left: 15px;
            text-decoration: none;
        }
        .header-icons a:hover {
            color: #28a745;
        }
        .main-container {
            display: flex;
            min-height: calc(100vh - 110px);
        }
        .sidebar {
            width: 250px;
            background: #0da80d;
            color: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            margin: 15px;
        }
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
        .dashboard-content {
            flex-grow: 1;
            padding: 30px;
        }
        .program-card {
            border-left: 4px solid #36A2EB;
            margin-bottom: 15px;
        }
        .stat-card {
            transition: transform 0.3s;
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
            text-align: center;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
<header class="header">
    <img src="../image/PLSP.png" alt="Logo" class="header-logo">
    <div class="header-title">
        <h2><?php echo sanitize($department_name); ?> Department</h2>
        <p>Coordinator Dashboard</p>
    </div>
    <div class="header-icons">
        <a href="#"><i class="bi bi-bell"></i></a>
        <a href="profile.php"><i class="bi bi-person-circle"></i></a>
    </div>
</header>
<div class="main-container">
    <nav class="sidebar">
        <ul>
            <li><a href="coordinator_panel.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="coordinator_announcement.php"><i class="bi bi-megaphone"></i> Announcement</a></li>
            <li><a href="coordinator_monitor.php"><i class="bi bi-clipboard-data"></i> Monitor</a></li>
            <li><a href="coordinator_documents.php"><i class="bi bi-folder"></i> Documents</a></li>
            <li><a href="coordinator_managesection.php"><i class="bi bi-people"></i> Manage Section</a></li>
            <li><a href="coordinator_partnership.php"><i class="bi bi-handshake"></i> Partnership</a></li>
            <li><a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
        </ul>
    </nav>

    <main class="dashboard-content">
        <h1><?php echo sanitize($department_name); ?> Department Overview</h1>
        
        <!-- Department Summary Cards -->
        <div class="stats-container">
            <div class="stat-card bg-primary">
                <h3>Total Students</h3>
                <p class="stat-value"><?php echo $totalStudents; ?></p>
            </div>
            <div class="stat-card bg-info">
                <h3>Male Students</h3>
                <p class="stat-value"><?php echo $maleStudents; ?></p>
            </div>
            <div class="stat-card bg-danger">
                <h3>Female Students</h3>
                <p class="stat-value"><?php echo $femaleStudents; ?></p>
            </div>
        </div>
        
        <!-- Program Statistics -->
        <div class="chart-container">
            <h3>Program Statistics</h3>
            <div class="row">
                <?php foreach ($programStats as $program): ?>
                <div class="col-md-4">
                    <div class="card program-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo sanitize($program['name']); ?></h5>
                            <p class="card-text">
                                Total: <?php echo $program['total']; ?><br>
                                Male: <?php echo $program['male']; ?><br>
                                Female: <?php echo $program['female']; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Document Status Charts -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h3>Document Status</h3>
                    <canvas id="documentChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h3>Document Completion Status</h3>
                    <canvas id="documentCompletionChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Monitoring and Hours Charts -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h3>Monitoring Activity (Last 7 Days)</h3>
                    <canvas id="monitoringChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h3>Student Hours Completion</h3>
                    <canvas id="hoursChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity Section -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h3>Recent Documents</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Student</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDocuments as $doc): ?>
                                <tr>
                                    <td><?php echo sanitize($doc['document']); ?></td>
                                    <td><?php echo sanitize($doc['student']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $doc['status'] === 'Approved' ? 'success' : 
                                                 ($doc['status'] === 'Rejected' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo sanitize($doc['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h3>Recent Monitoring</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Student</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentMonitoring as $mon): ?>
                                <tr>
                                    <td><?php echo sanitize($mon['activity']); ?></td>
                                    <td><?php echo sanitize($mon['student']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $mon['status'] === 'Completed' ? 'success' : 
                                                 ($mon['status'] === 'Pending' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo sanitize($mon['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Document Status Pie Chart
new Chart(document.getElementById('documentChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_keys($docStatusCounts)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($docStatusCounts)); ?>,
            backgroundColor: [
                '#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
            }
        }
    }
});

// Document Completion Bar Chart
new Chart(document.getElementById('documentCompletionChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_keys($documentCompletionStats)); ?>,
        datasets: [{
            label: 'Completed Documents',
            data: <?php echo json_encode(array_values($documentCompletionStats)); ?>,
            backgroundColor: '#36A2EB'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Monitoring Activity Line Chart
new Chart(document.getElementById('monitoringChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_keys($monitoringTrends)); ?>,
        datasets: [{
            label: 'Monitoring Entries',
            data: <?php echo json_encode(array_values($monitoringTrends)); ?>,
            borderColor: '#36A2EB',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            tension: 0.1,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Student Hours Completion Doughnut Chart
new Chart(document.getElementById('hoursChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_keys($studentHoursStats)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($studentHoursStats)); ?>,
            backgroundColor: [
                '#4BC0C0', '#36A2EB', '#FFCE56', '#FF6384'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
            }
        }
    }
});
</script>
</body>

</html>
