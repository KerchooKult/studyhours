<?php
session_start();
echo isset($_SESSION['active']) ? $_SESSION['active'] : 'false';
?>