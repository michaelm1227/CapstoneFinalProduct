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
$cover_letter = trim($_POST['cover_letter'] ?? '');

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid job selected.']);
    exit();
}

$create_table = "CREATE TABLE IF NOT EXISTS JobApplications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    cover_letter TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_job (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$connection->query($create_table);

$role_stmt = $connection->prepare('SELECT role FROM Users WHERE user_id = ?');
$role_stmt->bind_param('i', $user_id);
$role_stmt->execute();
$role = $role_stmt->get_result()->fetch_assoc();
$role_stmt->close();

if (!$role || $role['role'] === 'employer') {
    echo json_encode(['success' => false, 'error' => 'Employers cannot apply to jobs.']);
    exit();
}

$job_stmt = $connection->prepare('SELECT job_id FROM Jobs WHERE job_id = ? LIMIT 1');
$job_stmt->bind_param('i', $job_id);
$job_stmt->execute();
$job_result = $job_stmt->get_result();
$job_stmt->close();

if ($job_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Selected job no longer exists.']);
    exit();
}

$check_stmt = $connection->prepare('SELECT application_id FROM JobApplications WHERE user_id = ? AND job_id = ?');
$check_stmt->bind_param('ii', $user_id, $job_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$check_stmt->close();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'You have already applied to this job.']);
    exit();
}

$insert_stmt = $connection->prepare('INSERT INTO JobApplications (user_id, job_id, cover_letter) VALUES (?, ?, ?)');
$insert_stmt->bind_param('iis', $user_id, $job_id, $cover_letter);

if ($insert_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Application submitted.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Could not submit application.']);
}
$insert_stmt->close();
