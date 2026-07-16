<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chief_warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$cw_name = $_SESSION['user_name'];

$cw_gender_row = $pdo->prepare("SELECT hostel_gender FROM users WHERE id = ?");
$cw_gender_row->execute([$_SESSION['user_id']]);
$cw_gender = $cw_gender_row->fetchColumn() ?: 'All';
$gender_filter = ($cw_gender === 'Boys') ? 'Male' : (($cw_gender === 'Girls') ? 'Female' : '');

// Overview stats
$today = date('Y-m-d');

if ($gender_filter) {
    $att_today = $pdo->prepare('SELECT ha.status, COUNT(*) as cnt FROM hostel_attendance ha JOIN students s ON ha.student_id = s.id WHERE ha.attendance_date = ? AND s.gender = ? GROUP BY ha.status');
    $att_today->execute([$today, $gender_filter]);
} else {
    $att_today = $pdo->prepare('SELECT ha.status, COUNT(*) as cnt FROM hostel_attendance ha WHERE ha.attendance_date = ? GROUP BY ha.status');
    $att_today->execute([$today]);
}
$att_stats = [];
while ($row = $att_today->fetch()) $att_stats[$row['status']] = $row['cnt'];

if ($gender_filter) {
    $id_stats = $pdo->prepare('SELECT ic.card_status, COUNT(*) as cnt FROM student_id_cards ic JOIN students s ON ic.student_id = s.id WHERE s.gender = ? GROUP BY ic.card_status');
    $id_stats->execute([$gender_filter]);
    $id_stats = $id_stats->fetchAll();
} else {
    $id_stats = $pdo->query('SELECT card_status, COUNT(*) as cnt FROM student_id_cards GROUP BY card_status')->fetchAll();
}
$id_map = [];
foreach ($id_stats as $r) $id_map[$r['card_status']] = $r['cnt'];

if ($gender_filter) {
    $rc_stmt = $pdo->prepare('SELECT s2.*, st.name, st.enrollment_no FROM services s2 JOIN students st ON s2.student_id = st.id WHERE s2.category = "Complaint" AND st.gender = ? ORDER BY s2.created_at DESC LIMIT 10');
    $rc_stmt->execute([$gender_filter]);
    $recent_complaints = $rc_stmt->fetchAll();
} else {
    $recent_complaints = $pdo->query('SELECT s2.*, st.name, st.enrollment_no FROM services s2 JOIN students st ON s2.student_id = st.id WHERE s2.category = "Complaint" ORDER BY s2.created_at DESC LIMIT 10')->fetchAll();
}

if ($gender_filter) {
    $bc_stmt = $pdo->prepare('SELECT ic.*, s.name, s.enrollment_no, s.hostel_block FROM student_id_cards ic JOIN students s ON ic.student_id = s.id WHERE ic.card_status = "Blocked" AND s.gender = ?');
    $bc_stmt->execute([$gender_filter]);
    $blocked_cards = $bc_stmt->fetchAll();
} else {
    $blocked_cards = $pdo->query('SELECT ic.*, s.name, s.enrollment_no, s.hostel_block FROM student_id_cards ic JOIN students s ON ic.student_id = s.id WHERE ic.card_status = "Blocked"')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Chief Warden - Hostel Overview</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-navbar"><span class="brand">Chief Warden Portal</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($cw_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Chief Warden Home</a> <span class="sep">/</span> Hostel Overview</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-building"></i> Hostel Overview</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>

        <!-- Today's Attendance Summary -->
        <div class="card">
            <h2 class="card-title">Today's Hostel Attendance (<?php echo date('d M Y'); ?>)</h2>
            <div class="sgpa-display">
                <div class="sgpa-card"><div class="sgpa-label">Present</div><div class="sgpa-value" style="color:#27ae60;"><?php echo $att_stats['Present'] ?? 0; ?></div></div>
                <div class="sgpa-card"><div class="sgpa-label">Absent</div><div class="sgpa-value" style="color:#e74c3c;"><?php echo $att_stats['Absent'] ?? 0; ?></div></div>
                <div class="sgpa-card"><div class="sgpa-label">Late</div><div class="sgpa-value" style="color:#f5a623;"><?php echo $att_stats['Late'] ?? 0; ?></div></div>
                <div class="sgpa-card"><div class="sgpa-label">On Leave</div><div class="sgpa-value" style="color:#888;"><?php echo $att_stats['On Leave'] ?? 0; ?></div></div>
            </div>
        </div>

        <!-- ID Card Status -->
        <div class="card">
            <h2 class="card-title">ID Card Status</h2>
            <div class="sgpa-display">
                <div class="sgpa-card"><div class="sgpa-label">Active</div><div class="sgpa-value" style="color:#27ae60;"><?php echo $id_map['Active'] ?? 0; ?></div></div>
                <div class="sgpa-card"><div class="sgpa-label">Blocked</div><div class="sgpa-value" style="color:#e74c3c;"><?php echo $id_map['Blocked'] ?? 0; ?></div></div>
                <div class="sgpa-card"><div class="sgpa-label">Lost</div><div class="sgpa-value" style="color:#f5a623;"><?php echo $id_map['Lost'] ?? 0; ?></div></div>
            </div>
            <?php if (!empty($blocked_cards)): ?>
            <h3 style="font-size:14px; color:#c62828; margin-top:20px;"><i class="fa fa-ban"></i> Currently Blocked IDs</h3>
            <table class="data-table" style="margin-top:10px;">
                <thead><tr><th>Student</th><th>Reason</th><th>Since</th><th>Days</th></tr></thead>
                <tbody>
                    <?php foreach ($blocked_cards as $bc): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($bc['name']); ?></strong><br><small><?php echo htmlspecialchars($bc['enrollment_no']); ?></small></td>
                        <td style="font-size:12px;"><?php echo htmlspecialchars($bc['block_reason'] ?? '—'); ?></td>
                        <td><?php echo $bc['blocked_since'] ? date('d M Y', strtotime($bc['blocked_since'])) : '—'; ?></td>
                        <td><?php echo $bc['blocked_days'] ?? 0; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent Complaints -->
        <div class="card">
            <h2 class="card-title">Recent Complaints</h2>
            <?php if (empty($recent_complaints)): ?>
                <div class="empty-state"><i class="fa fa-check-circle"></i><p>No recent complaints.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Type</th><th>Description</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_complaints as $rc): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($rc['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($rc['service_type']); ?></td>
                            <td style="font-size:12px; max-width:250px;"><?php echo htmlspecialchars($rc['description']); ?></td>
                            <td><span class="badge badge-<?php echo strtolower(str_replace(' ','-',$rc['status'])); ?>"><?php echo $rc['status']; ?></span></td>
                            <td><?php echo date('d M Y', strtotime($rc['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
