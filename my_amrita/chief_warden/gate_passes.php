<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chief_warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$cw_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $gpid = intval($_POST['gatepass_id'] ?? 0);
    if ($gpid && ($action === 'approve_l3' || $action === 'reject_l3')) {
        $new_status = $action === 'approve_l3' ? 'Approved' : 'Rejected';
        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($action === 'reject_l3' && empty($reason)) { $msg = 'reason_required'; }
        else {
            $stmt = $pdo->prepare('UPDATE gate_passes SET level3_status=?, level3_by=?, level3_at=NOW(), status=?, rejection_reason=COALESCE(rejection_reason,?) WHERE id=?');
            $stmt->execute([$new_status, $cw_name, $new_status, $reason ?: null, $gpid]);
            $gp = $pdo->prepare('SELECT student_id FROM gate_passes WHERE id=?'); $gp->execute([$gpid]); $sid = $gp->fetchColumn();
            if ($sid) {
                $note_msg = $action === 'approve_l3' ? 'Your gate pass has been FULLY APPROVED by '.$cw_name.'.' : 'Your gate pass rejected at Level-3 by '.$cw_name.'. Reason: '.$reason;
                $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?,?,?,"gatepass")')->execute([$sid, 'Gate Pass '.ucfirst($new_status).' (L3 Final)', $note_msg]);
            }
            $msg = $action === 'approve_l3' ? 'approved' : 'rejected';
        }
    }
}

// Get chief warden's gender assignment
$cw_gender_row = $pdo->prepare("SELECT hostel_gender FROM users WHERE id = ?");
$cw_gender_row->execute([$_SESSION['user_id']]);
$cw_gender = $cw_gender_row->fetchColumn() ?: 'All';

$gender_filter = ($cw_gender === 'Boys') ? 'Male' : (($cw_gender === 'Girls') ? 'Female' : '');

$query1 = 'SELECT gp.*, s.name, s.enrollment_no, s.hostel_block, s.hostel_room FROM gate_passes gp JOIN students s ON gp.student_id = s.id WHERE gp.level2_status = "Approved" AND gp.level3_status = "Pending"';
$query2 = 'SELECT gp.*, s.name, s.enrollment_no FROM gate_passes gp JOIN students s ON gp.student_id = s.id WHERE gp.level3_status IN ("Approved","Rejected")';
$params = [];

if ($gender_filter) {
    $query1 .= ' AND s.gender = ?';
    $query2 .= ' AND s.gender = ?';
    $params[] = $gender_filter;
}

$query1 .= ' ORDER BY gp.created_at DESC';
$query2 .= ' ORDER BY gp.level3_at DESC LIMIT 20';

$stmt = $pdo->prepare($query1);
$stmt->execute($params);
$pending = $stmt->fetchAll();

$stmt2 = $pdo->prepare($query2);
$stmt2->execute($params);
$processed = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Chief Warden - Gate Pass (Level-3)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>.action-btn{display:inline-block;padding:6px 16px;border-radius:6px;font-size:12px;font-weight:600;border:none;cursor:pointer;transition:all .2s;font-family:'Inter',sans-serif;} .approve-btn{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;} .reject-btn{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;} .action-btn:hover{transform:translateY(-1px);}</style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Chief Warden Portal</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($cw_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Chief Warden Home</a> <span class="sep">/</span> Gate Pass (Level-3)</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-ticket"></i> Gate Pass – Level-3 (Final Approval)</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>

        <?php if ($msg === 'approved'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Gate pass FULLY APPROVED.</div>
        <?php elseif ($msg === 'rejected'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Gate pass rejected at Level-3.</div>
        <?php endif; ?>

        <div class="alert-banner info"><i class="fa fa-info-circle"></i><span class="alert-text">These gate passes have been approved at Level-1 (Faculty Advisor) and Level-2 (Warden). Your approval is the final step.</span></div>

        <div class="card">
            <h2 class="card-title">Pending Final Approval (<?php echo count($pending); ?>)</h2>
            <?php if (empty($pending)): ?>
                <div class="empty-state"><i class="fa fa-check-circle"></i><p>No gate passes pending Level-3 approval.</p></div>
            <?php else: ?>
                <?php foreach ($pending as $gp): ?>
                <div style="background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:20px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                        <div>
                            <strong style="font-size:15px;"><?php echo htmlspecialchars($gp['name']); ?></strong>
                            <span style="color:#888; font-size:12px; margin-left:8px;"><?php echo htmlspecialchars($gp['enrollment_no']); ?></span>
                            <span class="badge badge-<?php echo strtolower($gp['urgency']); ?>" style="margin-left:8px;"><?php echo $gp['urgency']; ?></span>
                            <div style="font-size:12px; color:#888; margin-top:4px;"><i class="fa fa-home"></i> <?php echo htmlspecialchars($gp['hostel_room'] ?? '—'); ?></div>
                        </div>
                        <div style="text-align:right; font-size:12px; color:#888;">
                            <div>L1: <strong style="color:#27ae60;"><?php echo htmlspecialchars($gp['level1_by']); ?></strong></div>
                            <div>L2: <strong style="color:#1565c0;"><?php echo htmlspecialchars($gp['level2_by']); ?></strong></div>
                        </div>
                    </div>
                    <div style="font-size:14px; color:#333; margin-bottom:8px;"><?php echo htmlspecialchars($gp['reason']); ?></div>
                    <div style="font-size:13px; color:#666; margin-bottom:14px;"><i class="fa fa-calendar"></i> <?php echo date('d M Y H:i', strtotime($gp['from_date'])); ?> → <?php echo date('d M Y H:i', strtotime($gp['to_date'])); ?></div>
                    <form method="POST">
                        <input type="hidden" name="gatepass_id" value="<?php echo $gp['id']; ?>">
                        <div style="margin-bottom:10px;">
                            <label style="font-size:11px; font-weight:600; color:#c0392b; text-transform:uppercase;">Rejection Reason <span style="color:red;">*</span></label>
                            <textarea name="rejection_reason" style="width:100%; padding:6px 10px; border:1px solid #e0e0e0; border-radius:6px; font-size:12px; font-family:'Inter',sans-serif; margin-top:4px;" rows="2" placeholder="Reason for rejection..."></textarea>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="submit" name="action" value="approve_l3" class="action-btn approve-btn"><i class="fa fa-check"></i> Final Approve</button>
                            <button type="submit" name="action" value="reject_l3" class="action-btn reject-btn" onclick="var r=this.form.rejection_reason.value.trim(); if(!r){alert('Rejection reason is mandatory!');return false;}"><i class="fa fa-times"></i> Reject</button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-title">Recently Processed</h2>
            <?php if (empty($processed)): ?>
                <div class="empty-state"><i class="fa fa-history"></i><p>No processed gate passes.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Reason</th><th>L1</th><th>L2</th><th>L3 (Final)</th></tr></thead>
                    <tbody>
                        <?php foreach ($processed as $gp): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($gp['name']); ?></strong><br><small><?php echo htmlspecialchars($gp['enrollment_no']); ?></small></td>
                            <td><?php echo htmlspecialchars($gp['reason']); ?></td>
                            <td><span class="badge badge-<?php echo strtolower($gp['level1_status']); ?>"><?php echo $gp['level1_status']; ?></span></td>
                            <td><span class="badge badge-<?php echo strtolower($gp['level2_status']); ?>"><?php echo $gp['level2_status']; ?></span></td>
                            <td><span class="badge badge-<?php echo strtolower($gp['level3_status']); ?>"><?php echo $gp['level3_status']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
