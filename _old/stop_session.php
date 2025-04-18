<?php
session_start();
require 'db.php';
date_default_timezone_set('America/Chicago');


// Ensure the user is logged in and there is an active study session
if (!isset($_SESSION['student_id']) || !isset($_SESSION['start_time'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No active session or not logged in.']);
    exit;
}

try {
    // Get student ID and calculate elapsed time
    $student_id = $_SESSION['student_id'];
    $elapsed_time_in_seconds = time() - $_SESSION['start_time'];
    $elapsed_time_in_minutes = floor($elapsed_time_in_seconds / 60);
    
    // Start transaction for data consistency
    $pdo->beginTransaction();
    
    // Only record if at least 1 minute has passed
    if ($elapsed_time_in_minutes >= 1) {
        // 1. Add entry to study_logs table
        $log_date = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO study_logs 
            (student_id, log_date, minutes_studied) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$student_id, $log_date, $elapsed_time_in_minutes]);
        
        // 2. Update the current_hours in students table
        $stmt = $pdo->prepare("
            UPDATE students 
            SET current_hours = current_hours + ? 
            WHERE id = ?
        ");
        $stmt->execute([$elapsed_time_in_minutes, $student_id]);
    }
    
    // Commit the transaction
    $pdo->commit();
    
    // Clear the session start time
    unset($_SESSION['start_time']);
    
    // Fetch updated data for display
    $stmt = $pdo->prepare("
        SELECT weekly_hours, current_hours FROM students WHERE id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    $updated_weekly_hours = $student['weekly_hours'];
    $updated_current_hours = $student['current_hours']; // convert minutes to hours
    
    // Return the updated values and a success message
    header('Content-Type: application/json');
    echo json_encode([
        'weekly_hours' => $updated_weekly_hours,
        'current_hours' => $updated_current_hours,
        'message' => $elapsed_time_in_minutes >= 1 
            ? 'Study session ended and time updated!' 
            : 'Session was too short to record (less than 1 minute).'
    ]);
    exit;
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}
?>