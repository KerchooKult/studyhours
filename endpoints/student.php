<?php
require_once './internal/db.php';
session_start();

return function($method, $params, $body) {
    // Require authentication
    if (!isset($_SESSION['admin_logged_in'])) {
        http_response_code(401);
        return ['error' => 'Unauthorized access'];
    }

    // Validate method and required parameter
    if ($method !== 'GET' || empty($params['student_id'])) {
        http_response_code(400);
        return ['error' => 'Missing or invalid parameters'];
    }

    $studentId = $params['student_id'];
    $response = [];

    // Fetch student info
    $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(404);
        return ['error' => 'Student not found'];
    }

    // Process current hours and completion percentage
    $student['current_hours_display'] = round(($student['current_hours'] ?? 0) / 60, 1);
    $student['completion_percentage'] = $student['weekly_hours'] > 0
        ? min(round(($student['current_hours_display'] / $student['weekly_hours']) * 100), 100)
        : 0;

    $response['student'] = $student;

    // Build chart data (last 7 days)
    try {
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT DATE(log_date) as day, SUM(minutes_studied)/60 as hours_studied
            FROM study_logs 
            WHERE student_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY day ORDER BY day
        ");
        $stmt->execute([$studentId]);
        $progressData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $chartData = ['labels' => [], 'data' => []];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dayName = date('D', strtotime($date));
            $chartData['labels'][] = $dayName;
            $chartData['data'][] = 0;
        }

        foreach ($progressData as $entry) {
            $entryDay = date('D', strtotime($entry['day']));
            $index = array_search($entryDay, $chartData['labels']);
            if ($index !== false) {
                $chartData['data'][$index] = (float)$entry['hours_studied'];
            }
        }

        $response['progress_chart'] = $chartData;
    } catch (PDOException $e) {
        // Fallback to empty chart
        $response['progress_chart'] = [
            'labels' => array_map(fn($i) => date('D', strtotime("-$i days")), range(6, 0)),
            'data' => array_fill(0, 7, 0)
        ];
    }

    // Fetch student notes
    try {
        $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM student_notes WHERE student_id = ? ORDER BY created_at DESC");
        $stmt->execute([$studentId]);
        $response['notes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $response['notes'] = [];
    }

    // Summary calculations
    $chartData = $response['progress_chart'];
    $maxHours = max($chartData['data']);
    $maxDayIndex = array_search($maxHours, $chartData['data']);

    $response['summary'] = [
        'average_daily_hours' => array_sum($chartData['data']) / 7,
        'days_active' => count(array_filter($chartData['data'], fn($hours) => $hours > 0)),
        'weekly_target' => $student['weekly_hours'],
        'highest_day' => [
            'day' => $chartData['labels'][$maxDayIndex] ?? 'N/A',
            'hours' => $maxHours
        ]
    ];

    return $response;
};