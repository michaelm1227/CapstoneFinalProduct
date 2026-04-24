<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user info & enforce role
$user_stmt = $connection->prepare("SELECT username, email, role FROM Users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!$user || $user['role'] !== 'employer') {
    header('Location: dashboard_student.php');
    exit();
}

$tag_options = [
    'Python', 'JavaScript', 'TypeScript', 'Java', 'C', 'C++', 'C#', 'Go', 'Rust', 'Ruby', 'Swift', 'Kotlin', 'R', 'MATLAB', 'Scala', 'Julia', 'Dart', 'Elixir', 'Haskell', 'Perl', 'PHP', 'Lua',
    'Machine Learning', 'Deep Learning', 'Artificial Intelligence', 'Computer Vision', 'NLP', 'Reinforcement Learning', 'Generative AI', 'LLMs', 'MLOps', 'Feature Engineering', 'Data Augmentation', 'Transfer Learning', 'Neural Networks', 'Transformers', 'Diffusion Models',
    'Data Science', 'Analytics', 'Big Data', 'Data Engineering', 'Data Visualization', 'Business Intelligence', 'Statistics', 'A/B Testing', 'ETL', 'Data Pipelines', 'Data Warehousing', 'Time Series', 'Forecasting', 'Pandas', 'Spark',
    'Cloud', 'AWS', 'Azure', 'GCP', 'Kubernetes', 'Docker', 'Terraform', 'Serverless', 'Microservices', 'Infrastructure as Code', 'Edge Computing', 'Bare Metal', 'VMware', 'OpenStack',
    'DevOps', 'SRE', 'CI/CD', 'Monitoring', 'Observability', 'Incident Response', 'Chaos Engineering', 'GitOps', 'Ansible', 'Jenkins', 'GitHub Actions',
    'Web Development', 'Frontend', 'Backend', 'Full Stack', 'React', 'Vue', 'Angular', 'React Native', 'Next.js', 'Node.js', 'REST APIs', 'GraphQL', 'WebSockets', 'Progressive Web Apps',
    'Mobile', 'Android', 'iOS', 'Flutter',
    'SQL', 'NoSQL', 'PostgreSQL', 'MySQL', 'MongoDB', 'Redis', 'Elasticsearch', 'Cassandra', 'Neo4j', 'Data Modeling', 'Database Administration', 'Vector Databases',
    'Cybersecurity', 'Penetration Testing', 'Application Security', 'Network Security', 'Zero Trust', 'Cryptography', 'SIEM', 'Threat Modeling', 'Compliance', 'SOC',
    'UI/UX', 'Product Design', 'User Research', 'Interaction Design', 'Accessibility', 'Design Systems', 'Figma', 'Prototyping', 'Information Architecture',
    'Networking', 'TCP/IP', 'Distributed Systems', 'Operating Systems', 'Linux', 'Embedded Systems', 'FPGA', 'Firmware', 'Low-Level Programming', 'Compilers', 'Real-Time Systems',
    'Hardware', 'Electronics', 'PCB Design', 'IoT', 'Semiconductors', 'ASIC Design', 'Signal Processing', '3D Printing', 'Mechatronics', 'CAD', 'Sensors',
    'Blockchain', 'Web3', 'Quantum Computing', 'AR/VR', 'Digital Twins', 'Edge AI', 'Autonomous Systems', 'Robotics', 'Drone Technology', 'Wearables', 'Research',
    'Biotech', 'Bioinformatics', 'Computational Biology', 'Genomics', 'Neuroscience', 'Chemistry', 'Physics', 'Climate Tech', 'Materials Science', 'Nanotechnology', 'Space Tech', 'HealthTech', 'FinTech', 'EdTech', 'AgriTech', 'LegalTech', 'GovTech', 'Clean Energy', 'Geospatial', 'Logistics Tech', 'Manufacturing',
    'STEM', 'SaaS', 'Quality Assurance', 'Technical Writing', 'Open Source', 'System Design', 'Architecture', 'Agile', 'Testing', 'Performance Engineering', 'Developer Tools', 'SDK / API Design', 'Digital Marketing', 'Ops', 'Product Management', 'Sales Engineering', 'Solutions Architecture', 'Technical Recruiting'
];

// Ensure the applications table exists for employer reporting.
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

// Fetch jobs posted by this employer
$jobs_stmt = $connection->prepare("
    SELECT j.job_id, j.title, j.company, j.state, j.zip_code, j.salary_range, j.posted_at, j.expires_at,
           ROUND(AVG(jr.rating), 2) AS avg_rating,
           MIN(jr.rating) AS min_rating,
           MAX(jr.rating) AS max_rating,
           COUNT(DISTINCT jr.rating_id) AS total_ratings,
           COUNT(DISTINCT a.application_id) AS application_count
    FROM Jobs j
    LEFT JOIN JobRatings jr ON jr.job_id = j.job_id
    LEFT JOIN JobApplications a ON a.job_id = j.job_id
    WHERE j.posted_by = ?
    GROUP BY j.job_id, j.title, j.company, j.state, j.zip_code, j.salary_range, j.posted_at, j.expires_at
    ORDER BY j.posted_at DESC
");
$jobs_stmt->bind_param("i", $user_id);
$jobs_stmt->execute();
$my_jobs = $jobs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$jobs_stmt->close();

// Fetch applicant details for this employer's jobs
$applicant_stmt = $connection->prepare("
    SELECT a.application_id, a.job_id, a.cover_letter, a.applied_at,
           u.user_id, u.username, u.email, u.location, u.visa_status,
           p.skills, p.interests, p.certifications, p.experience_years, p.resume_pdf,
           j.title AS job_title, j.company AS job_company
    FROM JobApplications a
    JOIN Jobs j ON j.job_id = a.job_id
    JOIN Users u ON u.user_id = a.user_id
    LEFT JOIN UserProfiles p ON p.user_id = u.user_id
    WHERE j.posted_by = ?
    ORDER BY a.applied_at DESC
");
$applicant_stmt->bind_param("i", $user_id);
$applicant_stmt->execute();
$applicants = $applicant_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$applicant_stmt->close();

// Candidate search filters
$candidate_keyword    = trim($_GET['candidate_keyword'] ?? '');
$candidate_visa       = $_GET['candidate_visa_status'] ?? '';
$candidate_location   = trim($_GET['candidate_location'] ?? '');
$candidate_experience = max(0, (int)($_GET['candidate_experience'] ?? 0));
$candidate_tags       = array_filter((array)($_GET['candidate_tags'] ?? []));
$candidate_results    = [];
$candidate_sql = "SELECT u.user_id, u.username, u.email, u.location, u.visa_status, p.skills, p.interests, p.certifications, p.experience_years, p.resume_pdf
    FROM Users u
    JOIN UserProfiles p ON u.user_id = p.user_id
    WHERE u.role != 'employer'";
$candidate_types = '';
$candidate_params = [];

if ($candidate_keyword !== '') {
    $candidate_sql .= " AND LOWER(CONCAT_WS(' ', p.skills, p.interests, p.certifications, u.location, u.username)) LIKE ?";
    $candidate_types .= 's';
    $candidate_params[] = '%' . mb_strtolower($candidate_keyword) . '%';
}
if ($candidate_visa !== '') {
    $candidate_sql .= " AND u.visa_status = ?";
    $candidate_types .= 's';
    $candidate_params[] = $candidate_visa;
}
if ($candidate_location !== '') {
    $candidate_sql .= " AND LOWER(u.location) LIKE ?";
    $candidate_types .= 's';
    $candidate_params[] = '%' . mb_strtolower($candidate_location) . '%';
}
if ($candidate_experience > 0) {
    $candidate_sql .= " AND COALESCE(p.experience_years, 0) >= ?";
    $candidate_types .= 'i';
    $candidate_params[] = $candidate_experience;
}
if (!empty($candidate_tags)) {
    $tag_conditions = [];
    foreach ($candidate_tags as $tag) {
        $tag_conditions[] = "LOWER(CONCAT_WS(' ', p.skills, p.interests, p.certifications)) LIKE ?";
        $candidate_types .= 's';
        $candidate_params[] = '%' . mb_strtolower($tag) . '%';
    }
    $candidate_sql .= ' AND (' . implode(' OR ', $tag_conditions) . ')';
}
$candidate_sql .= " ORDER BY COALESCE(p.experience_years, 0) DESC, u.username ASC LIMIT 100";
$candidate_stmt = $connection->prepare($candidate_sql);
if ($candidate_stmt) {
    if ($candidate_types !== '') {
        $bind_params = [];
        $bind_params[] = &$candidate_types;
        foreach ($candidate_params as $key => $value) {
            $bind_params[] = &$candidate_params[$key];
        }
        call_user_func_array([$candidate_stmt, 'bind_param'], $bind_params);
    }
    $candidate_stmt->execute();
    $candidate_results = $candidate_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $candidate_stmt->close();
}

// Messages for employer
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

$conversation_threads = [];
foreach ($messages as $message) {
    $partner_id = $message['sender_id'] === $user_id ? $message['recipient_id'] : $message['sender_id'];
    $partner_name = $message['sender_id'] === $user_id ? $message['recipient_name'] : $message['sender_name'];
    if (!isset($conversation_threads[$partner_id])) {
        $conversation_threads[$partner_id] = [
            'partner_id' => $partner_id,
            'partner_name' => $partner_name,
            'messages' => []
        ];
    }
    $conversation_threads[$partner_id]['messages'][] = $message;
}

function stars(int $n, int $total = 5): string {
    return str_repeat('★', $n) . str_repeat('☆', $total - $n);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard – Careerify</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #0d0f1a;
            --surface:  #151826;
            --card:     #1c1f32;
            --border:   #272b42;
            --accent:   #f59e0b;
            --accent2:  #fbbf24;
            --blue:     #5b7fff;
            --green:    #34d399;
            --red:      #f87171;
            --text:     #e8eaf6;
            --muted:    #7b82a8;
            --radius:   12px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

        /* NAVBAR */
        .navbar { background:var(--surface); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:100; }
        .nav-inner {
            max-width:1100px; margin:0 auto; padding:0 1.5rem;
            display:flex; align-items:center; gap:0.5rem;
        }
        .nav-brand {
            font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:800;
            color:var(--accent); padding:1rem 0; margin-right:1rem;
            white-space:nowrap; text-decoration:none;
        }
        .nav-tabs { display:flex; gap:0.25rem; flex:1; overflow-x:auto; scrollbar-width:none; }
        .nav-tabs::-webkit-scrollbar { display:none; }
        .tab-btn {
            background:none; border:none; color:var(--muted);
            font-family:'DM Sans',sans-serif; font-size:0.88rem; font-weight:500;
            padding:1.1rem 1rem; cursor:pointer; white-space:nowrap;
            border-bottom:2px solid transparent;
            transition:color 0.2s, border-color 0.2s;
            width:auto; margin-bottom:0; border-radius:0;
        }
        .tab-btn:hover { color:var(--text); }
        .tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
        .nav-user { display:flex; align-items:center; gap:0.75rem; margin-left:auto; padding-left:1rem; }
        .avatar {
            width:34px; height:34px; border-radius:50%;
            background:linear-gradient(135deg, var(--accent), var(--accent2));
            display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:0.85rem; color:#000; flex-shrink:0;
        }
        .nav-username { font-size:0.88rem; color:var(--muted); }
        .logout-btn {
            background:none; border:1px solid var(--border);
            color:var(--muted); font-size:0.8rem;
            padding:0.35rem 0.75rem; border-radius:6px;
            cursor:pointer; transition:all 0.2s;
            width:auto; margin-bottom:0; font-family:'DM Sans',sans-serif;
        }
        .logout-btn:hover { border-color:var(--red); color:var(--red); background:none; }

        /* CONTENT */
        .content { max-width:1100px; margin:0 auto; padding:2rem 1.5rem; }
        .tab-panel { display:none; animation:fadeUp 0.25s ease; }
        .tab-panel.active { display:block; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

        .page-header { margin-bottom:1.75rem; }
        .page-header h2 { font-family:'Syne',sans-serif; font-size:1.6rem; font-weight:800; }
        .page-header p { color:var(--muted); font-size:0.9rem; margin-top:0.25rem; }

        /* CARDS */
        .card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:1.25rem 1.5rem; margin-bottom:1rem; transition:border-color 0.2s; }
        .card:hover { border-color:var(--accent); }
        .card-title { font-weight:600; font-size:1rem; margin-bottom:0.25rem; }
        .card-meta { font-size:0.85rem; color:var(--muted); margin-bottom:0.75rem; }
        .card-meta span { margin-right:1rem; }
        .badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:20px; font-size:0.75rem; font-weight:600; }
        .badge-amber { background:rgba(245,158,11,0.15); color:var(--accent); }
        .badge-green { background:rgba(52,211,153,0.15); color:var(--green); }
        .badge-blue  { background:rgba(91,127,255,0.15); color:var(--blue); }

        .stats-row { display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:0.75rem; }
        .stat-chip { font-size:0.8rem; color:var(--muted); background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:0.3rem 0.65rem; }
        .stat-chip strong { color:var(--text); }
        .stars-gold { color:var(--accent2); letter-spacing:1px; }

        /* POST JOB FORM */
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; }
        @media(max-width:600px){ .form-grid{grid-template-columns:1fr;} }
        .field-group { display:flex; flex-direction:column; gap:0.4rem; }
        .field-group.full { grid-column:1/-1; }
        .field-group label { font-size:0.82rem; color:var(--muted); font-weight:500; }
        .field-group input,
        .field-group select,
        .field-group textarea {
            background:var(--surface); border:1px solid var(--border); border-radius:8px;
            color:var(--text); font-family:'DM Sans',sans-serif; font-size:0.9rem;
            padding:0.65rem 0.9rem; transition:border-color 0.2s; margin-bottom:0; width:100%;
        }
        .field-group input:focus, .field-group select:focus, .field-group textarea:focus {
            outline:none; border-color:var(--accent); background:var(--surface);
            box-shadow:0 0 0 3px rgba(245,158,11,0.12);
        }
        .field-group textarea { resize:vertical; min-height:100px; }
        .post-btn {
            background:var(--accent); color:#000; border:none;
            padding:0.75rem 2rem; border-radius:8px;
            font-family:'DM Sans',sans-serif; font-size:0.95rem; font-weight:700;
            cursor:pointer; margin-top:0.5rem; transition:opacity 0.2s;
            width:auto; margin-bottom:0;
        }
        .post-btn:hover { opacity:0.88; background:var(--accent); }
        .delete-btn {
            background:transparent; border:1px solid rgba(248,113,113,0.35);
            color:var(--red); border-radius:8px; padding:0.6rem 1rem;
            font-family:'DM Sans',sans-serif; font-size:0.9rem; cursor:pointer;
            transition:all 0.2s; margin-top:0.5rem;
        }
        .delete-btn:hover { background:rgba(248,113,113,0.08); border-color:var(--red); }

        /* EMPTY */
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--muted); font-size:0.95rem; }
        .empty-state .icon { font-size:2.5rem; margin-bottom:0.75rem; }

        /* SUCCESS / ERROR */
        .alert { padding:0.85rem 1.1rem; border-radius:8px; font-size:0.9rem; margin-bottom:1.25rem; }
        .alert-success { background:rgba(52,211,153,0.12); border:1px solid var(--green); color:var(--green); }
        .alert-error   { background:rgba(248,113,113,0.12); border:1px solid var(--red);   color:var(--red); }
    </style>
</head>
<body>

<?php
// Handle job delete and post form submission
$post_error     = '';
$post_success   = '';
$delete_success = '';
$delete_error   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
    $delete_id = (int)($_POST['job_id'] ?? 0);
    if ($delete_id > 0) {
        $check_stmt = $connection->prepare('SELECT posted_by FROM Jobs WHERE job_id = ? LIMIT 1');
        $check_stmt->bind_param('i', $delete_id);
        $check_stmt->execute();
        $job_owner = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($job_owner && (int)$job_owner['posted_by'] === $user_id) {
            $del = $connection->prepare('DELETE FROM Jobs WHERE job_id = ? AND posted_by = ?');
            $del->bind_param('ii', $delete_id, $user_id);
            if ($del->execute()) {
                $delete_success = 'Job removed successfully.';
            } else {
                $delete_error = 'Unable to delete job. Please try again.';
            }
            $del->close();
        } else {
            $delete_error = 'Cannot delete this job. It may not belong to you.';
        }
    } else {
        $delete_error = 'Invalid job selected for deletion.';
    }

    if (!empty($delete_success)) {
        $jobs_stmt2 = $connection->prepare("SELECT j.job_id, j.title, j.company, j.state, j.zip_code, j.salary_range, j.posted_at, j.expires_at,
                       ROUND(AVG(jr.rating),2) AS avg_rating, MIN(jr.rating) AS min_rating,
                       MAX(jr.rating) AS max_rating, COUNT(jr.rating_id) AS total_ratings,
                       COUNT(DISTINCT a.application_id) AS application_count
                FROM Jobs j
                LEFT JOIN JobRatings jr ON jr.job_id=j.job_id
                LEFT JOIN JobApplications a ON a.job_id=j.job_id
                WHERE j.posted_by=? GROUP BY j.job_id, j.title, j.company, j.state, j.zip_code, j.salary_range, j.posted_at, j.expires_at ORDER BY j.posted_at DESC");
        $jobs_stmt2->bind_param("i", $user_id);
        $jobs_stmt2->execute();
        $my_jobs = $jobs_stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $jobs_stmt2->close();
    }
}

$post_error   = '';
$post_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_job'])) {
    $title      = trim($_POST['title'] ?? '');
    $company    = trim($_POST['company'] ?? '');
    $state      = trim($_POST['state'] ?? '');
    $zip_code   = trim($_POST['zip_code'] ?? '');
    $work_type  = $_POST['work_type'] ?? '';
    $sal_min    = (int)($_POST['salary_min'] ?? 0);
    $sal_max    = (int)($_POST['salary_max'] ?? 0);
    $desc       = trim($_POST['description'] ?? '');
    $req        = trim($_POST['requirements'] ?? '');
    $selected_tags = array_filter(array_map('trim', (array)($_POST['tags'] ?? [])));
    $selected_tags = array_values(array_intersect($selected_tags, $tag_options));
    $tags       = implode(', ', $selected_tags);
    $exp_date   = $_POST['expiration_date'] ?? '';
    $salary_range = '$' . number_format($sal_min) . ' – $' . number_format($sal_max);

    if (empty($title) || empty($company) || empty($desc)) {
        $post_error = 'Title, company, and description are required.';
    } else {
        $ins = $connection->prepare("
            INSERT INTO Jobs (title, company, state, zip_code, description, requirements, tags, salary_range, work_type, expires_at, posted_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param("ssssssssssi", $title, $company, $state, $zip_code, $desc, $req, $tags, $salary_range, $work_type, $exp_date, $user_id);
        if ($ins->execute()) {
            $post_success = "Job \"$title\" posted successfully!";
            // Refresh job list
            $jobs_stmt2 = $connection->prepare("
                SELECT j.job_id, j.title, j.company, j.state, j.zip_code, j.salary_range, j.posted_at, j.expires_at,
                       ROUND(AVG(jr.rating),2) AS avg_rating, MIN(jr.rating) AS min_rating,
                       MAX(jr.rating) AS max_rating, COUNT(jr.rating_id) AS total_ratings
                FROM Jobs j LEFT JOIN JobRatings jr ON jr.job_id=j.job_id
                WHERE j.posted_by=? GROUP BY j.job_id ORDER BY j.posted_at DESC
            ");
            $jobs_stmt2->bind_param("i", $user_id);
            $jobs_stmt2->execute();
            $my_jobs = $jobs_stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $jobs_stmt2->close();
        } else {
            $post_error = 'Failed to post job: ' . htmlspecialchars($connection->error);
        }
        $ins->close();
    }
}
?>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-inner">
        <a class="nav-brand" href="#">⚡ Careerify</a>
        <div class="nav-tabs">
            <button class="tab-btn active" onclick="switchTab('post',this)">➕ Post a Job</button>
            <button class="tab-btn" onclick="switchTab('search',this)">🔍 Candidate Search</button>
            <button class="tab-btn" onclick="switchTab('listings',this)">📋 My Listings</button>
            <button class="tab-btn" onclick="switchTab('applicants',this)">👥 Applicants</button>
            <button class="tab-btn" onclick="switchTab('messages',this)">💬 Messages</button>
            <button class="tab-btn" onclick="switchTab('ratings',this)">⭐ Job Ratings</button>
        </div>
        <div class="nav-user">
            <div class="avatar"><?= strtoupper(substr($user['username'],0,1)) ?></div>
            <span class="nav-username"><?= htmlspecialchars($user['username']) ?></span>
            <form method="POST" action="logout.php" style="margin:0">
                <button type="submit" class="logout-btn">Log out</button>
            </form>
        </div>
    </div>
</nav>

<div class="content">

    <!-- ── TAB: POST A JOB ── -->
    <div class="tab-panel active" id="tab-post">
        <div class="page-header">
            <h2>Post a Job</h2>
            <p>Publish STEM-focused roles with tags and state-based location to attract the right candidates.</p>
        </div>

        <?php if (!empty($post_success)): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($post_success) ?></div>
        <?php endif; ?>
        <?php if (!empty($post_error)): ?>
            <div class="alert alert-error">✗ <?= htmlspecialchars($post_error) ?></div>
        <?php endif; ?>
        <?php if (!empty($delete_success)): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($delete_success) ?></div>
        <?php endif; ?>
        <?php if (!empty($delete_error)): ?>
            <div class="alert alert-error">✗ <?= htmlspecialchars($delete_error) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <input type="hidden" name="post_job" value="1">
                <div class="form-grid">
                    <div class="field-group">
                        <label>Job Title *</label>
                        <input type="text" name="title" placeholder="e.g. Software Developer" required>
                    </div>
                    <div class="field-group">
                        <label>Company Name *</label>
                        <input type="text" name="company" placeholder="e.g. Tech Corp" required>
                    </div>
                    <div class="field-group">
                        <label>State *</label>
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
                        <label>Zip Code *</label>
                        <input type="text" name="zip_code" pattern="[0-9]{5}" maxlength="5" placeholder="e.g. 08028" required>
                    </div>
                    <div class="field-group">
                        <label>Work Type *</label>
                        <select name="work_type" required>
                            <option value="">-- Select --</option>
                            <option value="remote">Remote</option>
                            <option value="hybrid">Hybrid</option>
                            <option value="onsite">Onsite</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>Expiration Date *</label>
                        <input type="date" name="expiration_date" required>
                    </div>
                    <div class="field-group">
                        <label>Salary Min ($) *</label>
                        <input type="number" name="salary_min" placeholder="e.g. 60000" required>
                    </div>
                    <div class="field-group">
                        <label>Salary Max ($) *</label>
                        <input type="number" name="salary_max" placeholder="e.g. 90000" required>
                    </div>
                    <div class="field-group full">
                        <label>Job Description *</label>
                        <textarea name="description" placeholder="Describe the role, responsibilities, team..." required></textarea>
                    </div>
                    <div class="field-group full">
                        <label>Requirements</label>
                        <textarea name="requirements" placeholder="Required skills, experience, education..."></textarea>
                    </div>
                    <div class="field-group full">
                        <label>Select Tags *</label>
                        <div class="tag-grid" style="display:flex;flex-wrap:wrap;gap:0.6rem;margin-top:0.75rem;">
                            <?php foreach ($tag_options as $tag): ?>
                            <label style="display:inline-flex;align-items:center;gap:0.45rem;padding:0.5rem 0.85rem;border:1px solid var(--border);border-radius:999px;background:var(--surface);cursor:pointer;">
                                <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag) ?>" style="width:auto;">
                                <?= htmlspecialchars($tag) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <small style="display:block;color:var(--muted);font-size:0.82rem;margin-top:0.75rem;">Pick tags to help STEM students find this role.</small>
                    </div>
                </div>
                <button type="submit" class="post-btn">Post Job →</button>
            </form>
        </div>
    </div>

    <!-- ── TAB: MY LISTINGS ── -->
    <div class="tab-panel" id="tab-listings">
        <div class="page-header">
            <h2>My Job Listings</h2>
            <p>All positions you've posted.</p>
        </div>
        <?php if (empty($my_jobs)): ?>
            <div class="empty-state">
                <div class="icon">📋</div>
                <p>You haven't posted any jobs yet. Use the Post a Job tab to get started.</p>
            </div>
        <?php else: ?>
            <?php foreach ($my_jobs as $j): ?>
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem">
                    <div>
                        <div class="card-title"><?= htmlspecialchars($j['title']) ?></div>
                        <div class="card-meta">
                            <span>🏢 <?= htmlspecialchars($j['company']) ?></span>
                            <span>📍 <?= htmlspecialchars($j['state'] . ' ' . $j['zip_code']) ?></span>
                            <?php if ($j['salary_range']): ?>
                            <span>💰 <?= htmlspecialchars($j['salary_range']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:0.5rem;align-items:center;">
                        <span class="badge badge-amber">Posted <?= date('M j, Y', strtotime($j['posted_at'])) ?></span>
                        <?php if ($j['expires_at']): ?>
                        <span class="badge badge-gold">Expires <?= date('M j, Y', strtotime($j['expires_at'])) ?></span>
                        <?php endif; ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="delete_job" value="1">
                            <input type="hidden" name="job_id" value="<?= (int)$j['job_id'] ?>">
                            <button type="submit" class="delete-btn" onclick="return confirm('Delete this job and all related applications?');">Delete</button>
                        </form>
                    </div>
                </div>
                <div class="stats-row">
                    <?php if ($j['total_ratings'] > 0): ?>
                        <div class="stat-chip"><strong>Avg</strong> <?= $j['avg_rating'] ?>/5 <span class="stars-gold"><?= stars((int)round($j['avg_rating'])) ?></span></div>
                        <div class="stat-chip"><strong>High</strong> <?= $j['max_rating'] ?>/5</div>
                        <div class="stat-chip"><strong>Low</strong> <?= $j['min_rating'] ?>/5</div>
                        <div class="stat-chip"><strong><?= $j['total_ratings'] ?></strong> rating<?= $j['total_ratings']!=1?'s':'' ?></div>
                    <?php else: ?>
                        <div class="stat-chip" style="color:var(--muted)">No ratings yet</div>
                    <?php endif; ?>
                    <div class="stat-chip"><strong><?= (int)$j['application_count'] ?></strong> applicant<?= $j['application_count'] != 1 ? 's' : '' ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── TAB: CANDIDATE SEARCH ── -->
    <div class="tab-panel" id="tab-search">
        <div class="page-header">
            <h2>Candidate Search</h2>
            <p>Find students by skills, tags, visa status, location, and experience.</p>
        </div>
        <div class="card">
            <form method="GET">
                <input type="hidden" name="search_candidates" value="1">
                <div class="form-grid">
                    <div class="field-group">
                        <label>Keywords</label>
                        <input type="text" name="candidate_keyword" value="<?= htmlspecialchars($candidate_keyword) ?>" placeholder="e.g. python, data science, STEM">
                    </div>
                    <div class="field-group">
                        <label>Visa Status</label>
                        <select name="candidate_visa_status">
                            <option value="">-- Any --</option>
                            <?php foreach (['F1','J1','H1B','OPT','CPT','Green Card','Citizen','Other'] as $visa): ?>
                            <option value="<?= $visa ?>" <?= $candidate_visa === $visa ? 'selected' : '' ?>><?= $visa ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>Location</label>
                        <input type="text" name="candidate_location" value="<?= htmlspecialchars($candidate_location) ?>" placeholder="City, State, or remote">
                    </div>
                    <div class="field-group">
                        <label>Minimum Experience</label>
                        <input type="number" min="0" name="candidate_experience" value="<?= $candidate_experience ?>" placeholder="Years">
                    </div>
                    <div class="field-group full">
                        <label>Tags</label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.5rem;">
                            <?php foreach ($tag_options as $tag): ?>
                            <label style="display:inline-flex;align-items:center;gap:0.45rem;padding:0.6rem 0.9rem;border:1px solid var(--border);border-radius:999px;background:var(--surface);cursor:pointer;">
                                <input type="checkbox" name="candidate_tags[]" value="<?= htmlspecialchars($tag) ?>" <?= in_array($tag, $candidate_tags, true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($tag) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <button type="submit" class="post-btn">Search Candidates</button>
            </form>
        </div>
        <?php if (empty($candidate_results)): ?>
            <div class="empty-state">
                <div class="icon">🔎</div>
                <p>No candidates found with the current filters. Try broader search terms or clear filters.</p>
            </div>
        <?php else: ?>
            <?php foreach ($candidate_results as $candidate): ?>
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        <div class="card-title"><?= htmlspecialchars($candidate['username']) ?></div>
                        <div class="card-meta">
                            <span>📧 <?= htmlspecialchars($candidate['email']) ?></span>
                            <span>📍 <?= htmlspecialchars($candidate['location'] ?: 'Not set') ?></span>
                            <span>🛂 <?= htmlspecialchars($candidate['visa_status'] ?: 'Unknown') ?></span>
                            <span>💼 <?= htmlspecialchars($candidate['experience_years'] ?? '0') ?> yrs</span>
                        </div>
                    </div>
                    <span class="badge badge-amber">Student</span>
                </div>
                <div class="stats-row">
                    <?php if (!empty($candidate['skills'])): ?><div class="stat-chip"><strong>Skills</strong> <?= htmlspecialchars($candidate['skills']) ?></div><?php endif; ?>
                    <?php if (!empty($candidate['interests'])): ?><div class="stat-chip"><strong>Interests</strong> <?= htmlspecialchars($candidate['interests']) ?></div><?php endif; ?>
                    <?php if (!empty($candidate['certifications'])): ?><div class="stat-chip"><strong>Certs</strong> <?= htmlspecialchars($candidate['certifications']) ?></div><?php endif; ?>
                </div>
                <?php if (!empty($candidate['resume_pdf'])): ?>
                <p class="job-description"><a href="view_resume.php?user_id=<?= (int)$candidate['user_id'] ?>" target="_blank" rel="noopener noreferrer" style="color:var(--accent);">View resume</a></p>
                <?php endif; ?>
                <p class="job-description"><a href="view_profile.php?user_id=<?= (int)$candidate['user_id'] ?>" class="button-outline" style="display:inline-block;margin-top:0.75rem;">View full profile</a></p>
                <details style="margin-top:1rem;border:1px solid var(--border);border-radius:12px;padding:0.75rem;background:var(--surface);">
                    <summary style="font-weight:600;cursor:pointer;color:var(--text);">Message or schedule interview</summary>
                    <form method="POST" action="send_message.php" style="margin-top:0.85rem;">
                        <input type="hidden" name="send_message" value="1">
                        <input type="hidden" name="recipient_id" value="<?= (int)$candidate['user_id'] ?>">
                        <input type="hidden" name="return_to" value="dashboard_employer.php">
                        <div class="field-group">
                            <label>Subject</label>
                            <input type="text" name="subject" placeholder="Interview invitation or quick note" required>
                        </div>
                        <div class="field-group full">
                            <label>Message</label>
                            <textarea name="body" placeholder="Write your message here..." required></textarea>
                        </div>
                        <div class="field-group">
                            <label>Interview Date/Time (optional)</label>
                            <input type="datetime-local" name="scheduled_for">
                        </div>
                        <button type="submit" class="post-btn">Send Message</button>
                    </form>
                </details>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── TAB: APPLICANTS ── -->
    <div class="tab-panel" id="tab-applicants">
        <div class="page-header">
            <h2>Applicant Activity</h2>
            <p>See students who have applied to your job listings.</p>
        </div>
        <?php if (empty($applicants)): ?>
            <div class="empty-state">
                <div class="icon">👥</div>
                <p>No applications yet. Once students apply, you’ll see them listed here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($applicants as $app): ?>
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        <div class="card-title"><?= htmlspecialchars($app['username']) ?> applied to <?= htmlspecialchars($app['job_title']) ?></div>
                        <div class="card-meta">
                            <span>📧 <?= htmlspecialchars($app['email']) ?></span>
                            <span>📍 <?= htmlspecialchars($app['location'] ?: 'Location not set') ?></span>
                            <span>🛂 <?= htmlspecialchars($app['visa_status'] ?: 'Visa unknown') ?></span>
                            <span>💼 <?= htmlspecialchars($app['experience_years'] ?? '0') ?> yrs</span>
                        </div>
                    </div>
                    <span class="badge badge-green">Applied <?= date('M j, Y', strtotime($app['applied_at'])) ?></span>
                </div>
                <div class="stats-row" style="margin-top:0.75rem;">
                    <?php if (!empty($app['skills'])): ?><div class="stat-chip"><strong>Skills</strong> <?= htmlspecialchars($app['skills']) ?></div><?php endif; ?>
                    <?php if (!empty($app['interests'])): ?><div class="stat-chip"><strong>Interests</strong> <?= htmlspecialchars($app['interests']) ?></div><?php endif; ?>
                    <?php if (!empty($app['certifications'])): ?><div class="stat-chip"><strong>Certs</strong> <?= htmlspecialchars($app['certifications']) ?></div><?php endif; ?>
                </div>
                <?php if (!empty($app['resume_pdf'])): ?>
                <p class="job-description"><a href="view_resume.php?user_id=<?= (int)$app['user_id'] ?>" target="_blank" rel="noopener noreferrer" style="color:var(--accent);">View full resume</a></p>
                <?php endif; ?>
                <p class="job-description"><a href="view_profile.php?user_id=<?= (int)$app['user_id'] ?>" class="button-outline" style="display:inline-block;margin-top:0.75rem;">View full profile</a></p>
                <?php if (!empty($app['cover_letter'])): ?>
                <p class="job-description"><strong>Cover note:</strong> <?= nl2br(htmlspecialchars($app['cover_letter'])) ?></p>
                <?php else: ?>
                <p class="job-description">No cover note provided.</p>
                <?php endif; ?>
                <details style="margin-top:1rem;border:1px solid var(--border);border-radius:12px;padding:0.75rem;background:var(--surface);">
                    <summary style="font-weight:600;cursor:pointer;color:var(--text);">Message <?= htmlspecialchars($app['username']) ?></summary>
                    <form method="POST" action="send_message.php" style="margin-top:0.85rem;">
                        <input type="hidden" name="send_message" value="1">
                        <input type="hidden" name="recipient_id" value="<?= (int)$app['user_id'] ?>">
                        <input type="hidden" name="return_to" value="dashboard_employer.php">
                        <div class="field-group">
                            <label>Subject</label>
                            <input type="text" name="subject" placeholder="Follow up on your application" required>
                        </div>
                        <div class="field-group full">
                            <label>Message</label>
                            <textarea name="body" placeholder="Write your message here..." required></textarea>
                        </div>
                        <button type="submit" class="post-btn">Send Message</button>
                    </form>
                </details>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── TAB: MESSAGES ── -->
    <div class="tab-panel" id="tab-messages">
        <div class="page-header">
            <h2>Messages</h2>
            <p>Messaging history with students and interview requests.</p>
        </div>
        <?php if (empty($conversation_threads)): ?>
            <div class="empty-state">
                <div class="icon">💬</div>
                <p>No messages yet. Start a conversation from Candidate Search or Applicants.</p>
            </div>
        <?php else: ?>
            <?php foreach ($conversation_threads as $thread): ?>
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                        <div class="card-title">Conversation with <?= htmlspecialchars($thread['partner_name']) ?></div>
                        <div class="card-meta">
                            <span><?= count($thread['messages']) ?> messages</span>
                            <span>Last: <?= date('M j, Y g:i A', strtotime($thread['messages'][0]['created_at'])) ?></span>
                        </div>
                    </div>
                    <span class="badge badge-blue">Thread</span>
                </div>
                <div style="margin-top:1rem;">
                    <?php foreach (array_reverse($thread['messages']) as $message): ?>
                    <div style="padding:0.85rem 0; border-bottom:1px solid var(--border);">
                        <div style="display:flex;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                            <strong style="color:var(--text);"><?= $message['sender_id'] === $user_id ? 'You' : htmlspecialchars($thread['partner_name']) ?></strong>
                            <span style="color:var(--muted);font-size:0.82rem;"><?= date('M j, Y g:i A', strtotime($message['created_at'])) ?></span>
                        </div>
                        <p style="margin:0.5rem 0 0;color:var(--text);line-height:1.6;"><?= nl2br(htmlspecialchars($message['body'])) ?></p>
                        <?php if (!empty($message['scheduled_for'])): ?>
                        <p style="margin:0.5rem 0 0;color:var(--accent);font-size:0.85rem;"><strong>Interview scheduled:</strong> <?= date('M j, Y g:i A', strtotime($message['scheduled_for'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <details style="margin-top:1rem;border:1px solid var(--border);border-radius:12px;padding:0.75rem;background:var(--surface);">
                    <summary style="font-weight:600;cursor:pointer;color:var(--text);">Reply to <?= htmlspecialchars($thread['partner_name']) ?></summary>
                    <form method="POST" action="send_message.php" style="margin-top:0.85rem;">
                        <input type="hidden" name="send_message" value="1">
                        <input type="hidden" name="recipient_id" value="<?= (int)$thread['partner_id'] ?>">
                        <input type="hidden" name="return_to" value="dashboard_employer.php">
                        <div class="field-group">
                            <label>Subject</label>
                            <input type="text" name="subject" value="Re: <?= htmlspecialchars($thread['messages'][0]['subject'] ?: 'Message') ?>" required>
                        </div>
                        <div class="field-group full">
                            <label>Message</label>
                            <textarea name="body" placeholder="Write your reply..." required></textarea>
                        </div>
                        <button type="submit" class="post-btn">Send Reply</button>
                    </form>
                </details>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── TAB: JOB RATINGS ── -->
    <div class="tab-panel" id="tab-ratings">
        <div class="page-header">
            <h2>Job Ratings</h2>
            <p>See how students and employees rate your posted jobs.</p>
        </div>
        <?php
        $rated_jobs = array_filter($my_jobs, fn($j) => $j['total_ratings'] > 0);
        $unrated    = array_filter($my_jobs, fn($j) => $j['total_ratings'] == 0);
        ?>
        <?php if (empty($my_jobs)): ?>
            <div class="empty-state">
                <div class="icon">⭐</div>
                <p>Post jobs first to see their ratings here.</p>
            </div>
        <?php else: ?>
            <?php if (!empty($rated_jobs)): ?>
                <h3 style="font-size:0.85rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:0.75rem">Rated Jobs</h3>
                <?php foreach ($rated_jobs as $j): ?>
                <div class="card">
                    <div class="card-title"><?= htmlspecialchars($j['title']) ?> <span style="color:var(--muted);font-weight:400">@ <?= htmlspecialchars($j['company']) ?></span></div>
                    <div class="stats-row" style="margin-top:0.5rem">
                        <div class="stat-chip"><strong>Avg</strong> <?= $j['avg_rating'] ?>/5 <span class="stars-gold"><?= stars((int)round($j['avg_rating'])) ?></span></div>
                        <div class="stat-chip"><strong>Highest</strong> <?= $j['max_rating'] ?>/5</div>
                        <div class="stat-chip"><strong>Lowest</strong> <?= $j['min_rating'] ?>/5</div>
                        <div class="stat-chip"><strong><?= $j['total_ratings'] ?></strong> total rating<?= $j['total_ratings']!=1?'s':'' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($unrated)): ?>
                <h3 style="font-size:0.85rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin:1.25rem 0 0.75rem">Not Yet Rated</h3>
                <?php foreach ($unrated as $j): ?>
                <div class="card" style="opacity:0.6">
                    <div class="card-title"><?= htmlspecialchars($j['title']) ?> <span style="color:var(--muted);font-weight:400">@ <?= htmlspecialchars($j['company']) ?></span></div>
                    <div class="stat-chip" style="margin-top:0.5rem;display:inline-block">No ratings yet</div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
