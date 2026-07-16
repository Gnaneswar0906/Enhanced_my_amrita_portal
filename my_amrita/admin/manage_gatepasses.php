<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $gpid = intval($_POST['gatepass_id'] ?? 0);
    if ($_POST['action'] === 'approve' && $gpid) {
        $pdo->prepare('UPDATE gate_passes SET status="Approved", approved_by=? WHERE id=?')->execute([$admin_name, $gpid]);
        $msg = 'approved';
    } elseif ($_POST['action'] === 'reject' && $gpid) {
        $pdo->prepare('UPDATE gate_passes SET status="Rejected" WHERE id=?')->execute([$gpid]);
        $msg = 'rejected';
    }
}
$table_alias = 's';
require_once 'filter_logic.php';

$passes = $pdo->query('SELECT g.*, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
                       FROM gate_passes g 
                       JOIN students s ON g.student_id = s.id 
                       WHERE 1=1 ' . $filter_sql . ' 
                       ORDER BY FIELD(g.status,"Pending","Approved","Rejected"), g.created_at DESC');
$passes->execute($filter_params);
$passes = $passes->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita Admin - Gate Passes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .approve-btn { background:#27ae60; color:#fff; border:none; padding:5px 12px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; }
        .reject-btn { background:#c0392b; color:#fff; border:none; padding:5px 12px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Admin Panel (Beta)</span>
        <div class="nav-links">
            <span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Gate Passes</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-ticket"></i> Gate Passes</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a>
        </div>

        <?php if ($msg === 'approved'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Gate pass approved.</div>
        <?php elseif ($msg === 'rejected'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Gate pass rejected.</div>
        <?php endif; ?>

        <?php 
        $filter_count = count($passes);
        include 'filter_ui.php'; 
        ?>

        <div class="card">
            <h2 class="card-title">All Gate Pass Requests (<?php echo $filter_count; ?>)</h2>
            <?php if (empty($passes)): ?>
                <div class="empty-state"><i class="fa fa-ticket"></i><p>No gate pass requests.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Reason</th><th>Urgency</th><th>From</th><th>To</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($passes as $g): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($g['student_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($g['enrollment_no']); ?></small><br>
                            <span style="font-size:10px; color:#888;">
                                <?php echo htmlspecialchars($g['batch'].' | '.$g['branch'].' | Sec '.$g['section'].' | Sem '.$g['current_sem']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($g['reason']); ?></td>
                        <td>
                            <?php
                                $urg = $g['urgency'] ?? 'Normal';
                                $uc = $urg === 'Emergency' ? 'badge-failed' : ($urg === 'Urgent' ? 'badge-pending' : 'badge-approved');
                                echo "<span class='badge $uc'>$urg</span>";
                            ?>
                        </td>
                        <td><?php echo date('d M Y H:i', strtotime($g['from_date'])); ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($g['to_date'])); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($g['status']); ?>"><?php echo $g['status']; ?></span></td>
                        <td>
                            <?php if ($g['status'] === 'Pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="gatepass_id" value="<?php echo $g['id']; ?>">
                                <button type="submit" name="action" value="approve" class="approve-btn"><i class="fa fa-check"></i> Approve</button>
                                <button type="submit" name="action" value="reject" class="reject-btn"><i class="fa fa-times"></i> Reject</button>
                            </form>
                            <?php else: ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
