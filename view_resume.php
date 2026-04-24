<?php
require_once 'db_connect.php';

$user_id = max(0, (int)($_GET['user_id'] ?? 0));
if ($user_id <= 0) {
    http_response_code(400);
    exit('Invalid user ID');
}

// Check if current user is employer (only employers can view resumes)
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Not logged in');
}

$current_user_id = (int)$_SESSION['user_id'];
$current_stmt = $connection->prepare('SELECT role FROM Users WHERE user_id = ?');
$current_stmt->bind_param('i', $current_user_id);
$current_stmt->execute();
$current = $current_stmt->get_result()->fetch_assoc();
$current_stmt->close();

if (!$current || $current['role'] !== 'employer') {
    http_response_code(403);
    exit('Access denied');
}

// Fetch PDF data from database
$stmt = $connection->prepare('SELECT resume_pdf, resume_filename, resume_mimetype FROM UserProfiles WHERE user_id = ? AND resume_pdf IS NOT NULL');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    http_response_code(404);
    exit('Resume not found');
}

// Set headers for PDF display
header('Content-Type: ' . ($result['resume_mimetype'] ?: 'application/pdf'));
header('Content-Disposition: inline; filename="' . ($result['resume_filename'] ?: 'resume.pdf') . '"');
header('Content-Length: ' . strlen($result['resume_pdf']));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output the PDF data
echo $result['resume_pdf'];
exit();
?>
