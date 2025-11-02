<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $image_path = 'default-profile.png';

    // Validate inputs
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!in_array($role, ['student', 'staff', 'admin'])) {
        $error = "Invalid role selected.";
    } else {
        // Handle optional profile image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $file = $_FILES['image'];
            $allowed_types = ['image/jpeg', 'image/png'];
            // Basic MIME check
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : $file['type'];
            if ($finfo) finfo_close($finfo);
            $max_size = 2 * 1024 * 1024; // 2MB
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($mime, $allowed_types) || !in_array($ext, ['jpg','jpeg','png'])) {
                $error = "Invalid image type. Upload JPG or PNG.";
            } elseif ($file['size'] > $max_size) {
                $error = "Image too large. Max 2MB.";
            } else {
                $new_name = uniqid('avatar_', true) . '.' . $ext;
                $dest = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $new_name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $image_path = $new_name; // stored relative to assets/
                } else {
                    $error = "Failed to save profile image.";
                }
            }
        }

        // Check for duplicate username or email
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $error = "Username or email already exists.";
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $email, $hashed_password, $role, $image_path);
            if ($stmt->execute()) {
                $success = "Registration successful! Please log in.";
                header("Location: login.php?success=" . urlencode($success));
                exit();
            } else {
                $error = "Registration failed: " . $conn->error;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Bugema Students Complaint Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .landing-card { max-width: 560px; }
    </style>
    </head>
<body class="dark-mode d-flex align-items-center justify-content-center">
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="w-100" style="max-width: 560px;">
            <div class="card landing-card p-3">
                <div class="card-body">
                    <h1 class="landing-logo mb-1 text-center">Bugema Students Complaint Center</h1>
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <a href="index.php?guest=1" class="btn btn-outline-secondary">Home</a>
                        <a href="login.php" class="btn btn-outline-primary">Login</a>
                    </div>
                    <h2 class="h5 mb-2 text-center">Register</h2>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-2">
                            <label for="reg-username" class="form-label">Username</label>
                            <input type="text" id="reg-username" name="username" class="form-control" placeholder="e.g. Joan" required>
                            <div class="form-text">Your display name in the portal.</div>
                        </div>
                        <div class="mb-2">
                            <label for="reg-email" class="form-label">Email</label>
                            <input type="email" id="reg-email" name="email" class="form-control" placeholder="e.g. student@bugema.ac.ug" required>
                            <div class="form-text">We'll send important updates to this address.</div>
                        </div>
                        <div class="mb-2">
                            <label for="reg-password" class="form-label">Password</label>
                            <input type="password" id="reg-password" name="password" class="form-control" placeholder="At least 6 characters" required>
                            <div class="form-text">Use a strong password you don't use elsewhere.</div>
                        </div>
                        <div class="mb-2">
                            <label for="reg-image" class="form-label">Profile Photo (optional)</label>
                            <input type="file" id="reg-image" name="image" class="form-control" accept="image/jpeg,image/png">
                            <div class="form-text">JPG or PNG, up to 2MB.</div>
                        </div>
                        <div class="mb-2">
                            <label for="reg-role" class="form-label">Role</label>
                            <select id="reg-role" name="role" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                            <div class="form-text">Choose how you'll use the system.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>