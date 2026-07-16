<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id = $_POST['id'];
    $status = $_POST['status'];
    try {
        $stmt = $pdo->prepare("UPDATE `refunds` SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    } catch (Exception $e) {
        // Fallback if status column doesn't exist
    }
}

$table_alias = 's';
require_once 'filter_logic.php';

// Fetch all records with student details
$records = [];
try {
    $sql = "SELECT m.*, s.name as student_name, s.enrollment_no as reg_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
            FROM `refunds` m 
            JOIN students s ON m.student_id = s.id 
            WHERE 1=1 " . $filter_sql . " 
            ORDER BY m.id DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filter_params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Manage Refunds</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .page-header { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e8e8e8; }
        .page-header h1 { margin: 0; font-size: 20px; color: #a4123f; font-weight: 700; }
        .back-btn { background: #f8f9fa; border: 1px solid #ddd; padding: 8px 16px; border-radius: 6px; color: #333; text-decoration: none; font-size: 13px; font-weight: 600; }
        .data-table th { background: #1a1a2e; color: #fff; padding: 12px; font-size: 12px; text-transform: uppercase; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .status-select { padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Admin Panel</span>
        <div class="nav-links">
            <span><?php echo htmlspecialchars($admin_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>
    <div class="breadcrumb-bar"><a href="home.php">Home</a> <span class="sep">/</span> Manage Refunds</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-undo"></i> Manage Refunds</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
        
        <?php 
        $filter_count = count($records);
        include 'filter_ui.php'; 
        ?>
        
        <div class="card" style="background:#fff; border-radius:10px; padding:20px; border:1px solid #e8e8e8;">
            <?php if (empty($records)): ?>
                <div style="text-align:center; padding:40px; color:#888;">
                    <i class="fa fa-undo" style="font-size:40px; margin-bottom:10px; color:#ccc;"></i>
                    <p>No records found in this module for the selected cohort.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th>Name & Reg No</th>
                                <th>Cohort</th>
                                <?php 
                                $keys = array_keys($records[0]);
                                $hide = ['id', 'student_id', 'student_name', 'reg_no', 'batch', 'branch', 'section', 'current_sem'];
                                foreach ($keys as $k) {
                                    if (!in_array($k, $hide) && !is_numeric($k)) echo "<th>".htmlspecialchars($k)."</th>";
                                }
                                ?>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $r): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($r['student_name']); ?></strong><br><span style="font-size:11px; color:#888;"><?php echo htmlspecialchars($r['reg_no']); ?></span></td>
                                <td style="font-size:11px; color:#666;">
                                    <?php echo htmlspecialchars($r['batch'] . ' | ' . $r['branch']); ?><br>
                                    Sec <?php echo htmlspecialchars($r['section']); ?> | Sem <?php echo htmlspecialchars($r['current_sem']); ?>
                                </td>
                                <?php 
                                foreach ($r as $k => $v) {
                                    if (!in_array($k, $hide) && !is_numeric($k)) echo "<td>".htmlspecialchars(substr((string)$v, 0, 50))."</td>";
                                }
                                ?>
                                <td>
                                    <form method="POST" style="display:inline-flex; gap:6px;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id" value="<?php echo $r['id'] ?? ''; ?>">
                                        <select name="status" class="status-select">
                                            <option value="Pending" <?php echo (isset($r['status']) && $r['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Approved" <?php echo (isset($r['status']) && $r['status'] == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                            <option value="Rejected" <?php echo (isset($r['status']) && $r['status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="Resolved" <?php echo (isset($r['status']) && $r['status'] == 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                        <button type="submit" style="background:#27ae60; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">Update</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>