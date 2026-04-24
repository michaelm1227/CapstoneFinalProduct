<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db_connect.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit();
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$job_id  = (int) ($_POST['job_id'] ?? 0);
$rating  = (int) ($_POST['rating'] ?? 0);

// Validate rating range
if ($job_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Invalid job or rating value.']);
    exit();
}

// Check user role — only students and employees can rate
$role_stmt = $connection->prepare("SELECT role FROM Users WHERE user_id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result()->fetch_assoc();
$role_stmt->close();

if (!$role_result || $role_result['role'] === 'employer') {
    echo json_encode(['success' => false, 'error' => 'Employers cannot rate jobs.']);
    exit();
}

// Check the job is in the user's PastJobs
$past_stmt = $connection->prepare(
    "SELECT past_id FROM PastJobs WHERE user_id = ? AND job_id = ?"
);
$past_stmt->bind_param("ii", $user_id, $job_id);
$past_stmt->execute();
$past_result = $past_stmt->get_result();
$past_stmt->close();

if ($past_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'You can only rate jobs in your Past Jobs list.']);
    exit();
}

// Check they haven't already rated this job
$check_stmt = $connection->prepare(
    "SELECT rating_id FROM JobRatings WHERE user_id = ? AND job_id = ?"
);
$check_stmt->bind_param("ii", $user_id, $job_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$check_stmt->close();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'You have already rated this job.']);
    exit();
}

// Insert the rating
$insert_stmt = $connection->prepare(
    "INSERT INTO JobRatings (user_id, job_id, rating) VALUES (?, ?, ?)"
);
$insert_stmt->bind_param("iii", $user_id, $job_id, $rating);

if ($insert_stmt->execute()) {
    // Return updated stats for this job
    $stats_stmt = $connection->prepare(
        "SELECT 
            ROUND(AVG(rating), 2) AS avg_rating,
            MIN(rating)           AS min_rating,
            MAX(rating)           AS max_rating,
            COUNT(*)              AS total_ratings
         FROM JobRatings
         WHERE job_id = ?"
    );
    $stats_stmt->bind_param("i", $job_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    $stats_stmt->close();

    echo json_encode([
        'success'      => true,
        'message'      => 'Rating submitted!',
        'avg_rating'   => $stats['avg_rating'],
        'min_rating'   => $stats['min_rating'],
        'max_rating'   => $stats['max_rating'],
        'total_ratings'=> $stats['total_ratings'],
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save rating.']);
}

$insert_stmt->close();
?>
