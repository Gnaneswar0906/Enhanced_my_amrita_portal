<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rid = intval($_POST['reg_id'] ?? 0);
    if ($action === 'approve' && $rid) {
        $stmt = $pdo->prepare('SELECT * FROM student_registrations WHERE id=? AND status="Pending"');
        $stmt->execute([$rid]);
        $reg = $stmt->fetch();
        if ($reg) {
            try {
                $pdo->beginTransaction();
                $enroll = 'BL.EN.U4CSE' . str_pad($rid + 23100, 5, '0', STR_PAD_LEFT);
                $uname = $reg['preferred_username'];
                $hash = password_hash('student', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO students (enrollment_no, username, password, name, email, phone, department, semester, dob, address) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$enroll, $uname, $hash, $reg['name'], $reg['email'], $reg['phone'], $reg['department'], $reg['semester'], $reg['dob'], $reg['address']]);
                $sid = $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO users (username, password, role, name, email, linked_student_id) VALUES (?,?,"student",?,?,?)')->execute([$uname, $hash, $reg['name'], $reg['email'], $sid]);
                $pdo->prepare('INSERT INTO student_id_cards (student_id) VALUES (?)')->execute([$sid]);
                $pdo->prepare('UPDATE student_registrations SET status="Approved" WHERE id=?')->execute([$rid]);
                $pdo->commit();
                $msg = 'approved';
            } catch(Exception $e) { $pdo->rollBack(); $msg = 'error'; }
        }
    } elseif ($action === 'reject' && $rid) {
        $reason = trim($_POST['reject_reason'] ?? '');
        $pdo->prepare('UPDATE student_registrations SET status="Rejected", reject_reason=? WHERE id=?')->execute([$reason, $rid]);
        $msg = 'rejected';
    }
}

$regs = $pdo->query('SELECT * FROM student_registrations ORDER BY FIELD(status, "Pending", "Approved", "Rejected"), created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Admin - Student Registrations</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>.action-btn{display:inline-block;padding:6px 14px;border-radius:6px;font-size:11px;font-weight:600;border:none;cursor:pointer;transition:all .2s;font-family:'Inter',sans-serif;} .approve-btn{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;} .reject-btn{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;} .action-btn:hover{transform:translateY(-1px);}</style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Admin Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Student Registrations</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-pencil-square-o"></i> Student Registrations</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a>
        </div>

        <?php if ($msg === 'approved'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Student approved and account created! (Default password: student)</div>
        <?php elseif ($msg === 'rejected'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Registration rejected.</div>
        <?php elseif ($msg === 'error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Error processing registration.</div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">All Registrations (<?php echo count($regs); ?>)</h2>
            <?php if (empty($regs)): ?><div class="empty-state"><i class="fa fa-pencil-square-o"></i><p>No registrations.</p></div>
            <?php else: ?>
                <?php foreach ($regs as $r): ?>
                <div style="background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:20px; margin-bottom:16px; <?php echo $r['status']==='Pending' ? 'border-left:4px solid #f5a623;' : ''; ?>">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <div>
                            <strong style="font-size:16px;"><?php echo htmlspecialchars($r['name']); ?></strong>
                            <span style="color:#888; font-size:12px; margin-left:10px;">@<?php echo htmlspecialchars($r['preferred_username']); ?></span>
                        </div>
                        <span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo $r['status']; ?></span>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; font-size:13px; color:#555; margin-bottom:12px;">
                        <div><i class="fa fa-envelope" style="color:#a4123f;"></i> <?php echo htmlspecialchars($r['email'] ?? '—'); ?></div>
                        <div><i class="fa fa-phone" style="color:#a4123f;"></i> <?php echo htmlspecialchars($r['phone'] ?? '—'); ?></div>
                        <div><i class="fa fa-building" style="color:#a4123f;"></i> <?php echo htmlspecialchars($r['department'] ?? '—'); ?> (Sem <?php echo $r['semester']; ?>)</div>
                    </div>
                    <div style="font-size:12px; color:#888; margin-bottom:12px;">Applied: <?php echo date('d M Y H:i', strtotime($r['created_at'])); ?></div>
                    <?php if ($r['status'] === 'Pending'): ?>
                    <div style="display:flex; gap:10px;">
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="approve"><input type="hidden" name="reg_id" value="<?php echo $r['id']; ?>"><button type="submit" class="action-btn approve-btn"><i class="fa fa-check"></i> Approve</button></form>
                        <form method="POST" style="display:inline;" onsubmit="var r=prompt('Rejection reason:'); if(!r) return false; this.querySelector('[name=reject_reason]').value=r;"><input type="hidden" name="action" value="reject"><input type="hidden" name="reg_id" value="<?php echo $r['id']; ?>"><input type="hidden" name="reject_reason"><button type="submit" class="action-btn reject-btn"><i class="fa fa-times"></i> Reject</button></form>
                    </div>
                    <?php elseif ($r['status'] === 'Rejected' && $r['reject_reason']): ?>
                    <div style="padding:8px 12px; background:#fde8e8; border-radius:6px; font-size:12px; color:#721c24;"><i class="fa fa-info-circle"></i> Reason: <?php echo htmlspecialchars($r['reject_reason']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
