<?php
$host = 'localhost';
$dbname = 'study_tracker';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
    exit;
}

// Function to get the current weekly study hours
function getWeeklyHours($student_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT weekly_hours FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    return (float) $stmt->fetchColumn();
}

function updateCurrentHours($student_id, $elapsed_minutes) {
    global $pdo;
    // Fetch current value
    $stmt = $pdo->prepare("SELECT current_hours FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $current_minutes = (int) $stmt->fetchColumn();
    
    // Update with new total (in minutes)
    $stmt = $pdo->prepare("UPDATE students SET current_hours = ? WHERE id = ?");
    $stmt->execute([$current_minutes + $elapsed_minutes, $student_id]);
}

function updateWeeklyHours($student_id, $elapsed_hours) {
    global $pdo;
    // Fetch current weekly hours (stored as hours)
    $stmt = $pdo->prepare("SELECT weekly_hours FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $current_hours = (float) $stmt->fetchColumn();
    
    // Update with new total (in hours)
    $stmt = $pdo->prepare("UPDATE students SET weekly_hours = ? WHERE id = ?");
    $stmt->execute([$current_hours + $elapsed_hours, $student_id]);
}

// Reset study hours weekly (runs only ONCE per week)
if (date('w') == 0 && date('H') == 23 && date('i') >= 58) {
    // Update students' total hours by adding their current_hours to it and resetting current_hours
    $resetQuery = "UPDATE students
                   SET total_hours = total_hours + (current_hours / 60),
                       current_hours = 0
                   WHERE current_hours > 0";
    $pdo->exec($resetQuery);
}

?>
