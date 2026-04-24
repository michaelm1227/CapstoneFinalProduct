-- Test data for mcglen97 — Careerify
USE mcglen97;

-- Test student (password: testpassword)
INSERT INTO Users (username, email, password_hash, role, location, visa_status)
VALUES ('testuser', 'test@example.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'student', 'New Jersey', 'F1');

-- Test employer (password: testpassword)
INSERT INTO Users (username, email, password_hash, role, location, visa_status)
VALUES ('testemployer', 'employer@example.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'employer', 'New York', NULL);

-- Empty profiles for both users
INSERT INTO UserProfiles (user_id, skills, interests)
VALUES (1, 'PHP, JavaScript, SQL', 'Web Development, Data Science');
INSERT INTO UserProfiles (user_id) VALUES (2);

-- Jobs (posted by employer user_id=2)
INSERT INTO Jobs (title, company, location, description, requirements, salary_range, work_type, posted_by)
VALUES ('Software Developer', 'Tech Company', 'Glassboro, NJ',
        'Build and maintain web applications for our platform.',
        'PHP, JavaScript, 1+ years experience',
        '$50,000 – $70,000', 'hybrid', 2);

INSERT INTO Jobs (title, company, location, description, requirements, salary_range, work_type, posted_by)
VALUES ('Web Developer Intern', 'Startup Inc', 'Remote',
        'Learn web development in a fast-paced startup environment.',
        'HTML, CSS, JavaScript basics',
        '$20 – $25/hour', 'remote', 2);

INSERT INTO Jobs (title, company, location, description, requirements, salary_range, work_type, posted_by)
VALUES ('Data Analyst', 'Analytics Corp', 'Philadelphia, PA',
        'Analyze business data and produce actionable reports.',
        'SQL, Excel, Python preferred',
        '$55,000 – $75,000', 'onsite', 2);

-- Put job 1 in testuser's past jobs so they can rate it
INSERT INTO PastJobs (user_id, job_id) VALUES (1, 1);

-- Save job 2 for testuser
INSERT INTO SavedJobs (user_id, job_id) VALUES (1, 2);

-- Sample job match scores for testuser
INSERT INTO JobMatches (user_id, job_id, match_score) VALUES (1, 1, 87.50);
INSERT INTO JobMatches (user_id, job_id, match_score) VALUES (1, 2, 72.00);
INSERT INTO JobMatches (user_id, job_id, match_score) VALUES (1, 3, 61.25);
