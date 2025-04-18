<?php
require 'db.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['newHours'], $_POST['type'])) {
    // Retrieve the data sent via POST
    $id = $_POST['id'];
    $newHours = $_POST['newHours'];
    $type = $_POST['type'];

    // Validate newHours to ensure it's numeric and non-negative
    if (is_numeric($newHours) && $newHours >= 0) {
        // Determine which column to update based on the "type" field
        if ($type == 'weekly_hours') {
            $column = 'weekly_hours';
        } elseif ($type == 'current_hours') {
            $column = 'current_hours';
        } else {
            echo "Error: Invalid type.";
            exit;
        }

        // Prepare and execute the SQL statement to update the hours
        $stmt = $pdo->prepare("UPDATE students SET $column = ? WHERE id = ?");
        $stmt->execute([$newHours, $id]);

        echo "Success: $column updated for student with ID $id.";
    } else {
        echo "Error: Invalid value for hours. Please provide a valid numeric value.";
    }
} else {
    echo "Error: Invalid request. Missing parameters.";
}
?>