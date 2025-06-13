<?php
require 'db.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Setup
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$startAt = ($page - 1) * $perPage;

$statusFilter = $_GET['status'] ?? '';
$searchRegno = $_GET['search'] ?? '';

$params = [];
$where = "";

if ($statusFilter && in_array($statusFilter, ['pending', 'under review', 'resolved'])) {
    $where .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if ($searchRegno) {
    $where .= " AND s.regno LIKE ?";
    $params[] = "%$searchRegno%";
}

$sql = "SELECT a.id, s.name AS student_name, s.regno, m.module_name, a.reason, a.status, mk.mark
        FROM appeals a 
        JOIN students s ON a.student_regno = s.regno 
        JOIN modules m ON a.module_id = m.id 
        LEFT JOIN marks mk ON mk.student_regno = s.regno AND mk.module_id = m.id
        WHERE 1 $where
        LIMIT $startAt, $perPage";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appeals = $stmt->fetchAll();

// Total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) 
                            FROM appeals a 
                            JOIN students s ON a.student_regno = s.regno 
                            WHERE 1 $where");
$countStmt->execute($params);
$totalAppeals = $countStmt->fetchColumn();
$totalPages = ceil($totalAppeals / $perPage);

// Get stats for dashboard
$statsStmt = $pdo->query("SELECT 
    COUNT(*) as total_appeals,
    SUM(status = 'pending') as pending,
    SUM(status = 'under review') as under_review,
    SUM(status = 'resolved') as resolved
    FROM appeals");
$stats = $statsStmt->fetch();

// Get recent activities
$activitiesStmt = $pdo->query("SELECT a.id, s.name as student_name, a.status, a.created_at 
                              FROM appeals a
                              JOIN students s ON a.student_regno = s.regno
                              ORDER BY a.created_at DESC
                              LIMIT 5");
$activities = $activitiesStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Student Appeals System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            background-color: var(--secondary-color);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-menu {
            padding: 0;
            list-style: none;
        }
        
        .sidebar-menu li {
            padding: 10px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }
        
        .sidebar-menu li:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu li a {
            color: white;
            text-decoration: none;
            display: block;
        }
        
        .sidebar-menu li.active {
            background-color: var(--primary-color);
        }
        
        .sidebar-menu li i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .stat-card {
            border-radius: 10px;
            color: white;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .stat-card.total { background: linear-gradient(135deg, #3498db, #2c3e50); }
        .stat-card.pending { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-card.review { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .stat-card.resolved { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .badge-pending { background-color: var(--warning-color); }
        .badge-review { background-color: var(--primary-color); }
        .badge-resolved { background-color: var(--success-color); }
        
        .table-responsive {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table th {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .status-select {
            border: none;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .status-select.pending { background-color: #fef5e7; color: #f39c12; }
        .status-select.under-review { background-color: #ebf5fb; color: #3498db; }
        .status-select.resolved { background-color: #eafaf1; color: #2ecc71; }
        
        .status-select:focus {
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .navbar-toggler {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header text-center">
            <h4>Student Appeals</h4>
            <p class="mb-0 text-muted">Admin Dashboard</p>
        </div>
        <ul class="sidebar-menu">
            <li class="active">
                <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li>
                <a href="students.php"><i class="fas fa-users"></i> Students</a>
            </li>
            <li>
                <a href="modules.php"><i class="fas fa-book"></i> Modules</a>
            </li>
            <li>
                <a href="marks.php"><i class="fas fa-graduation-cap"></i> Marks</a>
            </li>
            <li>
                <a href="appeals.php"><i class="fas fa-file-alt"></i> Appeals</a>
            </li>
            <li>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            </li>
            <li>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button class="navbar-toggler d-lg-none" type="button" id="sidebarToggle">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="d-flex align-items-center">
                    <h5 class="mb-0">Welcome, <?= $_SESSION['admin'] ?></h5>
                </div>
                <div class="d-flex">
                    <span class="badge bg-primary me-3">
                        <i class="fas fa-bell"></i>
                    </span>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="https://via.placeholder.com/30" alt="Admin" width="30" height="30" class="rounded-circle me-2">
                            <span><?= $_SESSION['admin'] ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-user-cog me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="container-fluid mt-4">
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card total">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">TOTAL APPEALS</h6>
                                <h2 class="mb-0"><?= $stats['total_appeals'] ?></h2>
                            </div>
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card pending">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">PENDING</h6>
                                <h2 class="mb-0"><?= $stats['pending'] ?></h2>
                            </div>
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card review">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">UNDER REVIEW</h6>
                                <h2 class="mb-0"><?= $stats['under_review'] ?></h2>
                            </div>
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card resolved">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">RESOLVED</h6>
                                <h2 class="mb-0"><?= $stats['resolved'] ?></h2>
                            </div>
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Activities -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= $activity['student_name'] ?></strong>
                                        <span class="badge rounded-pill 
                                            <?= $activity['status'] == 'pending' ? 'bg-warning' : 
                                               ($activity['status'] == 'under review' ? 'bg-primary' : 'bg-success') ?>">
                                            <?= ucfirst($activity['status']) ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?= date('M d, Y H:i', strtotime($activity['created_at'])) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Appeals Table -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Recent Appeals</h5>
                            <div>
                                <a href="appeals.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Module</th>
                                            <th>Marks</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appeals as $a): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($a['student_name']) ?></strong><br>
                                                <small class="text-muted"><?= $a['regno'] ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($a['module_name']) ?></td>
                                            <td><?= is_numeric($a['mark']) ? $a['mark'] : 'N/A' ?></td>
                                            <td>
                                                <span class="badge rounded-pill 
                                                    <?= $a['status'] == 'pending' ? 'bg-warning' : 
                                                       ($a['status'] == 'under review' ? 'bg-primary' : 'bg-success') ?>">
                                                    <?= ucfirst($a['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="post" action="update_status.php" class="d-flex">
                                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                    <select name="status" class="status-select <?= str_replace(' ', '-', $a['status']) ?> me-2">
                                                        <option value="pending" <?= $a['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="under review" <?= $a['status'] == 'under review' ? 'selected' : '' ?>>Under Review</option>
                                                        <option value="resolved" <?= $a['status'] == 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm justify-content-center mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($searchRegno) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Check for localStorage notification
        document.addEventListener('DOMContentLoaded', function() {
            const notification = localStorage.getItem('adminNotification');
            if (notification) {
                const { title, message, type } = JSON.parse(notification);
                showToast(title, message, type);
                localStorage.removeItem('adminNotification');
            }
        });
        
        // Show toast notification
        function showToast(title, message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toastEl = document.createElement('div');
            toastEl.className = `toast show align-items-center text-white bg-${type} border-0`;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            
            const toastBody = document.createElement('div');
            toastBody.className = 'd-flex';
            
            const toastContent = document.createElement('div');
            toastContent.className = 'toast-body';
            
            const toastTitle = document.createElement('strong');
            toastTitle.className = 'me-auto';
            toastTitle.textContent = title;
            
            const toastMessage = document.createElement('div');
            toastMessage.textContent = message;
            
            toastContent.appendChild(toastTitle);
            toastContent.appendChild(document.createElement('br'));
            toastContent.appendChild(toastMessage);
            
            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'btn-close btn-close-white me-2 m-auto';
            closeButton.setAttribute('data-bs-dismiss', 'toast');
            closeButton.setAttribute('aria-label', 'Close');
            
            toastBody.appendChild(toastContent);
            toastBody.appendChild(closeButton);
            toastEl.appendChild(toastBody);
            toastContainer.appendChild(toastEl);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toastEl.classList.remove('show');
                setTimeout(() => toastEl.remove(), 300);
            }, 5000);
        }
        
        // Change select style based on status
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                this.className = `status-select ${this.value.replace(' ', '-')} me-2`;
            });
        });
    </script>
</body>
</html>