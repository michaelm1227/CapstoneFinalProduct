-- ============================================================================
-- CAREERIFY DATABASE SCHEMA
-- Complete setup with all tables and PDF BLOB storage
-- ============================================================================

-- Users Table
CREATE TABLE IF NOT EXISTS Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    location VARCHAR(100),
    state VARCHAR(2),
    zip_code VARCHAR(5),
    visa_status VARCHAR(50),
    role VARCHAR(20) NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User Profiles Table (with resume_pdf BLOB for direct PDF storage)
CREATE TABLE IF NOT EXISTS UserProfiles (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jobs Table (with expires_at for job expiration)
CREATE TABLE IF NOT EXISTS Jobs (
    job_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    company VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    description TEXT NOT NULL,
    requirements TEXT,
    tags VARCHAR(255),
    salary_range VARCHAR(100),
    work_type VARCHAR(20),
    posted_by INT NOT NULL,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES Users(user_id) ON DELETE CASCADE,
    INDEX idx_posted_by (posted_by),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Job Applications Table
CREATE TABLE IF NOT EXISTS JobApplications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    cover_letter TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_job (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_job_id (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Saved Jobs Table
CREATE TABLE IF NOT EXISTS SavedJobs (
    saved_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    UNIQUE (user_id, job_id),
    INDEX idx_user_id (user_id),
    INDEX idx_job_id (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Past Jobs Table (for students to track completed jobs)
CREATE TABLE IF NOT EXISTS PastJobs (
    past_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    UNIQUE (user_id, job_id),
    INDEX idx_user_id (user_id),
    INDEX idx_job_id (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Job Ratings Table
CREATE TABLE IF NOT EXISTS JobRatings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    rated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    UNIQUE (user_id, job_id),
    INDEX idx_user_id (user_id),
    INDEX idx_job_id (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Job Matches Table (for skill matching scores)
CREATE TABLE IF NOT EXISTS JobMatches (
    match_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_id INT NOT NULL,
    match_score DECIMAL(5,2) CHECK (match_score >= 0 AND match_score <= 100),
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    UNIQUE (user_id, job_id),
    INDEX idx_user_id (user_id),
    INDEX idx_job_id (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Messages Table (for employer-student communication)
CREATE TABLE IF NOT EXISTS Messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(150),
    body TEXT,
    scheduled_for DATETIME DEFAULT NULL,
    job_id INT DEFAULT NULL,
    type VARCHAR(20) DEFAULT 'message',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE SET NULL,
    INDEX idx_sender_id (sender_id),
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PDF BLOB STORAGE
-- ============================================================================
-- NOTE: PDFs are stored directly in MySQL as BLOB data
-- resume_pdf column: LONGBLOB (up to 4GB)
-- resume_filename: Original filename (e.g., "john_resume.pdf")
-- resume_mimetype: MIME type (usually "application/pdf")
-- resume_uploaded_at: When the PDF was uploaded
--
-- When uploading PDFs:
--   1. Validate file is PDF only
--   2. Check file size <= 16MB (LONGBLOB limit in practice)
--   3. Read file as binary data
--   4. Store in resume_pdf column
--   5. Store filename and mimetype
--
-- To serve PDFs to users, create a PHP script that:
--   1. Fetches BLOB data from resume_pdf
--   2. Sets proper headers (Content-Type: application/pdf)
--   3. Outputs the binary data
-- ============================================================================

-- Example PHP code to upload PDF:
-- $pdf_data = file_get_contents($_FILES['resume']['tmp_name']);
-- $stmt = $conn->prepare("UPDATE UserProfiles SET resume_pdf=?, resume_filename=?, resume_mimetype=?, resume_uploaded_at=NOW() WHERE user_id=?");
-- $stmt->bind_param("bsss", $pdf_data, $filename, $mimetype, $user_id);
-- $stmt->send_long_data(0, $pdf_data); // For large BLOBs
-- $stmt->execute();

-- Example PHP code to serve PDF:
-- $stmt = $conn->prepare("SELECT resume_pdf, resume_filename, resume_mimetype FROM UserProfiles WHERE user_id=?");
-- $stmt->bind_param("i", $user_id);
-- $stmt->execute();
-- $result = $stmt->get_result()->fetch_assoc();
-- header('Content-Type: ' . $result['resume_mimetype']);
-- header('Content-Disposition: inline; filename="' . $result['resume_filename'] . '"');
-- echo $result['resume_pdf'];
