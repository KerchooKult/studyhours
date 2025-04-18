<?php
require 'db.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get student details
if (isset($_GET['action']) && $_GET['action'] === 'get_student_details' && isset($_GET['student_id'])) {
    $studentId = $_GET['student_id'];
    
    // Prepare response array
    $response = [];
    
    // Get basic student info
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    
    // Convert minutes to hours for display
    $student['current_hours_display'] = round(($student['current_hours'] ?? 0) / 60, 1);
    $student['completion_percentage'] = $student['weekly_hours'] > 0 ? 
        min(round(($student['current_hours_display'] / $student['weekly_hours']) * 100), 100) : 0;
    
    $response['student'] = $student;
    
    // Get weekly progress data (for the past 7 days)
    // This assumes you have a 'study_logs' table with fields: id, student_id, log_date, minutes_studied
    try {
        $stmt = $pdo->prepare("SELECT 
                                DATE(log_date) as day, 
                                SUM(minutes_studied)/60 as hours_studied 
                              FROM study_logs 
                              WHERE student_id = ? 
                              AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                              GROUP BY day 
                              ORDER BY day");
        $stmt->execute([$studentId]);
        $progressData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format dates for chart
        $chartData = [
            'labels' => [],
            'data' => []
        ];
        
        // Generate all 7 days for the chart
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dayName = date('D', strtotime($date));
            $chartData['labels'][] = $dayName;
            $chartData['data'][] = 0; // Default to 0 hours
        }
        
        // Fill in actual data
        foreach ($progressData as $entry) {
            $dayIndex = array_search(date('D', strtotime($entry['day'])), $chartData['labels']);
            if ($dayIndex !== false) {
                $chartData['data'][$dayIndex] = (float)$entry['hours_studied'];
            }
        }
        
        $response['progress_chart'] = $chartData;
    } catch (PDOException $e) {
        // If table doesn't exist or other DB error
        $response['progress_chart'] = [
            'labels' => array_map(function($i) { return date('D', strtotime("-$i days")); }, range(6, 0)),
            'data' => array_fill(0, 7, 0)
        ];
    }
    
    // Get any notes (assuming you have a notes table)
    try {
        $stmt = $pdo->prepare("SELECT * FROM student_notes WHERE student_id = ? ORDER BY created_at DESC");
        $stmt->execute([$studentId]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['notes'] = $notes;
    } catch (PDOException $e) {
        $response['notes'] = [];
    }
    
    // Add summary statistics
    $response['summary'] = [
        'average_daily_hours' => array_sum($chartData['data']) / 7,
        'days_active' => count(array_filter($chartData['data'], function($hours) { return $hours > 0; })),
        'weekly_target' => $student['weekly_hours'],
        'highest_day' => [
            'day' => $chartData['labels'][array_search(max($chartData['data']), $chartData['data'])] ?? 'N/A',
            'hours' => max($chartData['data'])
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Update Weekly Hours (Required)
if (isset($_POST['update_weekly_hours'])) {
    $id = $_POST['student_id'];
    $hours = $_POST['weekly_hours'];
    $stmt = $pdo->prepare("UPDATE students SET weekly_hours = ? WHERE id = ?");
    $stmt->execute([$hours, $id]);
    // Optionally, you can return a success message
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Weekly hours updated successfully!']);
    exit;
}

// Update Current Hours (Progress) - Convert hours to minutes before storing
if (isset($_POST['update_current_hours'])) {
    $id = $_POST['student_id'];
    $hours = $_POST['current_hours'];  // User enters hours
    $minutes = $hours * 60;  // Convert to minutes
    $stmt = $pdo->prepare("UPDATE students SET current_hours = ? WHERE id = ?");
    $stmt->execute([$minutes, $id]);
    // Optionally, you can return a success message
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Current hours updated successfully!']);
    exit;
}

// Add note for student
if (isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $studentId = $_POST['student_id'];
    $noteText = $_POST['note_text'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO student_notes (student_id, note_text, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$studentId, $noteText]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Note added successfully']);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to add note: ' . $e->getMessage()]);
    }
    exit;
}

// Delete note
if (isset($_POST['action']) && $_POST['action'] === 'delete_note') {
    $noteId = $_POST['note_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM student_notes WHERE id = ?");
        $stmt->execute([$noteId]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Note deleted successfully']);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to delete note: ' . $e->getMessage()]);
    }
    exit;
}

// If no valid action is specified
header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid request']);
exit;
?>