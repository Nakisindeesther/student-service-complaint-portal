<?php
include 'config.php';

// Redirect if not logged in or invalid user_id
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verify user exists in database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    session_destroy();
    header("Location: login.php?error=invalid_user");
    exit();
}
$stmt->close();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Post new complaint (students only)
    if ($_POST['action'] == 'post' && $_SESSION['role'] == 'student') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $department = $_POST['department'] ?? NULL;
        $media_path = NULL;

        if (empty($title) || empty($content)) {
            $response = ['success' => false, 'message' => 'Title and content are required.'];
        } else {
            // Handle file upload
            if (isset($_FILES['media']) && $_FILES['media']['error'] === 0) {
                $file = $_FILES['media'];
                $allowed_types = ['image/jpeg', 'image/png', 'video/mp4'];
                $max_size = $file['type'] === 'video/mp4' ? 50 * 1024 * 1024 : 5 * 1024 * 1024;
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_name = uniqid() . '.' . $file_ext;
                $upload_path = 'uploads/' . $new_name;

                if (!in_array($file['type'], $allowed_types)) {
                    $response = ['success' => false, 'message' => 'Invalid file type. Use JPG, PNG, or MP4.'];
                } elseif ($file['size'] > $max_size) {
                    $response = ['success' => false, 'message' => 'File too large. Images ≤5MB, Videos ≤50MB.'];
                } elseif (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $response = ['success' => false, 'message' => 'File upload failed. Check uploads/ folder permissions.'];
                } else {
                    $media_path = $upload_path;
                }
            }

            if (!isset($response)) {
                $stmt = $conn->prepare("INSERT INTO complaints (user_id, title, content, department, media_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $title, $content, $department, $media_path);
                if ($stmt->execute()) { // Line 47
                    $new_post_id = $stmt->insert_id;
                    $stmt->close();

                    $stmt = $conn->prepare("SELECT username, image FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $response = [
                        'success' => true,
                        'message' => 'Complaint posted successfully!',
                        'post' => [
                            'id' => $new_post_id,
                            'title' => htmlspecialchars($title),
                            'content' => htmlspecialchars($content),
                            'username' => htmlspecialchars($user['username']),
                            'image' => $user['image'] ?? 'default-profile.png',
                            'department' => htmlspecialchars($department ?? ''),
                            'media_path' => $media_path ? htmlspecialchars($media_path) : ''
                        ]
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Database error: ' . $conn->error];
                }
            }
        }
    }

    // Update complaint status (staff/admin)
    if ($_POST['action'] == 'update_status' && in_array($_SESSION['role'], ['staff', 'admin'])) {
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $new_status = $_POST['status'] ?? '';
        $allowed_statuses = ['open', 'in_progress', 'resolved'];
        if (!$post_id || !in_array($new_status, $allowed_statuses)) {
            $response = ['success' => false, 'message' => 'Invalid status update.'];
        } else {
            $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $post_id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'status' => $new_status, 'post_id' => $post_id];
            } else {
                $response = ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->close();
        }
    }

    // Like a post
    if ($_POST['action'] == 'like') {
        $post_id = $_POST['post_id'] ?? 0;
        $stmt = $conn->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
        $stmt->bind_param("ii", $user_id, $post_id);
        $stmt->execute();
        $existing_like = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing_like) {
            $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
            $stmt->bind_param("ii", $user_id, $post_id);
            $stmt->execute();
            $stmt->close();
            $response = ['success' => true, 'liked' => false];
        } else {
            $stmt = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $post_id);
            $stmt->execute();
            $stmt->close();
            $response = ['success' => true, 'liked' => true];
        }
    }

    // Repost a complaint
    if ($_POST['action'] == 'repost' && $_SESSION['role'] == 'student') {
        $post_id = $_POST['post_id'] ?? 0;
        $stmt = $conn->prepare("SELECT * FROM complaints WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $original_post = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($original_post) {
            $stmt = $conn->prepare("INSERT INTO complaints (user_id, title, content, department, media_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $original_post['title'], $original_post['content'], $original_post['department'], $original_post['media_path']);
            if ($stmt->execute()) {
                $new_post_id = $stmt->insert_id;
                $stmt->close();

                $stmt = $conn->prepare("SELECT username, image FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $response = [
                    'success' => true,
                    'message' => 'Repost successful!',
                    'post' => [
                        'id' => $new_post_id,
                        'title' => htmlspecialchars($original_post['title']),
                        'content' => htmlspecialchars($original_post['content']),
                        'username' => htmlspecialchars($user['username']),
                        'image' => $user['image'] ?? 'default-profile.png',
                        'department' => htmlspecialchars($original_post['department'] ?? ''),
                        'media_path' => $original_post['media_path'] ? htmlspecialchars($original_post['media_path']) : ''
                    ]
                ];
            } else {
                $response = ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
        } else {
            $response = ['success' => false, 'message' => 'Original post not found.'];
        }
    }

    // Post a comment
    if ($_POST['action'] == 'comment') {
        $post_id = $_POST['post_id'] ?? 0;
        $content = $_POST['content'] ?? '';
        if (empty($content)) {
            $response = ['success' => false, 'message' => 'Comment content is required.'];
        } else {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $post_id, $user_id, $content);
            if ($stmt->execute()) {
                $comment_id = $stmt->insert_id;
                $stmt->close();

                $stmt = $conn->prepare("SELECT username, image FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $response = [
                    'success' => true,
                    'message' => 'Comment posted successfully!',
                    'comment' => [
                        'id' => $comment_id,
                        'content' => htmlspecialchars($content),
                        'username' => htmlspecialchars($user['username']),
                        'image' => $user['image'] ?? 'default-profile.png'
                    ]
                ];
            } else {
                $response = ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
        }
    }

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        header("Location: dashboard.php");
        exit();
    }
}

// Fetch complaints with like and comment counts
$department_filter = $_GET['department'] ?? '';
$search_query = trim($_GET['q'] ?? '');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build dynamic WHERE clause and params
$whereClauses = [];
$types = '';
$params = [];
if ($department_filter !== '') {
    $whereClauses[] = 'c.department = ?';
    $types .= 's';
    $params[] = $department_filter;
}
if ($search_query !== '') {
    $whereClauses[] = '(c.title LIKE ? OR c.content LIKE ?)';
    $types .= 'ss';
    $like = '%' . $search_query . '%';
    $params[] = $like;
    $params[] = $like;
}

// Handle additional filters from explore
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

if ($status_filter !== '') {
    $whereClauses[] = 'c.status = ?';
    $types .= 's';
    $params[] = $status_filter;
}

if ($date_filter !== '') {
    $date_condition = '';
    switch ($date_filter) {
        case 'today':
            $date_condition = 'DATE(c.created_at) = CURDATE()';
            break;
        case 'week':
            $date_condition = 'c.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
            break;
        case 'month':
            $date_condition = 'c.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
            break;
    }
    if ($date_condition) {
        $whereClauses[] = $date_condition;
    }
}
$whereSql = count($whereClauses) ? (' WHERE ' . implode(' AND ', $whereClauses)) : '';

// Count total for pagination
$count_sql = "SELECT COUNT(*) as total FROM complaints c" . $whereSql;
$count_stmt = $conn->prepare($count_sql);
if ($types !== '') {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();
$total_pages = max(1, (int)ceil($total / $limit));

// Fetch complaints with like and comment counts, with pagination
$sql = "SELECT c.*, u.username, u.image,
        COALESCE(like_counts.like_count, 0) as like_count,
        COALESCE(comment_counts.comment_count, 0) as comment_count
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN (SELECT post_id, COUNT(*) as like_count FROM likes GROUP BY post_id) like_counts ON c.id = like_counts.post_id
        LEFT JOIN (SELECT post_id, COUNT(*) as comment_count FROM comments GROUP BY post_id) comment_counts ON c.id = comment_counts.post_id" . $whereSql . " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$bind_types = $types . 'ii';
if ($types !== '') {
    $stmt->bind_param($bind_types, ...array_merge($params, [$limit, $offset]));
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$complaints = $stmt->get_result();

// Simple user stats
$my_count = 0;
$mc_stmt = $conn->prepare('SELECT COUNT(*) AS c FROM complaints WHERE user_id = ?');
$mc_stmt->bind_param('i', $user_id);
$mc_stmt->execute();
$my_count = (int)($mc_stmt->get_result()->fetch_assoc()['c'] ?? 0);
$mc_stmt->close();

// Fetch comments for each post
$comments_by_post = [];
$comment_sql = "SELECT cm.*, u.username, u.image FROM comments cm JOIN users u ON cm.user_id = u.id ORDER BY cm.created_at DESC";
$comment_result = $conn->query($comment_sql);
while ($comment = $comment_result->fetch_assoc()) {
    $comments_by_post[$comment['post_id']][] = $comment;
}

$departments = [
    'Academic Affairs', 'Admissions Office', 'Cafeteria', 'Campus Security', 
    'Career Services', 'Financial Aid', 'Health Services', 'Hostel Management', 
    'IT Services', 'Library Services', 'Maintenance', 'Registrar Office', 
    'Student Affairs', 'Student Life', 'Transportation', 'Vice Chancellor Office'
];

// Department statistics
$dept_stats = [];
foreach ($departments as $dept) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total, 
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
        FROM complaints WHERE department = ?");
    $stmt->bind_param("s", $dept);
    $stmt->execute();
    $dept_stats[$dept] = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Admin overview statistics and latest complaints
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // Aggregate status counts
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
    FROM complaints");
    $stmt->execute();
    $admin_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Latest complaints for triage
    $stmt = $conn->prepare("SELECT c.id, c.title, c.status, c.department, c.created_at, u.username
        FROM complaints c JOIN users u ON c.user_id = u.id
        ORDER BY c.created_at DESC LIMIT 10");
    $stmt->execute();
    $admin_latest = $stmt->get_result();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emoji-mart@latest/css/emoji-mart.css">
</head>
<body class="dark-mode">
    <div class="d-flex">
        <!-- Left Sidebar -->
		<aside class="left-sidebar sidebar p-4">
			<div class="logo mb-3 d-flex align-items-center gap-1">
				<img src="assets/logo.png" alt="Bugema" class="logo-img">
				<h3 class="text-white fw-bold m-0">Bugema Complaints</h3>
			</div>
            <nav class="nav flex-column gap-3">
                <a href="dashboard.php" class="nav-link d-flex align-items-center gap-2 <?php echo !$department_filter ? 'active' : ''; ?>">
                    <i class="bi bi-house fs-3"></i><span>Home</span>
                </a>
                <a href="#" class="nav-link d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#exploreModal">
                    <i class="bi bi-search fs-3"></i><span>Explore</span>
                </a>
                <a href="#" class="nav-link d-flex align-items-center gap-2">
                    <i class="bi bi-bell fs-3"></i><span>Notifications</span>
                </a>
                <a href="#" class="nav-link d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#departmentsModal">
                    <i class="bi bi-building fs-3"></i><span>Departments</span>
                    <span class="badge bg-primary ms-auto"><?php echo count($departments); ?></span>
                </a>
                <?php if ($_SESSION['role'] == 'student'): ?>
                    <button class="nav-link btn btn-primary mt-2 post-button d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#postModal">
                        <i class="bi bi-pencil-square fs-3"></i><span>Post</span>
                    </button>
                <?php endif; ?>
				<a href="#" class="nav-link d-flex align-items-center gap-2 mt-auto profile-link">
					<span class="bi bi-person-circle fs-3"></span>
					<span><?php echo $_SESSION['username']; ?></span>
				</a>
            </nav>
            <div class="modal fade" id="departmentsModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Department Management</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3">Filter by Department</h6>
                                    <a href="dashboard.php" class="d-block mb-2 p-2 rounded <?php echo !$department_filter ? 'bg-primary text-white' : 'bg-light'; ?>">
                                        <i class="bi bi-grid-3x3-gap me-2"></i>All Departments
                                        <span class="badge bg-secondary ms-2"><?php echo array_sum(array_column($dept_stats, 'total')); ?></span>
                                    </a>
                                    <?php foreach ($departments as $dept): ?>
                                        <a href="dashboard.php?department=<?php echo urlencode($dept); ?>" class="d-block mb-2 p-2 rounded <?php echo $department_filter === $dept ? 'bg-primary text-white' : 'bg-light'; ?>">
                                            <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($dept); ?>
                                            <span class="badge bg-secondary ms-2"><?php echo $dept_stats[$dept]['total']; ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3">Department Statistics</h6>
                                    <?php if ($department_filter): ?>
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($department_filter); ?></h6>
                                                <div class="row text-center">
                                                    <div class="col-4">
                                                        <div class="text-warning fw-bold"><?php echo $dept_stats[$department_filter]['open_count']; ?></div>
                                                        <small class="text-muted">Open</small>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="text-info fw-bold"><?php echo $dept_stats[$department_filter]['in_progress_count']; ?></div>
                                                        <small class="text-muted">In Progress</small>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="text-success fw-bold"><?php echo $dept_stats[$department_filter]['resolved_count']; ?></div>
                                                        <small class="text-muted">Resolved</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach (array_slice($departments, 0, 6) as $dept): ?>
                                                <div class="col-6 mb-2">
                                                    <div class="card">
                                                        <div class="card-body p-2">
                                                            <div class="fw-bold small"><?php echo htmlspecialchars($dept); ?></div>
                                                            <div class="text-muted small"><?php echo $dept_stats[$dept]['total']; ?> complaints</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">Showing top 6 departments. Click "All Departments" to see complete list.</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Explore Modal -->
            <div class="modal fade" id="exploreModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Explore Complaints</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <h6 class="fw-bold mb-3">Search & Filter</h6>
                                    <form id="explore-form">
                                        <div class="mb-3">
                                            <label class="form-label">Search Keywords</label>
                                            <input type="text" id="explore-search" class="form-control" placeholder="Search complaints...">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Department</label>
                                            <select id="explore-department" class="form-select">
                                                <option value="">All Departments</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <select id="explore-status" class="form-select">
                                                <option value="">All Status</option>
                                                <option value="open">Open</option>
                                                <option value="in_progress">In Progress</option>
                                                <option value="resolved">Resolved</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Date Range</label>
                                            <select id="explore-date" class="form-select">
                                                <option value="">All Time</option>
                                                <option value="today">Today</option>
                                                <option value="week">This Week</option>
                                                <option value="month">This Month</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">Search</button>
                                    </form>
                                </div>
                                <div class="col-md-8">
                                    <h6 class="fw-bold mb-3">Search Results</h6>
                                    <div id="explore-results">
                                        <div class="text-center text-muted py-4">
                                            <i class="bi bi-search fs-1"></i>
                                            <p>Enter search criteria to find complaints</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Trending Topics -->
                            <div class="mt-4">
                                <h6 class="fw-bold mb-3">Trending Topics</h6>
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-primary me-1">Wi-Fi Issues</span>
                                        <small class="text-muted">(12 complaints)</small>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-warning me-1">Cafeteria Food</span>
                                        <small class="text-muted">(8 complaints)</small>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-info me-1">Library Hours</span>
                                        <small class="text-muted">(6 complaints)</small>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <span class="badge bg-success me-1">Hostel Maintenance</span>
                                        <small class="text-muted">(4 complaints)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
		<main class="feed flex-grow-1 p-3">
			<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
			<!-- Admin Overview -->
			<section class="mb-3">
				<h2 class="h6 mb-2">Admin Overview</h2>
				<div class="row g-2">
					<div class="col-6 col-md-3">
						<div class="card border-0 shadow-sm">
							<div class="card-body py-2">
								<div class="text-muted small">Total</div>
								<div class="fw-bold fs-5"><?php echo (int)($admin_stats['total'] ?? 0); ?></div>
							</div>
						</div>
					</div>
					<div class="col-6 col-md-3">
						<div class="card border-0 shadow-sm">
							<div class="card-body py-2">
								<div class="text-muted small">Open</div>
								<div class="fw-bold fs-5 text-warning"><?php echo (int)($admin_stats['open_count'] ?? 0); ?></div>
							</div>
						</div>
					</div>
					<div class="col-6 col-md-3">
						<div class="card border-0 shadow-sm">
							<div class="card-body py-2">
								<div class="text-muted small">In Progress</div>
								<div class="fw-bold fs-5 text-info"><?php echo (int)($admin_stats['in_progress_count'] ?? 0); ?></div>
							</div>
						</div>
					</div>
					<div class="col-6 col-md-3">
						<div class="card border-0 shadow-sm">
							<div class="card-body py-2">
								<div class="text-muted small">Resolved</div>
								<div class="fw-bold fs-5 text-success"><?php echo (int)($admin_stats['resolved_count'] ?? 0); ?></div>
							</div>
						</div>
					</div>
				</div>
				<div class="card mt-2 border-0 shadow-sm">
					<div class="card-body p-2">
						<div class="d-flex justify-content-between align-items-center mb-2">
							<h3 class="h6 m-0">Latest Complaints</h3>
							<small class="text-muted">Recent 10</small>
						</div>
						<div class="table-responsive">
							<table class="table table-sm align-middle mb-0">
								<thead>
									<tr>
										<th>ID</th>
										<th>Title</th>
										<th>User</th>
										<th>Department</th>
										<th>Status</th>
										<th>Created</th>
									</tr>
								</thead>
								<tbody>
									<?php if (isset($admin_latest) && $admin_latest && $admin_latest->num_rows > 0): ?>
										<?php while ($row = $admin_latest->fetch_assoc()): ?>
											<tr>
												<td>#<?php echo (int)$row['id']; ?></td>
												<td class="text-truncate" style="max-width: 240px;" title="<?php echo htmlspecialchars($row['title']); ?>"><?php echo htmlspecialchars($row['title']); ?></td>
												<td><?php echo htmlspecialchars($row['username']); ?></td>
												<td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['department'] ?: '—'); ?></span></td>
												<td>
													<select class="form-select form-select-sm status-select" data-post-id="<?php echo (int)$row['id']; ?>" style="width:auto;">
														<option value="open" <?php echo $row['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
														<option value="in_progress" <?php echo $row['status'] == 'in_progress' ? 'selected' : ''; ?>>In progress</option>
														<option value="resolved" <?php echo $row['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
													</select>
												</td>
												<td><small class="text-muted"><?php echo $row['created_at']; ?></small></td>
											</tr>
										<?php endwhile; ?>
									<?php else: ?>
										<tr><td colspan="6" class="text-center text-muted">No data</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</section>
			<?php endif; ?>
			<header class="d-flex justify-content-between align-items-center mb-3">
				<div class="w-100 d-flex align-items-center justify-content-between gap-2">
					<div>
						<h1 class="h5 mb-1">Complaint Feed <?php echo $department_filter ? "($department_filter)" : ''; ?></h1>
						<p class="text-muted mb-0 small">Track, review, and resolve student complaints</p>
					</div>
					<div class="d-none d-md-flex align-items-center gap-2 ms-2">
						<span class="badge bg-secondary">My complaints: <?php echo $my_count; ?></span>
					</div>
					<form class="d-flex" method="GET" action="dashboard.php" style="max-width: 320px;">
						<?php if ($department_filter): ?>
							<input type="hidden" name="department" value="<?php echo htmlspecialchars($department_filter); ?>">
						<?php endif; ?>
						<input type="search" name="q" value="<?php echo htmlspecialchars($search_query); ?>" class="form-control form-control-sm me-1" placeholder="Search complaints...">
						<button class="btn btn-sm btn-outline-secondary" type="submit">Search</button>
					</form>
					<button id="theme-toggle" class="btn btn-sm btn-primary ms-2">Light Mode</button>
				</div>
			</header>
			<?php if (isset($_GET['success'])): ?>
				<div class="alert alert-success alert-dismissible fade show" role="alert">
					<?php echo htmlspecialchars($_GET['success']); ?>
					<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				</div>
			<?php endif; ?>
            <?php if ($complaints->num_rows === 0): ?>
                <p>No complaints found<?php echo $department_filter ? " for $department_filter" : ''; ?>.</p>
            <?php else: ?>
				<?php while ($complaint = $complaints->fetch_assoc()): ?>
					<div class="post card mb-3 shadow-sm border-0" data-post-id="<?php echo $complaint['id']; ?>">
						<div class="card-body p-3">
						<div class="post-header d-flex gap-2 align-items-center">
							<span class="bi bi-person-circle fs-5"></span>
							<span class="username"><?php echo htmlspecialchars($complaint['username']); ?></span>
							<span class="timestamp text-muted small"><?php echo $complaint['created_at']; ?></span>
							<span class="status badge rounded-pill <?php echo $complaint['status'] == 'open' ? 'bg-warning' : ($complaint['status'] == 'in_progress' ? 'bg-info' : 'bg-success'); ?>" data-status-value="<?php echo $complaint['status']; ?>">
                                <?php echo $complaint['status']; ?>
                            </span>
                            <?php if (in_array($_SESSION['role'], ['staff', 'admin'])): ?>
                                <select class="form-select form-select-sm ms-2 status-select" data-post-id="<?php echo $complaint['id']; ?>" style="width:auto;">
                                    <option value="open" <?php echo $complaint['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $complaint['status'] == 'in_progress' ? 'selected' : ''; ?>>In progress</option>
                                    <option value="resolved" <?php echo $complaint['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                </select>
                            <?php endif; ?>
						<?php if ($complaint['department']): ?>
							<span class="department badge bg-secondary rounded-pill"><?php echo htmlspecialchars($complaint['department']); ?></span>
                            <?php endif; ?>
                        </div>
						<h3 class="card-title mt-2 mb-1"><?php echo htmlspecialchars($complaint['title']); ?></h3>
						<p class="card-text mb-2"><?php echo htmlspecialchars($complaint['content']); ?></p>
                        <?php if ($complaint['media_path']): ?>
                            <?php if (strpos($complaint['media_path'], '.mp4') !== false): ?>
								<video controls class="w-100 rounded border" style="max-height: 200px;">
                                    <source src="<?php echo htmlspecialchars($complaint['media_path']); ?>" type="video/mp4">
                                </video>
                            <?php else: ?>
								<img src="<?php echo htmlspecialchars($complaint['media_path']); ?>" alt="Media" class="w-100 rounded border" style="max-height: 200px;">
                            <?php endif; ?>
                        <?php endif; ?>
						<div class="post-actions d-flex gap-2 mt-3">
							<button class="btn btn-sm btn-outline-primary d-inline-flex align-items-center like-btn" data-post-id="<?php echo $complaint['id']; ?>">
                                <i class="bi bi-heart"></i> Like (<span class="like-count"><?php echo $complaint['like_count']; ?></span>)
                            </button>
							<button class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center share-btn" data-post-id="<?php echo $complaint['id']; ?>">
                                <i class="bi bi-share"></i> Share
                            </button>
							<button class="btn btn-sm btn-outline-primary d-inline-flex align-items-center repost-btn" data-post-id="<?php echo $complaint['id']; ?>">
                                <i class="bi bi-repeat"></i> Repost
                            </button>
							<button class="btn btn-sm btn-outline-info d-inline-flex align-items-center comment-btn" data-bs-toggle="collapse" data-bs-target="#comments-<?php echo $complaint['id']; ?>">
                                <i class="bi bi-chat"></i> Comment (<span class="comment-count"><?php echo $complaint['comment_count']; ?></span>)
                            </button>
                        </div>
					<div class="collapse" id="comments-<?php echo $complaint['id']; ?>">
                            <div class="comments-list mt-2">
                                <?php if (isset($comments_by_post[$complaint['id']])): ?>
                                    <?php foreach ($comments_by_post[$complaint['id']] as $comment): ?>
									<div class="comment card mb-2 border-0 shadow-sm">
										<div class="card-body p-2">
                                                <div class="d-flex gap-2 align-items-center">
                                                    <img src="assets/<?php echo $comment['image'] ?? 'default-profile.png'; ?>" alt="User" class="profile-pic rounded-circle" style="width: 32px; height: 32px;">
                                                    <span class="username"><?php echo htmlspecialchars($comment['username']); ?></span>
												<span class="timestamp text-muted small"><?php echo $comment['created_at']; ?></span>
                                                </div>
											<p class="card-text mt-1 mb-1"><?php echo htmlspecialchars($comment['content']); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
						<form class="comment-form mt-2" data-post-id="<?php echo $complaint['id']; ?>">
                                <input type="hidden" name="action" value="comment">
                                <input type="hidden" name="post_id" value="<?php echo $complaint['id']; ?>">
							<textarea name="content" class="form-control mb-2" placeholder="Add a comment..." rows="2" required></textarea>
                                <button type="submit" class="btn btn-sm btn-primary">Post Comment</button>
                            </form>
                        </div>
                        <div class="x-post mt-2">
                            </p>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
				<!-- Pagination -->
				<nav aria-label="Page navigation" class="mt-3">
					<ul class="pagination pagination-sm justify-content-center">
						<li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
							<a class="page-link" href="?<?php echo http_build_query(array_filter(['department' => $department_filter ?: null, 'q' => $search_query ?: null, 'page' => max(1, $page - 1)])); ?>">Prev</a>
						</li>
						<li class="page-item disabled"><span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span></li>
						<li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
							<a class="page-link" href="?<?php echo http_build_query(array_filter(['department' => $department_filter ?: null, 'q' => $search_query ?: null, 'page' => min($total_pages, $page + 1)])); ?>">Next</a>
						</li>
					</ul>
				</nav>
            <?php endif; ?>
        </main>

        <!-- Right Sidebar -->
        <aside class="right-sidebar sidebar p-3">
            <div class="live-section mb-4">
                <h3>Live on Bugema Students Complaint Center</h3>
                <p>Stay updated with the latest complaints and resolutions.</p>
            </div>
            <div class="trending-section">
                <h3>What's Happening</h3>
                <ul class="list-unstyled">
                    <li class="mb-2">Library Wi-Fi issues reported</li>
                    <li class="mb-2">Cafeteria menu feedback trending</li>
                    <li>Campus security updates</li>
                </ul>
            </div>
        </aside>

        <!-- Post Modal -->
        <?php if ($_SESSION['role'] == 'student'): ?>
        <div class="modal fade" id="postModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">New Complaint</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="post-form" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="post">
                            <div class="mb-3">
                                <input type="text" name="title" class="form-control" placeholder="Complaint Title (e.g., Cafeteria Issue)" required>
                            </div>
                            <div class="mb-3">
                                <textarea name="content" id="content-textarea" class="form-control" placeholder="Describe your complaint..." rows="4" required></textarea>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="button" id="emoji-btn" class="btn btn-sm btn-outline-secondary">😊 Emoji</button>
                                    <button type="button" id="gif-btn" class="btn btn-sm btn-outline-secondary">🎥 GIF</button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <select name="department" id="department" class="form-select">
                                    <option value="">Select Department (optional)</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Choose the department most relevant to your complaint</div>
                            </div>
                            <div class="mb-3">
                                <label for="media" class="form-label">Add Photo or Video (Optional)</label>
                                <input type="file" name="media" id="media" class="form-control" accept="image/jpeg,image/png,video/mp4">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Post Complaint</button>
                        </form>
                        <div id="post-error" class="text-danger mt-2" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/emoji-mart@latest/dist/browser.js"></script>
    <script src="https://platform.twitter.com/widgets.js" async></script>
    <script src="script.js" defer></script>
</body>
</html>