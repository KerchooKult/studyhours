<?php
    session_start(); 
    // API endpoint for Login (login.php)
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: chrome-extension://cpicngjcoodhlaiiebdilocdbpjjimjo");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); // No Content
        exit;
    }
    require('./internal/db.php');
    return function($method, $params, $body) {
        if ($method === 'GET') {
            // PARAM: student_id [string (numbers)]

            // declare variables
            $student_id = isset($params['student_id']) ? $params['student_id'] : null;
            // if any of the parameters are null, return error
            if ($student_id === null) {
                http_response_code(400);
                return ['error' => 'Missing parameters'];
            }

            // check if student_id is valid using db.php's getStudent(studentId)
            $student = getStudent($student_id);
            if ($student === false) {
                http_response_code(403);
                return ['error' => 'Invalid student ID'];
            }

            $_SESSION['student_id'] = $student_id;

            // Respond with the student's information
            return [
                'success' => true,
                'student_id' => $student_id,
                'name' => $student['name'],
                'weekly_hours' => $student['weekly_hours'],
                'current_hours' => $student['current_hours']
            ];
        }
        return ['error' => 'Unsupported method'];
    };  
?>
