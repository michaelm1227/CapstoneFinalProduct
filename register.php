<?php
session_start();
require_once 'db_connect.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role             = $_POST['role'] ?? '';
    $visa_status_input = $_POST['visa_status'] ?? [];
    $visa_status = '';

    if (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (empty($role)) {
        $error = 'Please select a role.';
    } elseif ($role === 'student') {
        if (!is_array($visa_status_input) || count($visa_status_input) === 0) {
            $error = 'Please select at least one visa status or choose Citizen.';
        } else {
            $cleaned = array_map('trim', $visa_status_input);
            $cleaned = array_filter($cleaned, function($value) { return $value !== ''; });
            $visa_status = implode(', ', $cleaned);
            if ($visa_status === '') {
                $error = 'Please select at least one visa status or choose Citizen.';
            }
        }
    } else {
        $visa_status = '';
    }

    if (empty($error)) {
        // Check username uniqueness
        $check = $connection->prepare("SELECT user_id FROM Users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'That username is already taken.';
        }
        $check->close();

        // Check email uniqueness
        if (empty($error)) {
            $check2 = $connection->prepare("SELECT user_id FROM Users WHERE email = ?");
            $check2->bind_param("s", $email);
            $check2->execute();
            if ($check2->get_result()->num_rows > 0) {
                $error = 'An account with that email already exists.';
            }
            $check2->close();
        }

        if (empty($error)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = $connection->prepare("INSERT INTO Users (username, email, password_hash, role, visa_status) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("sssss", $username, $email, $hashed, $role, $visa_status);

            if ($ins->execute()) {
                $new_user_id = $connection->insert_id;

                // Create empty profile row
                $prof = $connection->prepare("INSERT INTO UserProfiles (user_id) VALUES (?)");
                $prof->bind_param("i", $new_user_id);
                $prof->execute();
                $prof->close();

                // Handle resume upload for students and international students
                if (in_array($role, ['student', 'international']) && !empty($_FILES['resume']['name'])) {
                    $upload_dir = 'uploads/resumes/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext     = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
                    $allowed = ['pdf', 'doc', 'docx'];
                    if (in_array($ext, $allowed)) {
                        $filename = 'resume_' . $new_user_id . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_dir . $filename)) {
                            $res_upd = $connection->prepare("UPDATE UserProfiles SET resume_link=? WHERE user_id=?");
                            $res_path = $upload_dir . $filename;
                            $res_upd->bind_param("si", $res_path, $new_user_id);
                            $res_upd->execute();
                            $res_upd->close();
                        }
                    }
                }

                $success = 'Account created! Redirecting to login...';
                header('Refresh: 2; url=index.php');
            } else {
                $error = 'Registration failed. Please try again.';
            }
            $ins->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – Careerify</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <div class="form-box active" id="register-form">
        <h2>Create Account</h2>
        <p style="color:#555;font-size:0.95rem;margin-bottom:1rem;">Create a STEM student/alumni profile or employer account to connect talent with targeted career opportunities.</p>
        <?php if (!empty($error)): ?>

            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="POST" action="register.php" enctype="multipart/form-data">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <select id="role" name="role" onchange="toggleStudentFields()" required>
                <option value="">-- Select Role --</option>
                <option value="student">Student / Alumni</option>
                <option value="employer">Employer</option>
            </select>
            <div id="student-fields" style="display:none">
                <label style="font-size:0.85rem;color:#555;margin-bottom:6px;display:block">Visa status (select all that apply)</label>
                <select id="visa_status" name="visa_status[]" multiple size="6" style="width:100%; background:#fff; color:#000; border:1px solid #ccc; border-radius:8px; padding:0.65rem; margin-bottom:0.75rem;">
                    <option value="F1">F1</option>
                    <option value="J1">J1</option>
                    <option value="H1B">H-1B</option>
                    <option value="OPT">OPT</option>
                    <option value="CPT">CPT</option>
                    <option value="Green Card">Green Card</option>
                    <option value="Citizen">Citizen / None of the above</option>
                </select>
                <small style="display:block;color:#555;font-size:0.82rem;margin-bottom:10px;">Hold Ctrl (Windows) or Command (Mac) to select multiple visa statuses.</small>
                <label style="font-size:0.85rem;color:#555;margin-bottom:6px;display:block">Upload Resume (.pdf, .doc, .docx)</label>
                <input type="file" name="resume" accept=".pdf,.doc,.docx">
                <small style="display:block;color:#555;font-size:0.82rem;margin-top:8px;">Resume uploads are stored on disk and linked in your profile; Careerify does not store PDF content in the database.</small>
            </div>
            <button type="submit">Register</button>
            <p>Already have an account? <a href="index.php">Login here</a></p>
        </form>
    </div>
</div>
<script>
function toggleStudentFields() {
    const role = document.getElementById('role').value;
    const studentFields = document.getElementById('student-fields');
    if (studentFields) studentFields.style.display = (role === 'student') ? 'block' : 'none';
}
</script>
</body>
</html>
