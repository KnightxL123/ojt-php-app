<?php
session_start();
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_panel.php');
    } else {
        header('Location: user_panel.php');
    }
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'OJT';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die('Connection failed: '.$conn->connect_error);
}

// Fetch departments for selection
$departments = [];
$result = $conn->query("SELECT id, name FROM departments WHERE status = 'active' AND deleted_at IS NULL");
if ($result) {
    $departments = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch all programs for JavaScript
$programs = [];
$result = $conn->query("SELECT id, name, department_id FROM programs WHERE status = 'active'");
if ($result) {
    $programs = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch all sections for JavaScript
$sections = [];
$result = $conn->query("SELECT id, name, department_id, program_id FROM sections");
if ($result) {
    $sections = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>User Registration</title>
<style>
  body {
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(to bottom, #006400, #008000);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
  }
  .container {
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .register-box {
    background: rgb(173, 173, 173);
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 0 10px rgb(82, 82, 82);
    width: 400px;
  }
  .logo {
    width: 100px;
    margin: 10px;
  }
  h2 {
    margin: 10px 0;
  }
  input[type="text"],
  input[type="email"],
  input[type="password"],
  select {
    width: 80%;
    padding: 10px;
    margin: 10px 0;
    border-radius: 5px;
    border: 1px solid #ccc;
  }
  .register-btn {
    display: block;
    width: 80%;
    padding: 10px;
    background: green;
    color: white;
    text-align: center;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    margin: 10px auto;
    border: none;
    cursor: pointer;
  }
  .register-btn:hover {
    background: darkgreen;
  }
  p {
    margin-top: 10px;
  }
  p a {
    text-decoration: none;
    color: blue;
  }
  .message {
    margin: 10px 0;
  }
  .role-selection {
    margin: 15px 0;
  }
  .role-selection label {
    margin-right: 15px;
  }
  .selection-group {
    display: none;
    margin-bottom: 10px;
  }
</style>
</head>
<body>
  <div class="container">
    <div class="register-box">
      <img src="/ojt-management-system/backend/assets/images/plsplogo.jpg" alt="logo" class="logo">
      <h2>User Registration</h2>
      <?php
      if (isset($_GET['error'])) {
          echo '<div class="message" style="color:red;">' . htmlspecialchars($_GET['error']) . '</div>';
      }
      if (isset($_GET['msg'])) {
          echo '<div class="message" style="color:green;">' . htmlspecialchars($_GET['msg']) . '</div>';
      }
      ?>
      <form action="register_handler.php" method="post" autocomplete="off">
        <input type="text" name="username" placeholder="Choose Username" required autocomplete="username" />
        <input type="email" name="email" placeholder="Your Email" required autocomplete="email" />
        <input type="password" name="password" placeholder="Choose Password" required autocomplete="new-password" />
        <input type="password" name="confirm_password" placeholder="Confirm Password" required autocomplete="new-password" />
        
        <div class="role-selection">
          <label><input type="radio" name="role" value="user" checked> Adviser</label>
          <label><input type="radio" name="role" value="coordinator"> Coordinator</label>
        </div>
        
        <!-- Coordinator Selection (Department only) -->
        <div id="coordinator-selection" class="selection-group">
          <select name="department_id" id="coordinator-dept">
            <option value="">Select Department</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <!-- Adviser Selection (Department > Program > Section) -->
        <div id="adviser-selection" class="selection-group">
          <select name="adviser_department_id" id="adviser-dept">
            <option value="">Select Department</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
            <?php endforeach; ?>
          </select>
          
          <select name="program_id" id="program-select" disabled>
            <option value="">Select Program</option>
          </select>
          
          <select name="section_id" id="section-select" disabled>
            <option value="">Select Section</option>
          </select>
        </div>
        
        <button type="submit" class="register-btn">Register</button>
      </form>
      <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
  </div>

  <script>
    // Convert PHP arrays to JavaScript objects
    const programs = <?php echo json_encode($programs); ?>;
    const sections = <?php echo json_encode($sections); ?>;
    
    // Show/hide selection groups based on role
    document.querySelectorAll('input[name="role"]').forEach(radio => {
      radio.addEventListener('change', function() {
        const coordinatorSelection = document.getElementById('coordinator-selection');
        const adviserSelection = document.getElementById('adviser-selection');
        
        if (this.value === 'coordinator') {
          coordinatorSelection.style.display = 'block';
          adviserSelection.style.display = 'none';
          // Clear adviser selections
          document.getElementById('adviser-dept').value = '';
          document.getElementById('program-select').value = '';
          document.getElementById('program-select').disabled = true;
          document.getElementById('section-select').value = '';
          document.getElementById('section-select').disabled = true;
        } else {
          coordinatorSelection.style.display = 'none';
          adviserSelection.style.display = 'block';
          // Clear coordinator selection
          document.getElementById('coordinator-dept').value = '';
        }
      });
    });
    
    // Initialize - show adviser selection by default
    document.getElementById('adviser-selection').style.display = 'block';
    
    // Department change handler for adviser
    document.getElementById('adviser-dept').addEventListener('change', function() {
      const deptId = this.value;
      const programSelect = document.getElementById('program-select');
      const sectionSelect = document.getElementById('section-select');
      
      // Clear and disable dependent selects
      programSelect.innerHTML = '<option value="">Select Program</option>';
      programSelect.disabled = !deptId;
      sectionSelect.innerHTML = '<option value="">Select Section</option>';
      sectionSelect.disabled = true;
      
      if (deptId) {
        // Filter programs by department
        const deptPrograms = programs.filter(p => p.department_id == deptId);
        
        // Populate programs
        deptPrograms.forEach(program => {
          const option = document.createElement('option');
          option.value = program.id;
          option.textContent = program.name;
          programSelect.appendChild(option);
        });
      }
    });
    
    // Program change handler for adviser
    document.getElementById('program-select').addEventListener('change', function() {
      const programId = this.value;
      const sectionSelect = document.getElementById('section-select');
      
      // Clear and disable dependent selects
      sectionSelect.innerHTML = '<option value="">Select Section</option>';
      sectionSelect.disabled = !programId;
      
      if (programId) {
        // Filter sections by program
        const programSections = sections.filter(s => s.program_id == programId);
        
        // Populate sections
        programSections.forEach(section => {
          const option = document.createElement('option');
          option.value = section.id;
          option.textContent = section.name;
          sectionSelect.appendChild(option);
        });
      }
    });
  </script>
</body>
</html>