<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role, image FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['image'] = $user['image'] ?? 'default-profile.png';
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid credentials.";
        }
        $stmt->close();
    }
}

// Lightweight AJAX user lookup for avatar preview
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lookup']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    $lookup = trim($_GET['lookup']);
    header('Content-Type: application/json');
    if ($lookup === '') { echo json_encode(['found' => false]); exit(); }
    $stmt = $conn->prepare("SELECT image FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $lookup, $lookup);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        echo json_encode(['found' => true, 'image' => $row['image'] ?: 'default-profile.png']);
    } else {
        echo json_encode(['found' => false]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bugema Students Complaint Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .landing-card { max-width: 560px; }
    </style>
    </head>
<body class="dark-mode">
    <div class="container-fluid auth-split">
        <div class="row min-vh-100 g-0">
            <!-- Left: Form -->
            <div class="col-12 col-md-5 d-flex align-items-center justify-content-center auth-left p-4">
                <div class="w-100" style="max-width: 420px;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <a href="index.php?guest=1" class="btn btn-sm btn-outline-secondary">Home</a>
                        <a href="register.php" class="btn btn-sm btn-outline-primary">Register</a>
                    </div>
                    <h1 class="h4 mb-1">Welcome to Bugema University Complaint Center</h1>
                    <p class="text-muted mb-3">Sign into your account</p>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_user'): ?>
                        <div class="alert alert-danger">Invalid user session. Please log in again.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                    <?php endif; ?>
                    <div class="text-center mb-3" id="login-avatar" style="display:none;">
                        <img src="assets/right.png" alt="Avatar" class="rounded-circle shadow-sm" style="width:72px;height:72px;object-fit:cover;">
                    </div>
                    <form method="POST" class="auth-form">
                        <div class="mb-2">
                            <label for="login-email" class="form-label">Email</label>
                            <input type="email" id="login-email" name="email" class="form-control form-control-sm" placeholder="Phone or Email address" required>
                        </div>
                        <div class="mb-2">
                            <label for="login-password" class="form-label">Password</label>
                            <input type="password" id="login-password" name="password" class="form-control form-control-sm" placeholder="Password" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div></div>
                            <a href="#" class="small">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Log In</button>
                    </form>
                </div>
            </div>
            <!-- Right: Illustration -->
            <div class="col-12 col-md-7 auth-right">
                <div class="auth-art"></div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>