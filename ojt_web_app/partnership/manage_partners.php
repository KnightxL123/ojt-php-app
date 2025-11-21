<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$host = 'localhost';
$dbname = 'OJT';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$message = '';
$editData = null; // Initialize the variable

// Delete partner
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM partners WHERE id = ?");
    $stmt->bind_param('i', $delete_id);
    if ($stmt->execute()) {
        $message = "Partner deleted successfully.";
    } else {
        $message = "Error deleting partner.";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = intval($_POST['year'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $website_url = trim($_POST['website_url'] ?? '');

    if ($year >= 2000 && $year <= 2100 && $name !== '') {
        // Prevent duplicate
        $stmt = $conn->prepare("SELECT id FROM partners WHERE year = ? AND name = ?");
        $stmt->bind_param('is', $year, $name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $message = "Partner for year $year named '$name' already exists.";
            $stmt->close(); // Keep this here
        } else {
            $stmt->close(); // Close before creating new statement

            $insert = $conn->prepare("INSERT INTO partners (year, name, logo_url, website_url) VALUES (?, ?, ?, ?)");
            $insert->bind_param('isss', $year, $name, $logo_url, $website_url);
            if ($insert->execute()) {
                $message = "Partner added successfully.";
            } else {
                $message = "Failed to add partner.";
            }
            $insert->close();
        }
    } else {
        $message = "Please enter a valid year and partner name.";
    }
}

// Fetch partner data for editing
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt = $conn->prepare("SELECT id, year, name, logo_url, website_url FROM partners WHERE id = ?");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $editData = $res->fetch_assoc(); // Fetch the partner data
    $stmt->close();
}

// Fetch partners grouped by year
$partners = [];
$res = $conn->query("SELECT id, year, name, logo_url, website_url FROM partners ORDER BY year DESC, name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $partners[$row['year']][] = $row;
    }
}

$conn->close();

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Partners</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
    <style>
        .container { max-width: 900px; margin: 30px auto; }
        .partner-grid { display: flex; flex-direction: column; gap: 1rem; }
        .partner-card {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            background: #f9f9f9;
            position: relative;
        }
        .partner-card img {
            max-width: 100%;
            height: 80px;
            object-fit: contain;
            margin-bottom: 10px;
        }
        .partner-card a.partner-website {
            display: block;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }
        .action-buttons {
            position: absolute;
            top: 5px;
            right: 5px;
        }
        .action-buttons a {
            margin-left: 5px;
            cursor: pointer;
            color: black;
        }
    </style>
    <script>
    function confirmDelete(id){
        if(confirm('Are you sure you want to delete this partner?')){
            window.location.href = '?delete_id=' + id;
        }
    }
    </script>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="partnership.php" class="btn btn-secondary">Back</a>
        <h1 class="text-center flex-grow-1">Manage Partners</h1>
    </div>

    <?php if($message): ?>
        <div class="alert alert-info"><?php echo e($message); ?></div>
    <?php endif; ?>

    <div>
        <form method="POST" class="mb-4">
            <?php if($editData): ?>
                <input type="hidden" name="edit_id" value="<?php echo e($editData['id']); ?>">
            <?php endif; ?>
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="year" class="col-form-label">Year</label>
                    <input type="number" id="year" name="year" class="form-control" min="2000" max="2100" required placeholder="e.g. 2024" value="<?php echo e($editData['year'] ?? ''); ?>" />
                </div>
                <div class="col-auto">
                    <label for="name" class="col-form-label">Partner Name</label>
                    <input type="text" id="name" name="name" class="form-control" required placeholder="Partner name" value="<?php echo e($editData['name'] ?? ''); ?>" />
                </div>
            </div>
            <div class="row g-3 align-items-center mt-2">
                <div class="col-auto">
                    <label for="logo_url" class="col-form-label">Logo URL</label>
                    <input type="text" id="logo_url" name="logo_url" class="form-control" placeholder="URL of logo image (optional)" value="<?php echo e($editData['logo_url'] ?? ''); ?>" />
                </div>
                <div class="col-auto">
                    <label for="website_url" class="col-form-label">Website URL</label>
                    <input type="text" id="website_url" name="website_url" class="form-control" placeholder="URL of partner website (optional)" value="<?php echo e($editData['website_url'] ?? ''); ?>" />
                </div>
                <div class="row g-3 align-items-center mt-2">
                    <button type="submit" class="btn btn-primary"><?php echo $editData ? 'Save Changes' : 'Add Partner'; ?></button>
                    <?php if($editData): ?>
                        <a href="manage_partners.php" class="btn btn-secondary ms-2">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <?php if(empty($partners)): ?>
        <p>No partners found.</p>
    <?php else: ?>
        <?php foreach($partners as $year => $yearPartners): ?>
            <h3>Year <?php echo e($year); ?></h3>
            <div class="partner-grid mb-4">
                <?php foreach($yearPartners as $partner): ?>
                    <div class="partner-card">
                        <div class="action-buttons">
                            <a href="?edit_id=<?php echo e($partner['id']); ?>" title="Edit Partner">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="javascript:confirmDelete(<?php echo e($partner['id']); ?>)" title="Delete Partner">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                        <?php if($partner['logo_url']): ?>
                            <img src="<?php echo e($partner['logo_url']); ?>" alt="<?php echo e($partner['name']); ?> Logo" />
                        <?php else: ?>
                            <i class="bi bi-building" style="font-size:48px; margin-bottom:10px;"></i>
                        <?php endif; ?>
                        <p><?php echo e($partner['name']); ?></p>
                        <?php if($partner['website_url']): ?>
                            <a href="<?php echo e($partner['website_url']); ?>" target="_blank" rel="noopener" class="partner-website">Website</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>