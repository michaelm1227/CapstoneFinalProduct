<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['send_message'])) {
    header('Location: dashboard.php');
    exit();
}

$user_id      = (int)$_SESSION['user_id'];
$recipient_id = (int)($_POST['recipient_id'] ?? 0);
$subject      = trim($_POST['subject'] ?? '');
$body         = trim($_POST['body'] ?? '');
$scheduled_for = trim($_POST['scheduled_for'] ?? '');
$job_id       = (int)($_POST['job_id'] ?? 0);
$return_to    = $_POST['return_to'] ?? 'dashboard.php';
$return_to    = in_array($return_to, ['dashboard_employer.php', 'dashboard_student.php'], true) ? $return_to : 'dashboard.php';

if ($recipient_id <= 0 || $recipient_id === $user_id || $body === '') {
    header('Location: ' . $return_to);
    exit();
}

$check_recipient = $connection->prepare('SELECT user_id FROM Users WHERE user_id = ?');
$check_recipient->bind_param('i', $recipient_id);
$check_recipient->execute();
$recipient_result = $check_recipient->get_result();
$check_recipient->close();

if ($recipient_result->num_rows === 0) {
    header('Location: ' . $return_to);
    exit();
}

$type = $scheduled_for !== '' ? 'interview' : 'message';
$scheduled_param = $scheduled_for !== '' ? $scheduled_for : null;
$job_param = $job_id > 0 ? $job_id : null;

$insert = $connection->prepare(
    'INSERT INTO Messages (sender_id, recipient_id, subject, body, scheduled_for, job_id, type) VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$insert->bind_param('iisssis', $user_id, $recipient_id, $subject, $body, $scheduled_param, $job_param, $type);
$insert->execute();
$insert->close();

header('Location: ' . $return_to);
exit();
