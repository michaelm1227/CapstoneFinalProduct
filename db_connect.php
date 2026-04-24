<?php
$servername   = 'elvisdb';
$username     = 'mcglen97';
$password     = '11CAPSt0ne!!';
$databasename = 'mcglen97';

$connection = mysqli_connect($servername, $username, $password, $databasename);

if (!$connection) {
    die('Connection unsuccessful: ' . mysqli_connect_error());
}

// Ensure the Users table has a role column for modern dashboard logic.
$role_check = $connection->query("SHOW COLUMNS FROM Users LIKE 'role'");
if ($role_check && $role_check->num_rows === 0) {
    $result = $connection->query("ALTER TABLE Users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'student'");
    if (!$result) {
        error_log("Failed to add role column: " . $connection->error);
    }
}

// Ensure Users has state and zip_code columns for location.
$state_check = $connection->query("SHOW COLUMNS FROM Users LIKE 'state'");
if ($state_check && $state_check->num_rows === 0) {
    $result = $connection->query("ALTER TABLE Users ADD COLUMN state VARCHAR(2)");
    if (!$result) {
        error_log("Failed to add state column: " . $connection->error);
    }
}
$zip_check = $connection->query("SHOW COLUMNS FROM Users LIKE 'zip_code'");
if ($zip_check && $zip_check->num_rows === 0) {
    $result = $connection->query("ALTER TABLE Users ADD COLUMN zip_code VARCHAR(5)");
    if (!$result) {
        error_log("Failed to add zip_code column: " . $connection->error);
    }
}

// Ensure Jobs has a tags column for matching and filtering.
$tags_check = $connection->query("SHOW COLUMNS FROM Jobs LIKE 'tags'");
if ($tags_check && $tags_check->num_rows === 0) {
    $result = $connection->query("ALTER TABLE Jobs ADD COLUMN tags VARCHAR(255) DEFAULT NULL");
    if (!$result) {
        error_log("Failed to add tags column: " . $connection->error);
    }
}

// Ensure Jobs has an expires_at column for job expiration tracking.
$expires_check = $connection->query("SHOW COLUMNS FROM Jobs LIKE 'expires_at'");
if ($expires_check && $expires_check->num_rows === 0) {
    $result = $connection->query("ALTER TABLE Jobs ADD COLUMN expires_at DATE DEFAULT NULL");
    if (!$result) {
        error_log("Failed to add expires_at column: " . $connection->error);
    }
}

// Ensure Jobs has state and zip_code columns for location.
$job_state_check = $connection->query("SHOW COLUMNS FROM Jobs LIKE 'state'");
if ($job_state_check && $job_state_check->num_rows === 0) {
    $result = $connection->query("ALTER TABLE Jobs ADD COLUMN state VARCHAR(2)");
    if (!$result) {
        error_log("Failed to add state column to Jobs: " . $connection->error);
    }
}
$job_zip_check = $connection->query("SHOW COLUMNS FROM Jobs LIKE 'zip_code'");
if ($job_zip_check && $job_zip_check->num_rows === 0) {
    $result = $connection->query("ALTER TABLE Jobs ADD COLUMN zip_code VARCHAR(5)");
    if (!$result) {
        error_log("Failed to add zip_code column to Jobs: " . $connection->error);
    }
}

// Ensure the UserProfiles table exists.
$userprofiles_check = $connection->query("SHOW TABLES LIKE 'UserProfiles'");
if ($userprofiles_check && $userprofiles_check->num_rows === 0) {
    $connection->query("CREATE TABLE UserProfiles (
        profile_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        skills TEXT,
        interests TEXT,
        certifications TEXT,
        experience_years INT DEFAULT NULL,
        resume_pdf LONGBLOB,
        resume_filename VARCHAR(255),
        resume_mimetype VARCHAR(100) DEFAULT 'application/pdf',
        resume_uploaded_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} else {
    // If UserProfiles exists, ensure the expected columns are present.
    $exp_check = $connection->query("SHOW COLUMNS FROM UserProfiles LIKE 'experience_years'");
    if ($exp_check && $exp_check->num_rows === 0) {
        $result = $connection->query("ALTER TABLE UserProfiles ADD COLUMN experience_years INT DEFAULT NULL");
        if (!$result) {
            error_log("Failed to add experience_years column: " . $connection->error);
        }
    }
    $resume_check = $connection->query("SHOW COLUMNS FROM UserProfiles LIKE 'resume_pdf'");
    if ($resume_check && $resume_check->num_rows === 0) {
        $result1 = $connection->query("ALTER TABLE UserProfiles ADD COLUMN resume_pdf LONGBLOB");
        $result2 = $connection->query("ALTER TABLE UserProfiles ADD COLUMN resume_filename VARCHAR(255)");
        $result3 = $connection->query("ALTER TABLE UserProfiles ADD COLUMN resume_mimetype VARCHAR(100) DEFAULT 'application/pdf'");
        $result4 = $connection->query("ALTER TABLE UserProfiles ADD COLUMN resume_uploaded_at TIMESTAMP NULL");
        if (!$result1 || !$result2 || !$result3 || !$result4) {
            error_log("Failed to add resume columns: " . $connection->error);
        }
    }
}

// Ensure the messaging table exists for employer/student communication.
$messages_check = $connection->query("SHOW TABLES LIKE 'Messages'");
if ($messages_check && $messages_check->num_rows === 0) {
    $connection->query("CREATE TABLE Messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        subject VARCHAR(150) DEFAULT NULL,
        body TEXT,
        scheduled_for DATETIME DEFAULT NULL,
        job_id INT DEFAULT NULL,
        type VARCHAR(20) DEFAULT 'message',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES Users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Ensure supporting job tables exist for dashboard queries.
$connection->query("CREATE TABLE IF NOT EXISTS SavedJobs (
    saved_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    UNIQUE (user_id, job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$connection->query("CREATE TABLE IF NOT EXISTS PastJobs (
    past_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    UNIQUE (user_id, job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$connection->query("CREATE TABLE IF NOT EXISTS JobRatings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    rated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    UNIQUE (user_id, job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$connection->query("CREATE TABLE IF NOT EXISTS JobMatches (
    match_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    match_score DECIMAL(5,2) CHECK (match_score >= 0 AND match_score <= 100),
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    UNIQUE (user_id, job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

