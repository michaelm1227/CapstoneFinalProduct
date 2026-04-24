<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user info
$user_stmt = $connection->prepare("SELECT username, email, location, visa_status, role FROM Users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Redirect employers to their dashboard
if ($user['role'] === 'employer') {
    header('Location: dashboard_employer.php');
    exit();
}

// Ensure application table exists for this dashboard.
$connection->query("CREATE TABLE IF NOT EXISTS JobApplications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    cover_letter TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_job (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Fetch profile
$profile_stmt = $connection->prepare("SELECT skills, interests, certifications, resume_pdf, resume_filename, experience_years FROM UserProfiles WHERE user_id = ?");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile = $profile_stmt->get_result()->fetch_assoc() ?? [];
$profile_stmt->close();

// Messages for student
$message_stmt = $connection->prepare(
    "SELECT m.*, s.username AS sender_name, r.username AS recipient_name
     FROM Messages m
     JOIN Users s ON s.user_id = m.sender_id
     JOIN Users r ON r.user_id = m.recipient_id
     WHERE m.sender_id = ? OR m.recipient_id = ?
     ORDER BY m.created_at DESC"
);
$message_stmt->bind_param("ii", $user_id, $user_id);
$message_stmt->execute();
$messages = $message_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$message_stmt->close();

$success_message = $_SESSION['profile_success'] ?? '';
$error_message = $_SESSION['profile_error'] ?? '';
unset($_SESSION['profile_success'], $_SESSION['profile_error']);

// Fetch saved jobs
$saved_stmt = $connection->prepare("
    SELECT j.job_id, j.title, j.company, j.location, j.salary_range, s.saved_at
    FROM SavedJobs s
    JOIN Jobs j ON j.job_id = s.job_id
    WHERE s.user_id = ?
    ORDER BY s.saved_at DESC
");
$saved_stmt->bind_param("i", $user_id);
$saved_stmt->execute();
$saved_jobs = $saved_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$saved_stmt->close();

// Fetch past jobs with rating stats
$past_stmt = $connection->prepare("
    SELECT j.job_id, j.title, j.company, j.location, j.salary_range,
           ROUND(AVG(jr.rating),2) AS avg_rating,
           MIN(jr.rating) AS min_rating,
           MAX(jr.rating) AS max_rating,
           COUNT(jr.rating_id) AS total_ratings,
           my_r.rating AS my_rating,
           MAX(pj.added_at) AS added_at
    FROM PastJobs pj
    JOIN Jobs j ON j.job_id = pj.job_id
    LEFT JOIN JobRatings jr ON jr.job_id = j.job_id
    LEFT JOIN JobRatings my_r ON my_r.job_id = j.job_id AND my_r.user_id = ?
    WHERE pj.user_id = ?
    GROUP BY j.job_id, j.title, j.company, j.location, j.salary_range, my_r.rating
    ORDER BY added_at DESC
");
$past_stmt->bind_param("ii", $user_id, $user_id);
$past_stmt->execute();
$past_jobs = $past_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$past_stmt->close();

// Fetch applied jobs
$app_stmt = $connection->prepare("
    SELECT a.application_id, a.job_id, a.cover_letter, a.applied_at,
           j.title, j.company, j.location, j.salary_range, j.work_type
    FROM JobApplications a
    JOIN Jobs j ON j.job_id = a.job_id
    WHERE a.user_id = ?
    ORDER BY a.applied_at DESC
");
$app_stmt->bind_param("i", $user_id);
$app_stmt->execute();
$applied_jobs = $app_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$app_stmt->close();

// Fetch available jobs and match score
$jobs_stmt = $connection->prepare("
    SELECT j.job_id, j.title, j.company, j.location, j.description, j.requirements, j.tags,
           j.salary_range, j.work_type, j.posted_at, j.expires_at,
           IFNULL(jm.match_score, 0) AS match_score,
           EXISTS(SELECT 1 FROM SavedJobs s WHERE s.user_id = ? AND s.job_id = j.job_id) AS is_saved,
           EXISTS(SELECT 1 FROM JobApplications a WHERE a.user_id = ? AND a.job_id = j.job_id) AS is_applied
    FROM Jobs j
    LEFT JOIN JobMatches jm ON jm.user_id = ? AND jm.job_id = j.job_id
    WHERE j.expires_at IS NULL OR j.expires_at >= CURDATE()
    ORDER BY jm.match_score DESC, j.posted_at DESC
");
$jobs_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$jobs_stmt->execute();
$available_jobs = $jobs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$jobs_stmt->close();

foreach ($available_jobs as &$job) {
    if ((int)$job['match_score'] === 0) {
        $job_tags = array_filter(array_map('trim', explode(',', $job['tags'] ?? '')));
        $profile_terms = [];
        foreach (['skills', 'interests', 'certifications'] as $field) {
            foreach (explode(',', $profile[$field] ?? '') as $term) {
                $term = strtolower(trim($term));
                if ($term !== '') {
                    $profile_terms[] = preg_replace('/[^a-z0-9]+/', ' ', $term);
                }
            }
        }
        $profile_terms = array_unique(array_filter($profile_terms));
        $normalized_tags = array_unique(array_filter(array_map(function($tag) {
            return strtolower(trim(preg_replace('/[^a-z0-9]+/', ' ', $tag)));
        }, $job_tags)));
        if (!empty($normalized_tags) && !empty($profile_terms)) {
            $matched = count(array_intersect($normalized_tags, $profile_terms));
            $job['match_score'] = max(25, min(100, round($matched / count($normalized_tags) * 100)));
        } else {
            $job['match_score'] = 25;
        }
    } else {
        $job['match_score'] = (int) round($job['match_score']);
    }
}
unset($job);

usort($available_jobs, function($a, $b) {
    return $b['match_score'] <=> $a['match_score'] ?: strcmp($b['posted_at'], $a['posted_at']);
});

function stars(int $n, int $total = 5): string {
    return str_repeat('★', $n) . str_repeat('☆', $total - $n);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Careerify</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #0d0f1a;
            --surface:  #151826;
            --card:     #1c1f32;
            --border:   #272b42;
            --accent:   #5b7fff;
            --accent2:  #a78bfa;
            --gold:     #fbbf24;
            --green:    #34d399;
            --red:      #f87171;
            --text:     #e8eaf6;
            --muted:    #7b82a8;
            --radius:   12px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── NAVBAR ── */
        .navbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-brand {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--accent);
            padding: 1rem 0;
            margin-right: 1rem;
            white-space: nowrap;
            text-decoration: none;
        }
        .nav-tabs {
            display: flex;
            gap: 0.25rem;
            flex: 1;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .nav-tabs::-webkit-scrollbar { display: none; }
        .tab-btn {
            background: none;
            border: none;
            color: var(--muted);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            font-weight: 500;
            padding: 1.1rem 1rem;
            cursor: pointer;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            transition: color 0.2s, border-color 0.2s;
            width: auto;
            margin-bottom: 0;
            border-radius: 0;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        .nav-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-left: auto;
            padding-left: 1rem;
        }
        .avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.85rem; color: #fff;
            flex-shrink: 0;
        }
        .nav-username { font-size: 0.88rem; color: var(--muted); }
        .logout-btn {
            background: none; border: 1px solid var(--border);
            color: var(--muted); font-size: 0.8rem;
            padding: 0.35rem 0.75rem; border-radius: 6px;
            cursor: pointer; transition: all 0.2s;
            width: auto; margin-bottom: 0;
            font-family: 'DM Sans', sans-serif;
        }
        .logout-btn:hover { border-color: var(--red); color: var(--red); background: none; }

        /* ── CONTENT ── */
        .content { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; }
        .tab-panel { display: none; animation: fadeUp 0.25s ease; }
        .tab-panel.active { display: block; }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(8px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* ── PAGE HEADER ── */
        .page-header { margin-bottom: 1.75rem; }
        .page-header h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.6rem; font-weight: 800;
            color: var(--text);
        }
        .page-header p { color: var(--muted); font-size: 0.9rem; margin-top: 0.25rem; }

        /* ── CARDS ── */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
            transition: border-color 0.2s;
        }
        .card:hover { border-color: var(--accent); }
        .card-title { font-weight: 600; font-size: 1rem; margin-bottom: 0.25rem; }
        .alert { border-radius: 10px; padding: 1rem 1.2rem; margin-bottom: 1rem; font-size: 0.95rem; }
        .alert-success { background: rgba(52,211,153,0.12); border: 1px solid rgba(52,211,153,0.25); color: var(--green); }
        .alert-error { background: rgba(248,113,113,0.12); border: 1px solid rgba(248,113,113,0.25); color: var(--red); }
        .card-meta { font-size: 0.85rem; color: var(--muted); margin-bottom: 0.75rem; }
        .card-meta span { margin-right: 1rem; }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-blue  { background: rgba(91,127,255,0.15); color: var(--accent); }
        .badge-gold  { background: rgba(251,191,36,0.15);  color: var(--gold); }
        .badge-green { background: rgba(52,211,153,0.15);  color: var(--green); }

        .job-description { color: var(--muted); font-size:0.92rem; line-height:1.7; margin:0.75rem 0; }
        .job-tags { display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.75rem; }
        .job-tag { background: var(--surface); border:1px solid var(--border); border-radius: 10px; padding:0.4rem 0.75rem; font-size:0.78rem; color:var(--muted); }
        .action-row { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:0.75rem; margin-top:1rem; }
        .button-primary, .button-outline {
            border-radius: 8px; font-family: 'DM Sans', sans-serif; font-weight:600; cursor:pointer; transition: all 0.2s ease;
            padding:0.65rem 1rem; border:1px solid transparent;
        }
        .button-primary { background: var(--accent); color:#fff; border-color: var(--accent); }
        .button-primary:hover { opacity:0.93; }
        .button-outline { background: transparent; color: var(--accent); border-color: var(--accent); }
        .button-outline:hover { background: rgba(91,127,255,0.1); }
        .button-disabled { opacity:0.55; cursor:not-allowed; }
        .small-text { color: var(--muted); font-size:0.82rem; }

        /* ── MATCH SCORE BAR ── */
        .match-bar-wrap { margin-top: 0.5rem; }
        .match-bar-label { font-size: 0.8rem; color: var(--muted); margin-bottom: 4px; }
        .match-bar-track {
            height: 6px; background: var(--border);
            border-radius: 99px; overflow: hidden;
        }
        .match-bar-fill {
            height: 100%; border-radius: 99px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            transition: width 0.8s cubic-bezier(.4,0,.2,1);
        }

        /* ── STATS ROW ── */
        .stats-row {
            display: flex; gap: 0.75rem;
            flex-wrap: wrap; margin-bottom: 0.75rem;
        }
        .stat-chip {
            font-size: 0.8rem; color: var(--muted);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px; padding: 0.3rem 0.65rem;
        }
        .stat-chip strong { color: var(--text); }
        .stars-gold { color: var(--gold); letter-spacing: 1px; }

        /* ── STAR RATING INPUT ── */
        .star-input { display:flex; flex-direction:row-reverse; gap:4px; margin-bottom:0.75rem; }
        .star-input input { display:none; }
        .star-input label {
            font-size:1.6rem; color: var(--border);
            cursor:pointer; transition:color 0.15s;
            margin-bottom:0;
        }
        .star-input label:hover,
        .star-input label:hover ~ label,
        .star-input input:checked ~ label { color: var(--gold); }
        .rate-submit {
            background: var(--accent); color: #fff;
            border: none; padding: 0.45rem 1.1rem;
            border-radius: 6px; font-size: 0.85rem;
            cursor: pointer; transition: opacity 0.2s;
            width: auto; margin-bottom: 0;
            font-family: 'DM Sans', sans-serif;
        }
        .rate-submit:hover { opacity: 0.85; background: var(--accent); }
        .already-rated { color: var(--green); font-size: 0.88rem; font-weight: 500; }
        .feedback { font-size: 0.82rem; margin-top: 0.4rem; min-height: 1rem; }
        .feedback.err { color: var(--red); }
        .feedback.ok  { color: var(--green); }

        /* ── PROFILE FORM ── */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        @media (max-width: 600px) { .profile-grid { grid-template-columns: 1fr; } }
        .field-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .field-group.full { grid-column: 1 / -1; }
        .field-group label { font-size: 0.82rem; color: var(--muted); font-weight: 500; }
        .field-group input,
        .field-group select,
        .field-group textarea {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            padding: 0.65rem 0.9rem;
            transition: border-color 0.2s;
            margin-bottom: 0;
            width: 100%;
        }
        .field-group input:focus,
        .field-group select:focus,
        .field-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--surface);
            box-shadow: 0 0 0 3px rgba(91,127,255,0.12);
        }
        .field-group textarea { resize: vertical; min-height: 80px; }
        .save-btn {
            background: var(--accent);
            color: #fff; border: none;
            padding: 0.7rem 2rem;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem; font-weight: 600;
            cursor: pointer; margin-top: 0.5rem;
            transition: opacity 0.2s;
            width: auto; margin-bottom: 0;
        }
        .save-btn:hover { opacity: 0.88; background: var(--accent); }

        /* ── VISA CARD ── */
        .visa-hero {
            background: linear-gradient(135deg, #1c2a5e, #2d1f5e);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .visa-hero::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(91,127,255,0.1);
        }
        .visa-status-label { font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; }
        .visa-status-value {
            font-family: 'Syne', sans-serif;
            font-size: 2.5rem; font-weight: 800;
            color: var(--accent2);
            line-height: 1;
        }
        .visa-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
        }
        .visa-info-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
        }
        .visa-info-card h4 { font-size: 0.9rem; color: var(--accent); margin-bottom: 0.5rem; }
        .visa-info-card p { font-size: 0.85rem; color: var(--muted); line-height: 1.6; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 3rem 1rem;
            color: var(--muted); font-size: 0.95rem;
        }
        .empty-state .icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-inner">
        <a class="nav-brand" href="#">⚡ Careerify</a>
        <div class="nav-tabs">
            <button class="tab-btn active" onclick="switchTab('open', this)">🎯 Open Jobs</button>
            <button class="tab-btn" onclick="switchTab('saved', this)">🔖 Saved Jobs</button>
            <button class="tab-btn" onclick="switchTab('applied', this)">✉️ Applications</button>
            <button class="tab-btn" onclick="switchTab('messages', this)">💬 Messages</button>
            <button class="tab-btn" onclick="switchTab('profile', this)">👤 Edit Profile</button>
            <button class="tab-btn" onclick="switchTab('past', this)">📋 Past Jobs</button>
            <button class="tab-btn" onclick="switchTab('visa', this)">🛂 Visa Info</button>
        </div>
        <div class="nav-user">
            <div class="avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
            <div>
                <span class="nav-username"><?= htmlspecialchars($user['username']) ?></span>
                <span class="badge <?= $user['role'] === 'international' ? 'badge-blue' : 'badge-green' ?>" style="font-size:0.7rem;margin-left:0.4rem">
                    <?= $user['role'] === 'international' ? 'Intl. Student' : 'Student' ?>
                </span>
            </div>
            <form method="POST" action="logout.php" style="margin:0">
                <button type="submit" class="logout-btn">Log out</button>
            </form>
        </div>
    </div>
</nav>

<!-- CONTENT -->
<div class="content">

    <!-- ── TAB: OPEN JOBS ── -->
    <div class="tab-panel active" id="tab-open">
        <div class="page-header">
            <h2>Open Jobs</h2>
            <p>Browse STEM-friendly opportunities and apply directly from your dashboard.</p>
        </div>
        <?php if (empty($available_jobs)): ?>
            <div class="empty-state">
                <div class="icon">🎯</div>
                <p>No open jobs are available right now. Check back soon or ask an employer to post a new position.</p>
            </div>
        <?php else: ?>
            <?php foreach ($available_jobs as $job): ?>
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:0.5rem;">
                    <div>
                        <div class="card-title"><?= htmlspecialchars($job['title']) ?></div>
                        <div class="card-meta">
                            <span>🏢 <?= htmlspecialchars($job['company']) ?></span>
                            <span>📍 <?= htmlspecialchars($job['location']) ?></span>
                            <?php if ($job['salary_range']): ?>
                            <span>💰 <?= htmlspecialchars($job['salary_range']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge badge-blue"><?= $job['match_score'] ?>% match</span>
                </div>
                <p class="job-description"><?= nl2br(htmlspecialchars($job['description'])) ?></p>
                <?php if (!empty($job['tags'])): ?>
                <div class="job-tags">
                    <?php foreach (array_filter(array_map('trim', explode(',', $job['tags']))) as $tag): ?>
                        <?php if ($tag !== ''): ?>
                            <span class="job-tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($job['requirements'])): ?>
                <div class="job-tags">
                    <span class="job-tag">Requirements: <?= htmlspecialchars($job['requirements']) ?></span>
                </div>
                <?php endif; ?>
                <div class="action-row">
                    <div class="small-text"><?= htmlspecialchars($job['work_type']) ?> • Posted <?= date('M j, Y', strtotime($job['posted_at'])) ?> • Expires <?= date('M j, Y', strtotime($job['expires_at'])) ?></div>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <?php if ($job['is_applied']): ?>
                            <button type="button" class="button-outline button-disabled" disabled>Applied</button>
                        <?php else: ?>
                            <button type="button" class="button-primary apply-btn" data-job-id="<?= $job['job_id'] ?>">Apply</button>
                        <?php endif; ?>
                        <?php if ($job['is_saved']): ?>
                            <button type="button" class="button-outline button-disabled" disabled>Saved</button>
                        <?php else: ?>
                            <button type="button" class="button-outline save-btn" data-job-id="<?= $job['job_id'] ?>">Save</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── TAB: SAVED JOBS ── -->
    <div class="tab-panel" id="tab-saved">
        <div class="page-header">
            <h2>Saved Jobs</h2>
            <p>Jobs you've bookmarked for later.</p>
        </div>
        <?php if (empty($saved_jobs)): ?>
            <div class="empty-state">
                <div class="icon">🔖</div>
                <p>No saved jobs yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($saved_jobs as $j): ?>
            <div class="card">
                <div class="card-title"><?= htmlspecialchars($j['title']) ?></div>
                <div class="card-meta">
                    <span>🏢 <?= htmlspecialchars($j['company']) ?></span>
                    <span>📍 <?= htmlspecialchars($j['location']) ?></span>
                    <?php if ($j['salary_range']): ?>
                    <span>💰 <?= htmlspecialchars($j['salary_range']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="badge badge-gold">Saved <?= date('M j, Y', strtotime($j['saved_at'])) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── TAB: APPLICATIONS ── -->
    <div class="tab-panel" id="tab-applied">
        <div class="page-header">
            <h2>Your Applications</h2>
            <p>Track the jobs you have applied to and review your recent submissions.</p>
        </div>
        <?php if (empty($applied_jobs)): ?>
            <div class="empty-state">
                <div class="icon">✉️</div>
                <p>You haven't applied to any jobs yet. Browse open listings and apply to the roles you want.</p>
            </div>
        <?php else: ?>
            <?php foreach ($applied_jobs as $a): ?>
            <div class="card">
                <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
                <div class="card-meta">
                    <span>🏢 <?= htmlspecialchars($a['company']) ?></span>
                    <span>📍 <?= htmlspecialchars($a['location']) ?></span>
                    <?php if ($a['salary_range']): ?>
                    <span>💰 <?= htmlspecialchars($a['salary_range']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="stats-row" style="margin-top:0.75rem">
                    <div class="stat-chip"><strong>Applied</strong> <?= date('M j, Y', strtotime($a['applied_at'])) ?></div>
                    <div class="stat-chip"><?= htmlspecialchars($a['work_type']) ?></div>
                </div>
                <?php if (!empty($a['cover_letter'])): ?>
                <p class="job-description"><strong>Cover note:</strong> <?= nl2br(htmlspecialchars($a['cover_letter'])) ?></p>
                <?php else: ?>
                <p class="job-description">No cover note provided.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── TAB: MESSAGES ── -->
    <div class="tab-panel" id="tab-messages">
        <div class="page-header">
            <h2>Messages</h2>
            <p>Chat with employers and see interview invitations.</p>
        </div>
        <?php if (empty($messages)): ?>
            <div class="empty-state">
                <div class="icon">💬</div>
                <p>No messages yet. Employers can reach out after you apply.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        <div class="card-title"><?= htmlspecialchars($message['subject'] ?: 'No subject') ?></div>
                        <div class="card-meta">
                            <span><?= $message['sender_id'] === $user_id ? 'To' : 'From' ?>: <?= htmlspecialchars($message['sender_id'] === $user_id ? $message['recipient_name'] : $message['sender_name']) ?></span>
                            <?php if (!empty($message['type'])): ?><span>Type: <?= htmlspecialchars($message['type']) ?></span><?php endif; ?>
                            <?php if (!empty($message['scheduled_for'])): ?><span>Interview: <?= date('M j, Y g:i A', strtotime($message['scheduled_for'])) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <span class="badge <?= $message['sender_id'] === $user_id ? 'badge-blue' : 'badge-green' ?>"><?= htmlspecialchars($message['sender_id'] === $user_id ? 'Sent' : 'Received') ?></span>
                </div>
                <p class="job-description" style="margin-top:0.75rem;"><?= nl2br(htmlspecialchars($message['body'])) ?></p>
                <?php if ($message['recipient_id'] === $user_id): ?>
                <details style="margin-top:1rem;border:1px solid var(--border);border-radius:12px;padding:0.75rem;background:var(--surface);">
                    <summary style="font-weight:600;cursor:pointer;color:var(--text);">Reply</summary>
                    <form method="POST" action="send_message.php" style="margin-top:0.85rem;">
                        <input type="hidden" name="send_message" value="1">
                        <input type="hidden" name="recipient_id" value="<?= (int)$message['sender_id'] ?>">
                        <input type="hidden" name="return_to" value="dashboard_student.php">
                        <div class="field-group">
                            <label>Subject</label>
                            <input type="text" name="subject" value="Re: <?= htmlspecialchars($message['subject'] ?: 'Message') ?>" required>
                        </div>
                        <div class="field-group full">
                            <label>Message</label>
                            <textarea name="body" placeholder="Write your reply..." required></textarea>
                        </div>
                        <button type="submit" class="save-btn">Send Reply</button>
                    </form>
                </details>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── TAB: PAST JOBS ── -->
    <div class="tab-panel" id="tab-past">
        <div class="page-header">
            <h2>Past Jobs</h2>
            <p>Jobs you've worked — rate them to help other students.</p>
        </div>
        <?php if (empty($past_jobs)): ?>
            <div class="empty-state">
                <div class="icon">📋</div>
                <p>No past jobs added yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($past_jobs as $j): ?>
            <div class="card" id="card-<?= $j['job_id'] ?>">
                <div class="card-title"><?= htmlspecialchars($j['title']) ?></div>
                <div class="card-meta">
                    <span>🏢 <?= htmlspecialchars($j['company']) ?></span>
                    <span>📍 <?= htmlspecialchars($j['location']) ?></span>
                    <?php if ($j['salary_range']): ?>
                    <span>💰 <?= htmlspecialchars($j['salary_range']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="stats-row" id="stats-<?= $j['job_id'] ?>">
                    <?php if ($j['total_ratings'] > 0): ?>
                        <div class="stat-chip"><strong>Avg</strong> <?= $j['avg_rating'] ?>/5 <span class="stars-gold"><?= stars((int)round($j['avg_rating'])) ?></span></div>
                        <div class="stat-chip"><strong>High</strong> <?= $j['max_rating'] ?>/5</div>
                        <div class="stat-chip"><strong>Low</strong> <?= $j['min_rating'] ?>/5</div>
                        <div class="stat-chip"><strong><?= $j['total_ratings'] ?></strong> rating<?= $j['total_ratings'] != 1 ? 's' : '' ?></div>
                    <?php else: ?>
                        <div class="stat-chip" style="color:var(--muted)">No ratings yet</div>
                    <?php endif; ?>
                </div>

                <?php if ($j['my_rating']): ?>
                    <p class="already-rated">✓ You rated this: <span class="stars-gold"><?= stars((int)$j['my_rating']) ?></span> (<?= $j['my_rating'] ?>/5)</p>
                <?php else: ?>
                    <form class="rating-form" data-job-id="<?= $j['job_id'] ?>">
                        <div class="star-input">
                            <?php for ($s = 5; $s >= 1; $s--): ?>
                            <input type="radio" name="rating" id="s<?= $s ?>-<?= $j['job_id'] ?>" value="<?= $s ?>">
                            <label for="s<?= $s ?>-<?= $j['job_id'] ?>"  title="<?= $s ?> star<?= $s>1?'s':'' ?>">★</label>
                            <?php endfor; ?>
                        </div>
                        <button type="submit" class="rate-submit">Submit Rating</button>
                        <div class="feedback" id="fb-<?= $j['job_id'] ?>"></div>
                    </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── TAB: PROFILE ── -->
    <div class="tab-panel" id="tab-profile">
        <div class="page-header">
            <h2>Edit Your Profile</h2>
            <p>Keep your skills and info up to date for better job matches.</p>
        </div>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        <div class="card">
            <form method="POST" action="save_profile.php" enctype="multipart/form-data">
                <div class="profile-grid">
                    <div class="field-group">
                        <label>Username</label>
                        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                    </div>
                    <div class="field-group">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>
                    <div class="field-group">
                        <label>State</label>
                        <select name="state" required>
                            <option value="">-- Select State --</option>
                            <option value="AL">AL</option>
                            <option value="AK">AK</option>
                            <option value="AZ">AZ</option>
                            <option value="AR">AR</option>
                            <option value="CA">CA</option>
                            <option value="CO">CO</option>
                            <option value="CT">CT</option>
                            <option value="DE">DE</option>
                            <option value="FL">FL</option>
                            <option value="GA">GA</option>
                            <option value="HI">HI</option>
                            <option value="ID">ID</option>
                            <option value="IL">IL</option>
                            <option value="IN">IN</option>
                            <option value="IA">IA</option>
                            <option value="KS">KS</option>
                            <option value="KY">KY</option>
                            <option value="LA">LA</option>
                            <option value="ME">ME</option>
                            <option value="MD">MD</option>
                            <option value="MA">MA</option>
                            <option value="MI">MI</option>
                            <option value="MN">MN</option>
                            <option value="MS">MS</option>
                            <option value="MO">MO</option>
                            <option value="MT">MT</option>
                            <option value="NE">NE</option>
                            <option value="NV">NV</option>
                            <option value="NH">NH</option>
                            <option value="NJ">NJ</option>
                            <option value="NM">NM</option>
                            <option value="NY">NY</option>
                            <option value="NC">NC</option>
                            <option value="ND">ND</option>
                            <option value="OH">OH</option>
                            <option value="OK">OK</option>
                            <option value="OR">OR</option>
                            <option value="PA">PA</option>
                            <option value="RI">RI</option>
                            <option value="SC">SC</option>
                            <option value="SD">SD</option>
                            <option value="TN">TN</option>
                            <option value="TX">TX</option>
                            <option value="UT">UT</option>
                            <option value="VT">VT</option>
                            <option value="VA">VA</option>
                            <option value="WA">WA</option>
                            <option value="WV">WV</option>
                            <option value="WI">WI</option>
                            <option value="WY">WY</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>Zip Code</label>
                        <input type="text" name="zip_code" pattern="[0-9]{5}" maxlength="5" placeholder="e.g. 08028" required>
                    </div>
                    <div class="field-group">
                        <label>Visa Status</label>
                        <select name="visa_status">
                            <option value="">-- Select --</option>
                            <?php foreach (['F1','J1','H1B','OPT','CPT','Green Card','Citizen','Other'] as $v): ?>
                            <option value="<?= $v ?>" <?= ($user['visa_status'] ?? '') === $v ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>Experience (years)</label>
                        <input type="number" name="experience_years" min="0" value="<?= htmlspecialchars($profile['experience_years'] ?? '') ?>" placeholder="e.g. 2">
                    </div>
                    <div class="field-group full">
                        <label>Skills <span style="color:var(--muted);font-weight:400">(comma-separated)</span></label>
                        <textarea name="skills" placeholder="e.g. PHP, JavaScript, SQL, React"><?= htmlspecialchars($profile['skills'] ?? '') ?></textarea>
                    </div>
                    <div class="field-group full">
                        <label>Interests</label>
                        <textarea name="interests" placeholder="e.g. Web Development, Data Science"><?= htmlspecialchars($profile['interests'] ?? '') ?></textarea>
                    </div>
                    <div class="field-group full">
                        <label>Certifications</label>
                        <textarea name="certifications" placeholder="e.g. AWS Certified, Google Analytics"><?= htmlspecialchars($profile['certifications'] ?? '') ?></textarea>
                    </div>
                    <div class="field-group full">
                        <label>Upload Resume <span style="color:var(--muted);font-weight:400">(.pdf only, max 16MB)</span></label>
                        <input type="file" name="resume" accept=".pdf" style="background:var(--surface);border:1px dashed var(--border);color:var(--muted)">
                        <small style="display:block;color:var(--muted);font-size:0.82rem;margin-top:0.5rem;">PDF resumes are stored directly in the database as binary data for security and performance.</small>
                        <?php if (!empty($profile['resume_pdf'])): ?>
                        <small style="color:var(--green)">✓ Resume uploaded (<?= htmlspecialchars($profile['resume_filename'] ?? 'PDF') ?>)</small>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="save-btn">Save Profile</button>
            </form>
        </div>
    </div>

    <!-- ── TAB: VISA INFO ── -->
    <div class="tab-panel" id="tab-visa">
        <div class="page-header">
            <h2>Visa Information</h2>
            <p>Work authorization guidance based on your visa status.</p>
        </div>

        <?php if ($user['role'] === 'international'): ?>
        <div class="visa-hero">
            <div class="visa-status-label">Your current visa status</div>
            <div class="visa-status-value"><?= htmlspecialchars($user['visa_status'] ?? 'Not set') ?></div>
            <p style="color:var(--muted);font-size:0.85rem;margin-top:0.75rem">Update your visa status in the Profile tab.</p>
        </div>
        <div class="visa-info-grid">
            <div class="visa-info-card">
                <h4>🎓 F-1 Student</h4>
                <p>On-campus work up to 20 hrs/week. Off-campus requires CPT or OPT authorization.</p>
            </div>
            <div class="visa-info-card">
                <h4>📋 OPT</h4>
                <p>Full-time work for up to 12 months after graduation. STEM OPT extension available for 24 additional months.</p>
            </div>
            <div class="visa-info-card">
                <h4>🏫 CPT</h4>
                <p>Work authorization for internships or co-ops that are integral to your degree program.</p>
            </div>
            <div class="visa-info-card">
                <h4>💼 H-1B</h4>
                <p>Employer-sponsored visa for specialty occupations. Subject to annual cap and lottery.</p>
            </div>
            <div class="visa-info-card">
                <h4>🔬 J-1</h4>
                <p>Exchange visitor visa. Work authorization depends on program sponsor approval.</p>
            </div>
            <div class="visa-info-card">
                <h4>⚠️ Disclaimer</h4>
                <p>This is general information only. Always consult your DSO or an immigration attorney for advice specific to your situation.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="visa-hero">
            <div class="visa-status-label">Your account type</div>
            <div class="visa-status-value" style="font-size:1.8rem">Student / Alumni</div>
            <p style="color:var(--muted);font-size:0.85rem;margin-top:0.75rem">You are registered as a student or alumni seeking STEM job opportunities.</p>
        </div>
        <div class="visa-info-grid">
            <div class="visa-info-card">
                <h4>✅ Work Authorization</h4>
                <p>Students can indicate their visa status on registration, including U.S. citizens and international STEM students.</p>
            </div>
            <div class="visa-info-card">
                <h4>🎓 Student Jobs</h4>
                <p>Explore STEM internships, co-ops, part-time roles, and entry-level jobs that match your profile.</p>
            </div>
            <div class="visa-info-card">
                <h4>📄 Wrong role?</h4>
                <p>If your account state is incorrect, please contact support or re-register with the correct student role.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /content -->

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

function handleApplicationButton(button) {
    button.addEventListener('click', async function() {
        const jobId = this.dataset.jobId;
        if (!jobId) return;

        const fd = new FormData();
        fd.append('job_id', jobId);
        fd.append('cover_letter', '');

        this.disabled = true;
        this.textContent = 'Applying...';

        try {
            const res = await fetch('apply_job.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                this.textContent = 'Applied';
                this.classList.add('button-disabled');
                this.classList.remove('button-primary');
            } else {
                this.textContent = 'Apply';
                this.disabled = false;
                alert(data.error || 'Unable to submit your application.');
            }
        } catch (err) {
            this.textContent = 'Apply';
            this.disabled = false;
            alert('Unable to submit your application. Please try again.');
        }
    });
}

function handleSaveButton(button) {
    button.addEventListener('click', async function() {
        const jobId = this.dataset.jobId;
        if (!jobId) return;

        const fd = new FormData();
        fd.append('job_id', jobId);
        fd.append('action', 'save');

        this.disabled = true;
        this.textContent = 'Saving...';

        try {
            const res = await fetch('save_job.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                this.textContent = 'Saved';
                this.classList.add('button-disabled');
                this.classList.remove('button-outline');
            } else {
                this.textContent = 'Save';
                this.disabled = false;
                alert(data.error || 'Unable to save the job.');
            }
        } catch (err) {
            this.textContent = 'Save';
            this.disabled = false;
            alert('Unable to save the job. Please try again.');
        }
    });
}

document.querySelectorAll('.apply-btn').forEach(handleApplicationButton);
document.querySelectorAll('.save-btn[data-job-id]').forEach(handleSaveButton);

// Rating forms
document.querySelectorAll('.rating-form').forEach(function(form) {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const jobId    = form.dataset.jobId;
        const selected = form.querySelector('input[name="rating"]:checked');
        const fb       = document.getElementById('fb-' + jobId);

        if (!selected) {
            fb.textContent = 'Please select a star rating.';
            fb.className   = 'feedback err';
            return;
        }

        const fd = new FormData();
        fd.append('job_id', jobId);
        fd.append('rating', selected.value);

        try {
            const res  = await fetch('rate_job.php', { method:'POST', body:fd });
            const data = await res.json();

            if (data.success) {
                const filled = parseInt(selected.value);
                form.innerHTML = `<p class="already-rated">✓ You rated this: <span class="stars-gold">${'★'.repeat(filled)}${'☆'.repeat(5-filled)}</span> (${filled}/5)</p>`;
                document.getElementById('stats-' + jobId).innerHTML = `
                    <div class="stat-chip"><strong>Avg</strong> ${data.avg_rating}/5</div>
                    <div class="stat-chip"><strong>High</strong> ${data.max_rating}/5</div>
                    <div class="stat-chip"><strong>Low</strong> ${data.min_rating}/5</div>
                    <div class="stat-chip"><strong>${data.total_ratings}</strong> rating${data.total_ratings!=1?'s':''}</div>
                `;
            } else {
                fb.textContent = data.error;
                fb.className   = 'feedback err';
            }
        } catch(err) {
            fb.textContent = 'Something went wrong. Please try again.';
            fb.className   = 'feedback err';
        }
    });
});
</script>
</body>
</html>
