USE mcglen97;

-- Ensure the Users table has a role column
ALTER TABLE Users ADD COLUMN IF NOT EXISTS role VARCHAR(50) NOT NULL DEFAULT 'student';

-- If you have existing users, update their role labels to new conventions:
-- Old 'student' (international) -> 'international'
-- Old 'employee' -> 'student'
-- Old 'employer' stays 'employer'
UPDATE Users SET role = 'international' WHERE role = 'student';
UPDATE Users SET role = 'student' WHERE role = 'employee';

-- Verify
SELECT user_id, username, role FROM Users;
