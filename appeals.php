<?php
require 'db.php';
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

// Setup
$perPage = 5;
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
?>


<!DOCTYPE html>
<html>
<head>
    <title>Admin - Appeals</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background-color: #f9f9f9;
        }

        h2, h3 {
            color: white;
        }

        a {
            text-decoration: none;
            color: #0066cc;
        }

        form {
            margin-bottom: 20px;
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        input[type="text"], select, button {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        button {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }

        button:hover {
            background-color: #218838;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f1f1f1;
        }

        td form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pagination {
            margin-top: 20px;
        }

        .pagination a {
            padding: 6px 12px;
            margin: 0 3px;
            background-color: #e9ecef;
            color: #333;
            border-radius: 4px;
        }

        .pagination a:hover {
            background-color: #d4d4d4;
        }
    </style>
</head>
<body>
<div style="background: #1590c1; padding: 1px;color: white;border-radius: 5px 5px 5px 5px;">
<h2><a href="logout.php" style="float: right;margin-right:2% ;"><button style="float: right;background:red;color: white;">Logout</button></a></h2>

<h1 style="text-align: center;">Student Appeals management system</h1>
<h4 style="margin-left: 2%;">Welcome, <?= $_SESSION['admin'] ?> </h4>
</div>
<form method="get" class="filter-bar" style="margin-top: 2%;">
    <input type="text" name="search" placeholder="Search by RegNo" value="<?= htmlspecialchars($searchRegno) ?>">
    <select name="status">
        <option value="">-- Status Filter --</option>
        <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="under review" <?= $statusFilter == 'under review' ? 'selected' : '' ?>>Under Review</option>
        <option value="resolved" <?= $statusFilter == 'resolved' ? 'selected' : '' ?>>Resolved</option>
    </select>
    <button type="submit">Filter</button>
</form>

<table>
    <tr>
        <th>#</th>
        <th>Student</th>
        <th>Module</th>
        <th>Marks</th>
        <th>Reason</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
    <?php foreach ($appeals as $i => $a): ?>
    <tr>
        <td><?= $i+1 + $startAt ?></td>
        <td><?= htmlspecialchars($a['student_name']) ?> (<?= $a['regno'] ?>)</td>
        <td><?= htmlspecialchars($a['module_name']) ?></td>
        <td><?= is_numeric($a['mark']) ? $a['mark'] : 'N/A' ?></td>
        <td><?= htmlspecialchars($a['reason']) ?></td>
        <td><?= ucfirst($a['status']) ?></td>
        <td>
            <form method="post" action="update_status.php">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <select name="status">
                    <option <?= $a['status'] == 'pending' ? 'selected' : '' ?>>pending</option>
                    <option <?= $a['status'] == 'under review' ? 'selected' : '' ?>>under review</option>
                    <option <?= $a['status'] == 'resolved' ? 'selected' : '' ?>>resolved</option>
                </select>
                <button type="submit">Update</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($searchRegno) ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>

</body>
</html>
