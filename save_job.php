<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$job_id = (int) ($_POST['job_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($job_id <= 0 || !in_array($action, ['save', 'remove'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data.']);
    exit();
}

if ($action === 'save') {
    $insert = $connection->prepare('INSERT IGNORE INTO SavedJobs (user_id, job_id) VALUES (?, ?)');
    $insert->bind_param('ii', $user_id, $job_id);
    $saved = $insert->execute();
    $insert->close();
    echo json_encode(['success' => $saved, 'saved' => true]);
    exit();
}

$delete = $connection->prepare('DELETE FROM SavedJobs WHERE user_id = ? AND job_id = ?');
$delete->bind_param('ii', $user_id, $job_id);
$deleted = $delete->execute();
$delete->close();

echo json_encode(['success' => $deleted, 'saved' => false]);
