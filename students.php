<?php
require 'db.php';
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student'])) {
        $regno = strtoupper(trim($_POST['regno']));
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        
        // Validate registration number format (2 digits + RP + 5 digits)
        if (!preg_match('/^\d{2}RP\d{5}$/', $regno)) {
            $error = "Registration number must be in the format: 21RP04216 (2 digits + RP + 5 digits)";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO students (regno, name, phone_number) VALUES (?, ?, ?)");
                $stmt->execute([$regno, $name, $phone]);
                
                // Store success notification
                echo "<script>
                    localStorage.setItem('adminNotification', JSON.stringify({
                        title: 'Success',
                        message: 'Student added successfully!',
                        type: 'success'
                    }));
                    window.location.href = 'students.php';
                </script>";
                exit;
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error = "Student with this registration number already exists.";
                } else {
                    $error = "Error adding student: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['update_student'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        
        $stmt = $pdo->prepare("UPDATE students SET name = ?, phone_number = ? WHERE regno = ?");
        if ($stmt->execute([$name, $phone, $id])) {
            echo "<script>
                localStorage.setItem('adminNotification', JSON.stringify({
                    title: 'Success',
                    message: 'Student updated successfully!',
                    type: 'success'
                }));
                window.location.href = 'students.php';
            </script>";
            exit;
        } else {
            $error = "Error updating student.";
        }
    } elseif (isset($_POST['delete_student'])) {
        $id = $_POST['id'];
        
        // Check if student has any appeals before deleting
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appeals WHERE student_regno = ?");
        $stmt->execute([$id]);
        $appealCount = $stmt->fetchColumn();
        
        if ($appealCount > 0) {
            $error = "Cannot delete student with existing appeals. Delete appeals first.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM students WHERE regno = ?");
            if ($stmt->execute([$id])) {
                echo "<script>
                    localStorage.setItem('adminNotification', JSON.stringify({
                        title: 'Success',
                        message: 'Student deleted successfully!',
                        type: 'success'
                    }));
                    window.location.href = 'students.php';
                </script>";
                exit;
            } else {
                $error = "Error deleting student.";
            }
        }
    }
}

// Get all students
$stmt = $pdo->query("SELECT * FROM students ORDER BY regno");
$students = $stmt->fetchAll();

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$totalStudents = count($students);
$totalPages = ceil($totalStudents / $perPage);
$students = array_slice($students, ($page - 1) * $perPage, $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students | Student Appeals System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reuse styles from admin.php */
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
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
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
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
        
        .modal-header {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-danger {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
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
            <li>
                <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li class="active">
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
                    <h5 class="mb-0">Manage Students</h5>
                </div>
                <div class="d-flex">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus me-2"></i>Add Student
                    </button>
                </div>
            </div>
        </nav>

        <!-- Content -->
        <div class="container-fluid mt-4">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Student List</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Reg No</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= $student['regno'] ?></td>
                                    <td><?= htmlspecialchars($student['name']) ?></td>
                                    <td><?= $student['phone_number'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-student" 
                                                data-id="<?= $student['regno'] ?>"
                                                data-name="<?= htmlspecialchars($student['name']) ?>"
                                                data-phone="<?= $student['phone_number'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="id" value="<?= $student['regno'] ?>">
                                            <button type="submit" name="delete_student" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this student?')">
                                                <i class="fas fa-trash"></i>
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
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStudentModalLabel">Add New Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="regno" class="form-label">Registration Number</label>
                            <input type="text" class="form-control" id="regno" name="regno" 
                                   placeholder="e.g., 22RP01641" required
                                   pattern="\d{2}RP\d{5}" 
                                   title="Format: 2 digits + RP + 5 digits (e.g., 22RP01641)">
                            <div class="form-text">Format: 2 digits + RP + 5 digits (e.g., 22RP01641)</div>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_student" class="btn btn-primary">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStudentModalLabel">Edit Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_regno" class="form-label">Registration Number</label>
                            <input type="text" class="form-control" id="edit_regno" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="edit_phone" name="phone" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
                    </div>
                </form>
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
        
        // Handle edit student button clicks
        document.querySelectorAll('.edit-student').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const phone = this.getAttribute('data-phone');
                
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_regno').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_phone').value = phone;
                
                const editModal = new bootstrap.Modal(document.getElementById('editStudentModal'));
                editModal.show();
            });
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
    </script>
</body>
</html>