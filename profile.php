<?php
include 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $image_name = $_SESSION['image'] ?? 'default-profile.png';

    if ($username === '' || $email === '') {
        $error = 'Username and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        // image upload optional
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $file = $_FILES['image'];
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : $file['type'];
            if ($finfo) finfo_close($finfo);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($mime, ['image/jpeg','image/png']) || !in_array($ext, ['jpg','jpeg','png'])) {
                $error = 'Invalid image type. Upload JPG or PNG.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'Image too large. Max 2MB.';
            } else {
                $new_name = uniqid('avatar_', true) . '.' . $ext;
                $dest = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $new_name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $image_name = $new_name;
                } else {
                    $error = 'Failed to save profile image.';
                }
            }
        }

        if ($error === '') {
            // ensure email not taken by another user
            $stmt = $conn->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ?');
            $stmt->bind_param('ssi', $username, $email, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $error = 'Username or email already in use.';
            } else {
                $stmt->close();
                if ($new_password !== '') {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET username = ?, email = ?, password = ?, image = ? WHERE id = ?');
                    $stmt->bind_param('ssssi', $username, $email, $hashed, $image_name, $user_id);
                } else {
                    $stmt = $conn->prepare('UPDATE users SET username = ?, email = ?, image = ? WHERE id = ?');
                    $stmt->bind_param('sssi', $username, $email, $image_name, $user_id);
                }
                if ($stmt->execute()) {
                    $_SESSION['username'] = $username;
                    $_SESSION['image'] = $image_name;
                    $success = 'Profile updated successfully.';
                } else {
                    $error = 'Failed to update profile: ' . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

// Load current user
$stmt = $conn->prepare('SELECT username, email, image FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dark-mode">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h1 class="h4 mb-3">Edit Profile</h1>
                        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                        <div class="text-center mb-3">
                            <img src="assets/<?php echo htmlspecialchars($user['image'] ?? 'default-profile.png'); ?>" class="rounded-circle" style="width:96px;height:96px;object-fit:cover;" alt="Avatar">
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password (optional)</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Profile Photo (optional)</label>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


