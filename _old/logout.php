<?php
session_start();
session_destroy();
header("Location: index.php"); // Redirects back to the login page
exit;
?>
