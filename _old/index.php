<?php
session_start();
require 'db.php';

// The rest of your PHP logic and HTML below...
if (!isset($_SESSION['student_id'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $student_id = $_POST['student_id'];
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();

        if ($student) {
            $_SESSION['student_id'] = $student_id;
            $_SESSION['name'] = $student['name'];
            // Redirect to avoid form resubmission
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Invalid Student ID";
        }
    }
} 

// Fetch student data if logged in
$weekly_hours = 0;
$current_hours = 0;
// leftover minutes for display
$current_minutes = 0;
if (isset($_SESSION['student_id'])) {
    $stmt = $pdo->prepare("SELECT weekly_hours, current_hours FROM students WHERE id = ?");
    $stmt->execute([$_SESSION['student_id']]);
    $student_data = $stmt->fetch();
    $weekly_hours = round($student_data['weekly_hours'], 1);
    // current hours is the total in minutes without any decimal
    $current_hours = floor($student_data['current_hours'] / 60);
    // current minutes is the leftover minutes
    $current_minutes = $student_data['current_hours'] % 60;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Hour Tracker</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: var(--secondary);
            color: white;
            padding: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .app-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .logout-link {
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 4px;
            background-color: rgba(255,255,255,0.1);
            transition: background-color 0.3s;
        }
        
        .logout-link:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .main-content {
            flex: 1;
            padding: 2rem 0;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .login-form {
            max-width: 400px;
            margin: 3rem auto;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s, transform 0.1s;
        }
        
        .btn:active {
            transform: scale(0.98);
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #d13539;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .stat-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 2.5rem;
            opacity: 0.2;
            color: var(--primary);
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--dark);
        }
        
        .timer-display {
            font-size: 2.5rem;
            font-weight: bold;
            text-align: center;
            margin: 1.5rem 0;
            color: var(--primary);
            font-family: monospace;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .alert {
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="app-title">Study Hour Tracker</div>
            <?php if (isset($_SESSION['student_id'])): ?>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="logout.php" class="logout-link">Logout</a>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="container main-content">
        <?php if (!isset($_SESSION['student_id'])): ?>
            <div class="card login-form">
                <h2 style="margin-bottom: 1.5rem; text-align: center;">Student Login</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input type="number" id="student_id" name="student_id" class="form-control" placeholder="Enter your Student ID" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                </form>
            </div>
        <?php else: ?>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-calendar-week stat-icon"></i>
                    <div class="stat-title">WEEKLY STUDY HOURS</div>
                    <div class="stat-value" id="weekly-hours"><?php echo $weekly_hours; ?> hours.</div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-clock stat-icon"></i>
                    <div class="stat-title">CURRENT PROGRESS</div>
                    <div class="stat-value" id="current-progress"><?php echo $current_hours; ?> hours <?php if($current_minutes != 0) { echo 'and ' . $current_minutes . ' minutes.'; } else { echo '.'; } ?></div>
                </div>
            </div>
            
            <div class="card">
                <h2 style="margin-bottom: 1rem; text-align: center;">Study Session</h2>
                
                <div class="timer-display" id="timer">0h 0m 0s</div>
                
                <form id="study-form" method="POST">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    
                    <div class="action-buttons">
                        <button type="button" id="start-btn" class="btn btn-primary" onclick="startStudySession()">
                            <i class="fas fa-play"></i> Start Study Timer
                        </button>
                        <button type="button" id="stop-btn" class="btn btn-danger" onclick="stopTimer(event)" disabled>
                            <i class="fas fa-stop"></i> Stop Study Timer
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let timer;
        let elapsedTime = 0; // Time in seconds
        const startBtn = document.getElementById("start-btn");
        const stopBtn = document.getElementById("stop-btn");

        function startTimer() {
            // Disable start button and enable stop button
            if (startBtn) startBtn.disabled = true;
            if (stopBtn) stopBtn.disabled = false;
            
            
            elapsedTime = 0; // Reset the elapsed time
            timer = setInterval(function() {
                elapsedTime++;
                displayTime(elapsedTime);
                // check if $_SESSION['status'] is true or false if false end the session
                // Check session status
                var status = "<?php echo isset($_SESSION['active']) ? $_SESSION['active'] : 'false'; ?>";
                // make a fetch to check_status its going to return true or false in the body
                fetch('check_status.php')
                .then(response => response.text()) // read the plain body text (like "1" or "0")
                .then(text => {
                    if (text.trim() !== "1") {
                    clearInterval(timer);
                    displayTime(0); // Reset timer display

                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.textContent = "Session ended due to inactivity.";
                    document.querySelector('.card').insertBefore(errorDiv, document.querySelector('.timer-display'));

                    setTimeout(() => errorDiv.remove(), 3000);

                    if (startBtn) startBtn.disabled = false;
                    if (stopBtn) stopBtn.disabled = true;
                    }
                })
                .catch(error => console.error('Error:', error));

            }, 1000);
        }

        function stopTimer(event) {
            event.preventDefault();
            clearInterval(timer);
            
            // Enable start button and disable stop button
            if (startBtn) startBtn.disabled = false;
            if (stopBtn) stopBtn.disabled = true;

            // Create form data from the study form
            const formData = new FormData(document.getElementById("study-form"));
            // Append the AJAX flag
            formData.append('ajax', true);

            // Show loading state
            document.getElementById("timer").innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            // Send the data to stop_session.php using fetch
            fetch('stop_session.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                displayTime(0); // Reset timer display
                
                if (data.message) {
                    // Create temporary success message
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'alert alert-success';
                    messageDiv.textContent = data.message;
                    document.querySelector('.card').insertBefore(messageDiv, document.querySelector('.timer-display'));
                    
                    // Remove the message after 3 seconds
                    setTimeout(() => messageDiv.remove(), 3000);
                    
                    // Update displayed values dynamically
                    if (data.weekly_hours !== undefined) {
                        document.getElementById("weekly-hours").textContent = data.weekly_hours + " hours.";
                    }
                    if (data.current_hours !== undefined) {
                        // Convert minutes to hours for display without the decimal and add a variable for the minutes
                        const totalMinutes = data.current_hours;
                        const hours = Math.floor(totalMinutes / 60);
                        const minutes = totalMinutes % 60;
                        document.getElementById("current-progress").textContent = hours + " hours and " + minutes + " minutes.";
                    }
                } else if (data.error) {
                    // Create temporary error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.textContent = data.error;
                    document.querySelector('.card').insertBefore(errorDiv, document.querySelector('.timer-display'));
                    
                    // Remove the message after 3 seconds
                    setTimeout(() => errorDiv.remove(), 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                displayTime(0); // Reset timer display
                
                // Create temporary error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.textContent = 'There was an error updating the database.';
                document.querySelector('.card').insertBefore(errorDiv, document.querySelector('.timer-display'));
                
                // Remove the message after 3 seconds
                setTimeout(() => errorDiv.remove(), 3000);
            });
        }

        function displayTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const sec = seconds % 60;
            document.getElementById("timer").textContent = `${hours}h ${minutes}m ${sec}s`;
        }

        async function getLocation() {
            try {
                const response = await fetch('location.php');
                const data = await response.json();
                if (data.latitude && data.longitude) {
                    document.getElementById('latitude').value = data.latitude;
                    document.getElementById('longitude').value = data.longitude;
                    return [data.latitude, data.longitude];
                } else {
                    console.error('Location not found');
                    return [null, null];
                }
            } catch (error) {
                console.error('Error fetching location:', error);
                return [null, null];
            }
        }

        async function requestGeolocation() {
            document.getElementById("timer").innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting location...';
            // get location data from getLocation
            const [latitude, longitude] = await getLocation();
            if (latitude && longitude) {
                sendLocationData(latitude, longitude);
            } else {
                console.error('Failed to retrieve location.');
                document.getElementById("timer").textContent = 'Location retrieval failed.';
                if (startBtn) startBtn.disabled = false;
            }
        }

        function sendLocationData(latitude, longitude) {
            const formData = new FormData();
            formData.append('latitude', latitude);
            formData.append('longitude', longitude);
            formData.append('start_study', 'true'); // Flag to indicate we're starting the session

            // Send the data to start_session.php to check location and start the study session
            fetch('start_session.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    // Show success message
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'alert alert-success';
                    messageDiv.textContent = data.message;
                    document.querySelector('.card').insertBefore(messageDiv, document.querySelector('.timer-display'));
                    
                    // Remove the message after 3 seconds
                    setTimeout(() => messageDiv.remove(), 3000);
                    
                    startTimer(); // Start the timer if the session was successfully started
                } else if (data.error) {
                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.textContent = data.error;
                    document.querySelector('.card').insertBefore(errorDiv, document.querySelector('.timer-display'));
                    
                    // Remove the message after 3 seconds
                    setTimeout(() => errorDiv.remove(), 3000);
                    
                    // Reset timer display
                    displayTime(0);
                    
                    // Enable start button
                    if (startBtn) startBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.textContent = 'There was an error starting the session.';
                document.querySelector('.card').insertBefore(errorDiv, document.querySelector('.timer-display'));
                
                // Remove the message after 3 seconds
                setTimeout(() => errorDiv.remove(), 3000);
                
                // Reset timer display
                displayTime(0);
                
                // Enable start button
                if (startBtn) startBtn.disabled = false;
            });
        }

        function startStudySession() {
            // Disable start button to prevent multiple clicks
            if (startBtn) startBtn.disabled = true;
            
            if (confirm("To start the study timer, we need to access your location. Proceed?")) {
                requestGeolocation();
            } else {
                // If user cancels, re-enable the start button
                if (startBtn) startBtn.disabled = false;
            }
        }
    </script>
</body>
</html>