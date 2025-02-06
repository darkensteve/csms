<?php
include 'db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}
?>