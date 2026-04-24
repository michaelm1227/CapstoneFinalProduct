<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$stmt = $connection->prepare('SELECT role FROM Users WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit();
}

if ($user['role'] === 'employer') {
    header('Location: dashboard_employer.php');
} else {
    header('Location: dashboard_student.php');
}
exit();
