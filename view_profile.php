<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$current_stmt = $connection->prepare('SELECT role FROM Users WHERE user_id = ?');
$current_stmt->bind_param('i', $current_user_id);
$current_stmt->execute();
$current = $current_stmt->get_result()->fetch_assoc();
$current_stmt->close();

if (!$current || $current['role'] !== 'employer') {
    header('Location: dashboard.php');
    exit();
}

$profile_user_id = max(0, (int)($_GET['user_id'] ?? 0));
if ($profile_user_id <= 0) {
    header('Location: dashboard_employer.php');
    exit();
}

$profile_stmt = $connection->prepare(
    'SELECT u.username, u.email, u.location, u.visa_status, p.skills, p.interests, p.certifications, p.experience_years, p.resume_pdf
     FROM Users u
     LEFT JOIN UserProfiles p ON p.user_id = u.user_id
     WHERE u.user_id = ? AND u.role != \'employer\''
);
$profile_stmt->bind_param('i', $profile_user_id);
$profile_stmt->execute();
$profile = $profile_stmt->get_result()->fetch_assoc();
$profile_stmt->close();

if (!$profile) {
    header('Location: dashboard_employer.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Profile – Careerify</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0d0f1a; --surface:#151826; --card:#1c1f32; --border:#272b42; --accent:#f59e0b; --accent2:#fbbf24; --text:#e8eaf6; --muted:#7b82a8; --radius:14px; }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
        .page{max-width:900px;margin:0 auto;padding:2rem 1.5rem;}
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.5rem;}
        .topbar h1{font-family:'Syne',sans-serif;font-size:1.6rem;}
        .back-link{color:var(--accent);text-decoration:none;font-weight:600;}
        .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;}
        .section{margin-top:1.5rem;}
        .section h2{font-size:1.05rem;color:var(--accent);margin-bottom:0.75rem;}
        .field{margin-bottom:1rem;}
        .label{font-size:0.85rem;color:var(--muted);margin-bottom:0.3rem;display:block;}
        .value{font-size:0.95rem;color:var(--text);line-height:1.6;}
        .chips{display:flex;flex-wrap:wrap;gap:0.5rem;}
        .chip{background:rgba(255,255,255,0.06);border:1px solid var(--border);padding:0.5rem 0.75rem;border-radius:999px;font-size:0.85rem;color:var(--text);}
        .resume-link{display:inline-block;margin-top:0.75rem;color:var(--accent);text-decoration:none;font-weight:600;}
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Applicant Profile</h1>
            <p style="color:var(--muted);margin-top:0.35rem;">Review the student’s full profile and resume before sending a message.</p>
        </div>
        <a href="dashboard_employer.php" class="back-link">← Back to dashboard</a>
    </div>
    <div class="card">
        <div class="section">
            <h2>Basic Info</h2>
            <div class="field"><span class="label">Name</span><div class="value"><?= htmlspecialchars($profile['username']) ?></div></div>
            <div class="field"><span class="label">Email</span><div class="value"><?= htmlspecialchars($profile['email']) ?></div></div>
            <div class="field"><span class="label">Location</span><div class="value"><?= htmlspecialchars($profile['location'] ?: 'Not provided') ?></div></div>
            <div class="field"><span class="label">Visa status</span><div class="value"><?= htmlspecialchars($profile['visa_status'] ?: 'Not set') ?></div></div>
            <div class="field"><span class="label">Experience</span><div class="value"><?= htmlspecialchars($profile['experience_years'] ?? '0') ?> years</div></div>
        </div>
        <div class="section">
            <h2>Profile Details</h2>
            <?php if (!empty($profile['skills'])): ?><div class="field"><span class="label">Skills</span><div class="value"><?= nl2br(htmlspecialchars($profile['skills'])) ?></div></div><?php endif; ?>
            <?php if (!empty($profile['interests'])): ?><div class="field"><span class="label">Interests</span><div class="value"><?= nl2br(htmlspecialchars($profile['interests'])) ?></div></div><?php endif; ?>
            <?php if (!empty($profile['certifications'])): ?><div class="field"><span class="label">Certifications</span><div class="value"><?= nl2br(htmlspecialchars($profile['certifications'])) ?></div></div><?php endif; ?>
        </div>
        <?php if (!empty($profile['resume_pdf'])): ?>
        <div class="section">
            <h2>Resume</h2>
            <a class="resume-link" href="view_resume.php?user_id=<?= $profile_user_id ?>" target="_blank" rel="noopener noreferrer">Open resume</a>
        </div>
        <?php endif; ?>
        <div class="section">
            <h2>Quick actions</h2>
            <div class="chips">
                <a class="chip" href="dashboard_employer.php">Back to employer dashboard</a>
                <a class="chip" href="dashboard_employer.php#tab-messages">View messages</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
