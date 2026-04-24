-- ============================================
-- Full schema for mcglen97 — VisaJob Connect
-- Run this to reset and rebuild all tables
-- ============================================
USE mcglen97;

DROP TABLE IF EXISTS JobMatches;
DROP TABLE IF EXISTS JobRatings;
DROP TABLE IF EXISTS PastJobs;
DROP TABLE IF EXISTS SavedJobs;
DROP TABLE IF EXISTS UserProfiles;
DROP TABLE IF EXISTS Jobs;
DROP TABLE IF EXISTS Users;

-- Users
CREATE TABLE Users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'student',  -- student | employee | employer
    location      VARCHAR(100),
    visa_status   VARCHAR(50),
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- UserProfiles
CREATE TABLE UserProfiles (
    profile_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    skills         TEXT,
    interests      TEXT,
    certifications TEXT,
    experience_years INT DEFAULT NULL,
    resume_link    VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Jobs (posted_by links to the employer's user_id)
CREATE TABLE Jobs (
    job_id       INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(100) NOT NULL,
    company      VARCHAR(100) NOT NULL,
    location     VARCHAR(100),
    description  TEXT,
    requirements TEXT,
    tags         VARCHAR(255),              -- comma-separated tags for matching
    salary_range VARCHAR(100),
    work_type    VARCHAR(20),               -- remote | hybrid | onsite
    posted_by    INT,                       -- employer user_id (nullable for seeded data)
    expires_at   DATE,
    posted_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(title),
    INDEX(company),
    INDEX(location),
    FOREIGN KEY (posted_by) REFERENCES Users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- SavedJobs
CREATE TABLE SavedJobs (
    saved_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id  INT NOT NULL,
    job_id   INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)  REFERENCES Jobs(job_id)   ON DELETE CASCADE,
    UNIQUE(user_id, job_id)
) ENGINE=InnoDB;

-- PastJobs
CREATE TABLE PastJobs (
    past_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id  INT NOT NULL,
    job_id   INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)  REFERENCES Jobs(job_id)   ON DELETE CASCADE,
    UNIQUE(user_id, job_id)
) ENGINE=InnoDB;

-- JobRatings (one per user per job, no updates)
CREATE TABLE JobRatings (
    rating_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    job_id     INT NOT NULL,
    rating     TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    rated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)  REFERENCES Jobs(job_id)   ON DELETE CASCADE,
    UNIQUE(user_id, job_id)
) ENGINE=InnoDB;

-- Job Applications
CREATE TABLE JobApplications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    job_id         INT NOT NULL,
    cover_letter   TEXT,
    applied_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)  REFERENCES Jobs(job_id)   ON DELETE CASCADE,
    UNIQUE(user_id, job_id)
) ENGINE=InnoDB;

-- Messages between students and employers
CREATE TABLE Messages (
    message_id    INT AUTO_INCREMENT PRIMARY KEY,
    sender_id     INT NOT NULL,
    recipient_id  INT NOT NULL,
    subject       VARCHAR(150) DEFAULT NULL,
    body          TEXT,
    scheduled_for DATETIME DEFAULT NULL,
    job_id        INT DEFAULT NULL,
    type          VARCHAR(20) DEFAULT 'message',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- JobMatches
CREATE TABLE JobMatches (
    match_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    job_id        INT NOT NULL,
    match_score   DECIMAL(5,2) CHECK (match_score >= 0 AND match_score <= 100),
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)  REFERENCES Jobs(job_id)   ON DELETE CASCADE,
    UNIQUE(user_id, job_id)
) ENGINE=InnoDB;
