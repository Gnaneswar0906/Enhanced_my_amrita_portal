<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'request_gatepass') {
        $reason  = trim($_POST['reason'] ?? '');
        $urgency = trim($_POST['urgency'] ?? 'Normal');
        $from    = $_POST['from_date'] ?? '';
        $to      = $_POST['to_date'] ?? '';
        if ($reason && $from && $to) {
            $stmt = $pdo->prepare('INSERT INTO gate_passes (student_id, reason, urgency, from_date, to_date) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$student_id, $reason, $urgency, $from, $to]);
            $msg = 'success';
        } else { $msg = 'error'; }
    } elseif ($action === 'cancel_gatepass') {
        $gpid = intval($_POST['gatepass_id'] ?? 0);
        $creason = trim($_POST['cancel_reason'] ?? '');
        if ($gpid) {
            $pdo->prepare('UPDATE gate_passes SET status="Rejected" WHERE id=? AND student_id=?')->execute([$gpid, $student_id]);
            $pdo->prepare('INSERT INTO gatepass_cancellations (gatepass_id, student_id, reason) VALUES (?,?,?)')->execute([$gpid, $student_id, $creason]);
            $msg = 'cancel_success';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM gate_passes WHERE student_id = ? ORDER BY created_at DESC');
$stmt->execute([$student_id]);
$passes = $stmt->fetchAll();

$stmt2 = $pdo->prepare('SELECT * FROM faculty_advisors WHERE student_id = ?');
$stmt2->execute([$student_id]);
$advisor = $stmt2->fetch();

// Get student gender and warden info
$stInfo = $pdo->prepare("SELECT gender, warden_name FROM students WHERE id = ?");
$stInfo->execute([$student_id]);
$student_info = $stInfo->fetch();
$student_gender = $student_info['gender'] ?? 'Male';
$student_warden = $student_info['warden_name'] ?? '';

// Get gender-appropriate approvers
$wardens = [];
try {
    // Level-2: Student's specific floor warden
    if ($student_warden) {
        $stmt3 = $pdo->prepare("SELECT u.name, u.role, u.email, u.phone, u.department FROM users u WHERE u.role = 'warden' AND u.name = ?");
        $stmt3->execute([$student_warden]);
        $warden_row = $stmt3->fetch();
        if ($warden_row) $wardens[] = $warden_row;
    }
    // Level-3: Chief warden based on gender
    if ($student_gender === 'Female') {
        $cw = $pdo->query("SELECT u.name, u.role, u.email, u.phone, u.department FROM users u WHERE u.role = 'chief_warden' AND u.department LIKE '%Girls%' LIMIT 1")->fetch();
    } else {
        $cw = $pdo->query("SELECT u.name, u.role, u.email, u.phone, u.department FROM users u WHERE u.role = 'chief_warden' AND u.department LIKE '%Boys%' LIMIT 1")->fetch();
    }
    if ($cw) $wardens[] = $cw;
} catch(Exception $e) {}

$pipeline_steps = ['Level-1 (Advisor)', 'Level-2 (Warden)', 'Level-3 (Chief Warden)'];
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Gate Pass</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .approver-card { background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:16px; display:flex; gap:14px; align-items:center; }
        .approver-avatar { width:44px; height:44px; background:linear-gradient(135deg,#a4123f,#d4264f); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:16px; flex-shrink:0; }
        .approver-info { flex:1; }
        .approver-info .name { font-weight:600; font-size:14px; color:#333; }
        .approver-info .role { font-size:11px; color:#a4123f; text-transform:uppercase; font-weight:600; letter-spacing:0.3px; }
        .approver-info .details { font-size:12px; color:#666; margin-top:4px; }
        .approver-info .details i { color:#a4123f; margin-right:4px; width:14px; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Gate Pass</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-ticket"></i> Gate Pass</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Gate pass request submitted!</div>
        <?php elseif ($msg === 'cancel_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Gate pass cancelled.</div>
        <?php elseif ($msg === 'error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all required fields.</div>
        <?php endif; ?>

        <!-- Approval Chain – Faculty Details with Location -->
        <div class="card">
            <h2 class="card-title"><i class="fa fa-sitemap" style="color:#a4123f;"></i> Approval Chain – Faculty & Staff Details</h2>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:14px;">
                <?php if ($advisor): ?>
                <div class="approver-card" style="border-left:3px solid #27ae60;">
                    <div class="approver-avatar"><?php echo strtoupper(substr($advisor['faculty_name'], 0, 1)); ?></div>
                    <div class="approver-info">
                        <div class="role">Level-1 Approver (Faculty Advisor)</div>
                        <div class="name"><?php echo htmlspecialchars($advisor['faculty_name']); ?></div>
                        <div class="details">
                            <div><i class="fa fa-briefcase"></i> <?php echo htmlspecialchars($advisor['designation']); ?></div>
                            <div><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($advisor['office_room']); ?></div>
                            <div><i class="fa fa-envelope"></i> <a href="mailto:<?php echo htmlspecialchars($advisor['email']); ?>" style="color:#a4123f;"><?php echo htmlspecialchars($advisor['email']); ?></a></div>
                            <?php if ($advisor['phone']): ?><div><i class="fa fa-phone"></i> <?php echo htmlspecialchars($advisor['phone']); ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php foreach ($wardens as $w): ?>
                <div class="approver-card" style="border-left:3px solid <?php echo $w['role']==='warden' ? '#f5a623' : '#a4123f'; ?>;">
                    <div class="approver-avatar" style="background:<?php echo $w['role']==='warden' ? 'linear-gradient(135deg,#f5a623,#f7c948)' : 'linear-gradient(135deg,#a4123f,#d4264f)'; ?>;"><?php echo strtoupper(substr($w['name'], 0, 1)); ?></div>
                    <div class="approver-info">
                        <div class="role"><?php echo $w['role']==='warden' ? 'Level-2 Approver (Warden)' : 'Level-3 Approver (Chief Warden)'; ?></div>
                        <div class="name"><?php echo htmlspecialchars($w['name']); ?></div>
                        <div class="details">
                            <div><i class="fa fa-building"></i> <?php echo htmlspecialchars($w['department'] ?? 'Hostel Administration'); ?></div>
                            <div><i class="fa fa-envelope"></i> <a href="mailto:<?php echo htmlspecialchars($w['email']); ?>" style="color:#a4123f;"><?php echo htmlspecialchars($w['email']); ?></a></div>
                            <?php if ($w['phone']): ?><div><i class="fa fa-phone"></i> <?php echo htmlspecialchars($w['phone']); ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Gate Pass History -->
        <div class="card">
            <h2 class="card-title">Gate Pass History</h2>
            <?php if (empty($passes)): ?>
                <div class="empty-state"><i class="fa fa-ticket"></i><p>No gate pass requests.</p></div>
            <?php else: ?>
                <?php foreach ($passes as $gp): ?>
                <div style="background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:20px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <div>
                            <strong style="font-size:15px;"><?php echo htmlspecialchars($gp['reason']); ?></strong>
                            <span class="badge badge-<?php echo strtolower($gp['urgency']); ?>" style="margin-left:8px;"><?php echo $gp['urgency']; ?></span>
                        </div>
                        <span class="badge badge-<?php echo strtolower($gp['status']); ?>"><?php echo $gp['status']; ?></span>
                    </div>
                    <div style="font-size:13px; color:#666; margin-bottom:14px;">
                        <i class="fa fa-calendar"></i> <?php echo date('d M Y H:i', strtotime($gp['from_date'])); ?> → <?php echo date('d M Y H:i', strtotime($gp['to_date'])); ?>
                    </div>

                    <!-- 3-Level Approval Pipeline -->
                    <div class="status-pipeline">
                        <?php
                        $levels = [
                            ['label' => 'Level-1 (Advisor)', 'status' => $gp['level1_status'], 'by' => $gp['level1_by']],
                            ['label' => 'Level-2 (Warden)', 'status' => $gp['level2_status'], 'by' => $gp['level2_by']],
                            ['label' => 'Level-3 (Chief Warden)', 'status' => $gp['level3_status'], 'by' => $gp['level3_by']],
                        ];
                        foreach ($levels as $lv):
                            $cls = match($lv['status']) { 'Approved' => 'completed', 'Rejected' => 'rejected', default => '' };
                            if ($lv['status'] === 'Pending') { $cls = 'active'; }
                        ?>
                        <div class="pipeline-step <?php echo $cls; ?>" title="<?php echo $lv['by'] ? 'By: '.$lv['by'] : ''; ?>">
                            <div class="step-dot">
                                <?php if ($cls==='completed') echo '<i class="fa fa-check"></i>';
                                      elseif ($cls==='rejected') echo '<i class="fa fa-times"></i>';
                                      elseif ($cls==='active') echo '<i class="fa fa-clock-o"></i>'; ?>
                            </div>
                            <div class="step-label"><?php echo $lv['label']; ?></div>
                            <?php if ($lv['by']): ?>
                                <div style="font-size:10px; color:#888; margin-top:2px;"><?php echo htmlspecialchars($lv['by']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($gp['status'] === 'Pending'): ?>
                    <form method="POST" style="margin-top:12px;" onsubmit="return confirm('Cancel this gate pass?');">
                        <input type="hidden" name="action" value="cancel_gatepass">
                        <input type="hidden" name="gatepass_id" value="<?php echo $gp['id']; ?>">
                        <input type="text" name="cancel_reason" placeholder="Reason for cancellation" class="form-control" style="margin-bottom:8px;">
                        <button type="submit" style="background:#e74c3c; color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;"><i class="fa fa-times"></i> Cancel Request</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Request Form -->
        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Request Gate Pass</h3>
            <form method="POST">
                <input type="hidden" name="action" value="request_gatepass">
                <div class="form-row">
                    <div class="form-group">
                        <label>Urgency</label>
                        <select class="form-control" name="urgency" required>
                            <option value="Normal">Normal</option>
                            <option value="Urgent">Urgent</option>
                            <option value="Emergency">Emergency</option>
                        </select>
                    </div>
                    <div class="form-group"><label>From</label><input type="datetime-local" class="form-control" name="from_date" required></div>
                    <div class="form-group"><label>To</label><input type="datetime-local" class="form-control" name="to_date" required></div>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Reason</label>
                    <textarea class="form-control" name="reason" placeholder="Reason for gate pass..." required></textarea>
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Request</button>
            </form>
        </div>
    </div>
</body>
</html>
