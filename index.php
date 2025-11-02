<?php
include 'config.php';
if (isset($_SESSION['user_id']) && !isset($_GET['guest'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bugema Students Complaint Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .landing-container { min-height: 100vh; }
        .landing-hero { 
            background: linear-gradient( to bottom, rgba(0,0,0,.6), rgba(0,0,0,.6) ), url('assets/hero.jpg') center/cover no-repeat; 
            border-radius: 16px;
        }
        .landing-logo { font-weight: 800; letter-spacing: 0.5px; }
        .quick-tiles .card { transition: transform .15s ease, box-shadow .15s ease; }
        .quick-tiles .card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.2); }
        /* Page background image with blur */
        body.landing-bg { background: none !important; }
        body.landing-bg::before {
            content: '';
            position: fixed;
            inset: 0;
            background: url('assets/background.jpg') center/cover fixed no-repeat;
            filter: blur(0.1px);
            transform: scale(1.05); /* avoid edge clipping after blur */
            z-index: -2;
        }
        body.landing-bg::after {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(42, 42, 44, 0); /* subtle dark overlay for readability */
            z-index: -1;
        }
        body.landing-bg.dark-mode { color: #000033; }
    </style>
    </head>
<body class="dark-mode landing-bg d-flex align-items-center justify-content-center">
    <div class="container landing-container d-flex align-items-center justify-content-center py-4">
        <div class="w-100">
            <!-- Hero Banner -->
            <div class="landing-hero text-center text-white p-5 mb-4">
                <h1 class="landing-logo mb-2">Bugema Students Complaint Center</h1>
                <p class="mb-4">Your One-Stop Hub for Services & Support — Get Help, Fast!</p>
                <a href="dashboard.php" class="btn btn-primary btn-lg">Submit a Request Now</a>
            </div>

            <!-- Role-based Login/Register -->
            <div class="card mb-4">
                <div class="card-body d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-semibold">Jump back in:</span>
                        <select class="form-select form-select-sm" style="width:auto;">
                            <option>Student</option>
                            <option>Staff</option>
                            <option>Admin</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="login.php" class="btn btn-primary">Login</a>
                        <a href="register.php" class="btn btn-outline-primary">Sign Up</a>
                        <a href="login.php" class="btn btn-outline-secondary">Report Anonymously</a>
                    </div>
                </div>
            </div>

            <!-- Quick Action Tiles -->
            <div class="row quick-tiles g-3 mb-4">
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="dashboard.php#postModal" class="text-decoration-none" data-bs-toggle="modal">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="display-6">📝</div>
                                <h5 class="mt-2 mb-1">Lodge a Complaint</h5>
                                <p class="text-muted small mb-0">Submit details with attachments</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="dashboard.php" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="display-6">🔎</div>
                                <h5 class="mt-2 mb-1">Track My Requests</h5>
                                <p class="text-muted small mb-0">Use ID or keywords to find updates</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="dashboard.php?department=IT%20Services" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="display-6">💻</div>
                                <h5 class="mt-2 mb-1">Explore Services</h5>
                                <p class="text-muted small mb-0">Academics, IT, Finance, Housing…</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
                    <a href="dashboard.php" class="text-decoration-none">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="display-6">📢</div>
                                <h5 class="mt-2 mb-1">View Announcements</h5>
                                <p class="text-muted small mb-0">Stay updated with campus news</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Global Search -->
            <form action="dashboard.php" method="GET" class="card p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-sm-9">
                        <input type="search" class="form-control" name="q" placeholder="Search for IT help, fee issues, or complaint IDs…">
                    </div>
                    <div class="col-sm-3 d-grid">
                        <button class="btn btn-outline-secondary" type="submit">Search</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>