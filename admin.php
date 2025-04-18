<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

session_start();
require './internal/db.php';

$admin_password = getenv('ADMIN_PASSWORD'); // Change for security

// Admin Authentication
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password']) && $_POST['password'] == $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Login</title>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
            <style>
                :root {
                    --primary: #4361ee;
                    --primary-dark: #3a56d4;
                    --secondary: #2b2d42;
                    --light: #f8f9fa;
                    --grey: #e9ecef;
                    --dark: #212529;
                }
                body {
                    font-family: "Poppins", sans-serif;
                    background-color: var(--grey);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .login-container {
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
                    width: 400px;
                    padding: 40px;
                    text-align: center;
                }
                .login-container h1 {
                    color: var(--primary);
                    margin-bottom: 30px;
                }
                .login-form input {
                    width: 100%;
                    padding: 12px 15px;
                    margin: 10px 0;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    font-size: 16px;
                    transition: border-color 0.3s;
                    box-sizing: border-box;
                }
                .login-form input:focus {
                    border-color: var(--primary);
                    outline: none;
                }
                .login-form button {
                    background-color: var(--primary);
                    color: white;
                    border: none;
                    padding: 12px 0;
                    width: 100%;
                    border-radius: 5px;
                    font-size: 16px;
                    cursor: pointer;
                    margin-top: 20px;
                    transition: background-color 0.3s;
                }
                .login-form button:hover {
                    background-color: var(--primary-dark);
                }
                .icon {
                    font-size: 48px;
                    color: var(--primary);
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <i class="fas fa-user-shield icon"></i>
                <h1>Admin Access</h1>
                <form method="POST" class="login-form">
                    <input type="password" name="password" placeholder="Enter Admin Password" required>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>';
        exit;
    }
}




// Logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Add Student
if (isset($_POST['add_student'])) {
    $name = $_POST['name'];
    $id = $_POST['student_id'];
    $stmt = $pdo->prepare("INSERT INTO students (id, name) VALUES (?, ?)");
    $stmt->execute([$id, $name]);
}

// Remove Student
if (isset($_POST['remove_student'])) {
    $id = $_POST['student_id'];
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$id]);
}

// Fetch All Students
$students = $pdo->query("SELECT * FROM students ORDER BY name ASC")->fetchAll();

// Get weekly progress data for all students (for the past 7 days)
// This assumes you have a 'study_logs' table with fields: id, student_id, date, minutes_studied
function getWeeklyProgressData($pdo) {
    $query = "SELECT student_id, 
              DATE(log_date) as day, 
              SUM(minutes_studied)/60 as hours_studied 
              FROM study_logs 
              WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
              GROUP BY student_id, day 
              ORDER BY day";
    return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
}

// Try to get the progress data, handle if table doesn't exist
try {
    $weeklyProgressData = getWeeklyProgressData($pdo);
} catch (PDOException $e) {
    $weeklyProgressData = [];
}

// Format data for charts
$chartData = [];
foreach ($students as $student) {
    $chartData[$student['id']] = [
        'name' => $student['name'],
        'days' => [],
        'hours' => []
    ];
}

// Last 7 days for X-axis labels
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $days[] = date('D', strtotime($date));
}

// Process weekly progress data
foreach ($weeklyProgressData as $entry) {
    $day = date('D', strtotime($entry['day']));
    $studentId = $entry['student_id'];
    $hours = $entry['hours_studied'];
    
    if (isset($chartData[$studentId])) {
        $index = array_search($day, $days);
        if ($index !== false) {
            $chartData[$studentId]['days'][$index] = $day;
            $chartData[$studentId]['hours'][$index] = $hours;
        }
    }
}

// Fill in missing days with zero hours
foreach ($chartData as $studentId => $data) {
    for ($i = 0; $i < 7; $i++) {
        if (!isset($data['days'][$i])) {
            $chartData[$studentId]['days'][$i] = $days[$i];
            $chartData[$studentId]['hours'][$i] = 0;
        }
    }
    // Sort by day
    ksort($chartData[$studentId]['days']);
    ksort($chartData[$studentId]['hours']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Hours Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4cc9f0;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --secondary: #2b2d42;
            --light: #f8f9fa;
            --grey: #e9ecef;
            --dark: #212529;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--grey);
            color: var(--dark);
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--secondary);
            color: white;
            position: fixed;
            height: 100%;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            margin-top: 10px;
            font-weight: 600;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 15px 20px;
            color: #ddd;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .header h2 {
            color: var(--primary);
        }
        
        .header-actions form {
            display: inline-block;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        /* Dashboard Stats */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 32px;
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .stat-card h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #777;
            font-size: 16px;
            margin-bottom: 0;
        }
        
        /* Student Management */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .card-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--grey);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--grey);
        }
        
        .table th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--secondary);
        }
        
        .table tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .table input[type="number"] {
            width: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        
        /* Progress Bar */
        .progress-container {
            width: 100%;
            background-color: var(--grey);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 8px;
            background-color: var(--primary);
            transition: width 0.4s ease;
        }
        
        /* Charts */
        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 20px;
            position: relative;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .sidebar-header h3, .sidebar .menu-text {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .chart-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
        
        /* Progress Circle */
        .progress-circle-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .progress-circle {
            position: relative;
            width: 60px;
            height: 60px;
        }
        
        .progress-circle svg {
            transform: rotate(-90deg);
        }
        
        .progress-circle circle {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
        }
        
        .progress-circle .bg {
            stroke: var(--grey);
        }
        
        .progress-circle .progress {
            stroke: var(--primary);
            stroke-dasharray: 167;
            transition: stroke-dashoffset 0.5s;
        }
        
        .progress-circle .percentage {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
            font-weight: 600;
        }
        
        /* Student detail modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 800px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            animation: modalopen 0.3s;
        }
        
        @keyframes modalopen {
            from {opacity: 0; transform: translateY(-50px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--grey);
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--dark);
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--grey);
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        
        .tab.active {
            border-bottom: 2px solid var(--primary);
            color: var(--primary);
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-graduation-cap fa-2x"></i>
                <h3>Study Tracker</h3>
                <h5>GOD MODE</h2>
            </div>
            <div class="sidebar-menu">
                <a href="#" class="active"><i class="fas fa-th-large"></i> <span class="menu-text">Dashboard</span></a>
                <!-- <a href="#"><i class="fas fa-users"></i> <span class="menu-text">Students</span></a>
                <a href="#"><i class="fas fa-chart-line"></i> <span class="menu-text">Reports</span></a>
                <a href="#"><i class="fas fa-cog"></i> <span class="menu-text">Settings</span></a> -->
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2>Study Hours Dashboard</h2>
                <div class="header-actions">
                    <form method="POST">
                        <button type="submit" name="logout" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Stats Overview -->
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo count($students); ?></h3>
                    <p>Total Students</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <?php
                    // Calculate total weekly hours required
                    $totalWeeklyHours = 0;
                    foreach ($students as $student) {
                        $totalWeeklyHours += $student['weekly_hours'] ?? 0;
                    }
                    ?>
                    <h3><?php echo $totalWeeklyHours; ?></h3>
                    <p>Weekly Hours Target</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-chart-line"></i>
                    <?php
                    // Calculate total current hours
                    $totalCurrentHours = 0;
                    foreach ($students as $student) {
                        $totalCurrentHours += ($student['current_hours'] ?? 0) / 60; // Convert minutes to hours
                    }
                    ?>
                    <h3><?php echo round($totalCurrentHours, 1); ?></h3>
                    <p>Current Hours</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-trophy"></i>
                    <?php
                    // Calculate completion percentage
                    $completionPercentage = $totalWeeklyHours > 0 ? 
                        round(($totalCurrentHours / $totalWeeklyHours) * 100, 1) : 0;
                    ?>
                    <h3><?php echo $completionPercentage; ?>%</h3>
                    <p>Completion Rate</p>
                </div>
            </div>
            
            <!-- Student Management -->
            <div class="card">
                <div class="card-header">
                    <h3>Add New Student</h3>
                </div>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="student_id">Student ID</label>
                            <input type="number" id="student_id" name="student_id" class="form-control" placeholder="Enter student ID" required>
                        </div>
                        <div class="form-group">
                            <label for="name">Student Name</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Enter student name" required>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" name="add_student" class="btn btn-primary form-control">
                                <i class="fas fa-plus"></i> Add Student
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Student List -->
            <div class="card">
                <div class="card-header">
                    <h3>Student List</h3>
                    <div>
                        <input type="text" id="searchStudent" class="form-control" placeholder="Search students..." style="width: 250px;">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Weekly Target (Hours)</th>
                                <th>Current Progress (Hours)</th>
                                <th>Completion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $studentId = $student['id'];
                                $weeklyHours = $student['weekly_hours'] ?? 0;
                                // Convert current_hours (in minutes) back to hours for display
                                $currentHoursInHours = round(($student['current_hours'] ?? 0) / 60, 1);
                                $completionPercentage = $weeklyHours > 0 ? 
                                    min(round(($currentHoursInHours / $weeklyHours) * 100), 100) : 0;
                                
                                // Determine color based on completion percentage
                                $progressColor = 'var(--danger)';
                                if ($completionPercentage >= 75) {
                                    $progressColor = 'var(--success)';
                                } else if ($completionPercentage >= 50) {
                                    $progressColor = 'var(--primary)';
                                } else if ($completionPercentage >= 25) {
                                    $progressColor = 'var(--warning)';
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($studentId); ?></td>
                                    <td><?php echo htmlspecialchars($student['name'] ?? ''); ?></td>
                                    <td>
                                        <input type="number" min="0" step="0.5" value="<?php echo htmlspecialchars($weeklyHours); ?>"
                                               onblur="updateHours(<?php echo $studentId; ?>, parseFloat(this.value), 'weekly_hours')">
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.5" value="<?php echo htmlspecialchars($currentHoursInHours); ?>"
                                               onblur="updateHours(<?php echo $studentId; ?>, this.value * 60, 'current_hours')">
                                    </td>
                                    <td>
                                        <div class="progress-circle-container">
                                            <div class="progress-circle">
                                                <svg height="60" width="60">
                                                    <circle class="bg" cx="30" cy="30" r="27"></circle>
                                                    <circle class="progress" cx="30" cy="30" r="27" 
                                                            style="stroke: <?php echo $progressColor; ?>; 
                                                                   stroke-dashoffset: <?php echo 167 - (167 * $completionPercentage / 100); ?>">
                                                    </circle>
                                                </svg>
                                                <div class="percentage"><?php echo $completionPercentage; ?>%</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-primary" onclick="showStudentDetails(<?php echo $studentId; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                                            <button type="submit" name="remove_student" class="btn btn-danger" 
                                                    onclick="return confirm('Are you sure you want to remove this student?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Student Details Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalStudentName">Student Details</h3>
                <span class="close">&times;</span>
            </div>
            <div class="tabs">
                <div class="tab active" data-tab="overview">Overview</div>
            </div>
            <div class="tab-content active" id="overview">
                <div class="card">
                    <h4>Study Summary</h4>
                    <div id="studentSummary">
                        <!-- Filled by JavaScript -->
                    </div>
                </div>
            </div>
            <div class="tab-content" id="progress">
                <canvas id="studentProgressChart"></canvas>
            </div>
            <div class="tab-content" id="notes">
                <div class="form-group">
                    <label>Add Note</label>
                    <textarea class="form-control" rows="4" placeholder="Add notes about this student..."></textarea>
                </div>
                <button class="btn btn-primary">Save Note</button>
                <div class="card" style="margin-top: 20px;">
                    <h4>Previous Notes</h4>
                    <p>No notes available yet.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Update Hours AJAX Function
        function updateHours(id, newHours, type) {
            // Prepare data to send
            const payload = {
                id: id,
                newHours: newHours,
                type: type
            };

            // Send AJAX request
            fetch('/api.php/updateHours', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    location.reload(); // Refresh the page on success
                } else {
                    alert('Error updating hours: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('There was an error updating the hours.');
            });
        }

        // Show Student Details Modal
        // Function to show student details in modal
        function showStudentDetails(studentId) {
            // Clear previous data
            document.getElementById('studentSummary').innerHTML = '';
            
            // Show the modal
            const modal = document.getElementById('studentModal');
            modal.style.display = 'block';
            
            // Fetch student details from the API
            fetch(`/api.php/student/${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }

                    console.log(data)

                    // Update modal title with student name
                    document.getElementById('modalStudentName').textContent = data.student.name;

                    // Fill student summary
                    const summaryHTML = `
                        <div class="stats-container" style="margin-top: 15px;">
                            <div class="stat-card">
                                <i class="fas fa-clock"></i>
                                <h3>${data.student.weekly_hours}</h3>
                                <p>Weekly Target</p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-chart-line"></i>
                                <h3>${data.student.current_hours_display}</h3>
                                <p>Current Hours</p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-percentage"></i>
                                <h3>${data.student.completion_percentage}%</h3>
                                <p>Completion Rate</p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-calendar-check"></i>
                                <h3>${data.summary.days_active}/7</h3>
                                <p>Active Days</p>
                            </div>
                        </div>
                        <div style="margin-top: 20px;">
                            <h4>Weekly Summary</h4>
                            <p>Average daily study: <strong>${data.summary.average_daily_hours.toFixed(1)} hours</strong></p>
                            <p>Best day: <strong>${data.summary.highest_day.day} (${data.summary.highest_day.hours.toFixed(1)} hours)</strong></p>
                        </div>
                    `;
                    document.getElementById('studentSummary').innerHTML = summaryHTML;

                    // Update progress chart
                    updateStudentProgressChart(data.progress_chart);

                    // Update notes section
                    updateNotesSection(data.notes, studentId);
                })
                .catch(error => {
                    console.error('Error fetching student details:', error);
                    alert('Error loading student details. Please try again.');
                });
                    
        }

        // Update the student progress chart
        function updateStudentProgressChart(chartData) {
            const ctx = document.getElementById('studentProgressChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (window.studentChart) {
                window.studentChart.destroy();
            }
            
            window.studentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Hours Studied',
                        data: chartData.data,
                        backgroundColor: 'rgba(67, 97, 238, 0.7)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Hours'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Day'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Daily Study Hours (Past Week)',
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });
        }

        // Update notes section
        function updateNotesSection(notes, studentId) {
            const notesTab = document.getElementById('notes');
            
            // Keep the form at the top
            const existingForm = notesTab.querySelector('.form-group').outerHTML + 
                                notesTab.querySelector('.btn').outerHTML;
            
            let notesHTML = '';
            
            if (notes && notes.length > 0) {
                notesHTML = '<div class="card" style="margin-top: 20px;"><h4>Previous Notes</h4><div class="notes-list">';
                
                notes.forEach(note => {
                    const noteDate = new Date(note.created_at).toLocaleString();
                    notesHTML += `
                        <div class="note-item" style="border-bottom: 1px solid #eee; padding: 10px 0;">
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #777; font-size: 12px;">${noteDate}</span>
                                <button onclick="deleteNote(${note.id}, ${studentId})" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <p style="margin-top: 5px;">${note.note_text}</p>
                        </div>
                    `;
                });
                
                notesHTML += '</div></div>';
            } else {
                notesHTML = '<div class="card" style="margin-top: 20px;"><h4>Previous Notes</h4><p>No notes available yet.</p></div>';
            }
            
            // Update form action to include student ID
            const formHTML = `
                <div class="form-group">
                    <label>Add Note</label>
                    <textarea id="noteText" class="form-control" rows="4" placeholder="Add notes about this student..."></textarea>
                </div>
                <button class="btn btn-primary" onclick="addNote(${studentId})">Save Note</button>
            `;
            
            notesTab.innerHTML = formHTML + notesHTML;
        }

        // Add a note
        function addNote(studentId) {
            const noteText = document.getElementById('noteText').value.trim();
            
            if (!noteText) {
                alert('Please enter a note before saving.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_note');
            formData.append('student_id', studentId);
            formData.append('note_text', noteText);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh student details to show the new note
                    showStudentDetails(studentId);
                    // Clear the note textarea
                    document.getElementById('noteText').value = '';
                } else {
                    alert(data.error || 'Failed to add note. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error adding note:', error);
                alert('An error occurred while adding the note.');
            });
        }

        // Delete a note
        function deleteNote(noteId, studentId) {
            if (!confirm('Are you sure you want to delete this note?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_note');
            formData.append('note_id', noteId);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh student details to update notes list
                    showStudentDetails(studentId);
                } else {
                    alert(data.error || 'Failed to delete note. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error deleting note:', error);
                alert('An error occurred while deleting the note.');
            });
        }

        // Close the modal when the user clicks on the close button
        document.querySelector('.close').addEventListener('click', function() {
            document.getElementById('studentModal').style.display = 'none';
        });
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('studentModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        // Search Functionality
        document.getElementById('searchStudent').addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.table tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let found = false;
                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(filter)) {
                        found = true;
                    }
                });
                row.style.display = found ? '' : 'none';
            });
        });
    </script>
</body>
</html>