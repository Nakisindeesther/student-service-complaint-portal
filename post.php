<?php
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$post_id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT c.*, u.username, u.image FROM complaints c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="dark-mode">
    <div class="container mt-5">
        <?php if ($post): ?>
            <div class="card">
                <div class="card-body">
                    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                    <p><?php echo htmlspecialchars($post['content']); ?></p>
                    <p>Posted by: <?php echo htmlspecialchars($post['username']); ?></p>
                </div>
            </div>
        <?php else: ?>
            <p>Post not found.</p>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
    </div>
</body>
</html>