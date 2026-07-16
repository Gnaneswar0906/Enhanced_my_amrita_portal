<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$stmt = $pdo->prepare('SELECT ic.*, s.name, s.enrollment_no, s.department, s.hostel_block, s.hostel_room FROM student_id_cards ic JOIN students s ON ic.student_id = s.id WHERE ic.student_id = ?');
$stmt->execute([$student_id]);
$id_card = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - ID Card</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> ID Card</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-credit-card"></i> Student ID Card</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($id_card): ?>
        <div class="card" style="text-align:center; padding:40px;">
            <div style="max-width:400px; margin:0 auto; background:linear-gradient(135deg,#a4123f, #c2185b); border-radius:16px; padding:30px; color:#fff; box-shadow:0 8px 30px rgba(164,18,63,0.3);">
                <div style="font-size:18px; font-weight:700; margin-bottom:4px;">AMRITA VISHWA VIDYAPEETHAM</div>
                <div style="font-size:11px; opacity:0.8; margin-bottom:20px;">Student Identity Card</div>
                <div style="width:80px; height:80px; background:rgba(255,255,255,0.2); border-radius:50%; margin:0 auto 16px; display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:700;"><?php echo strtoupper(substr($id_card['name'], 0, 1)); ?></div>
                <div style="font-size:18px; font-weight:600; margin-bottom:4px;"><?php echo htmlspecialchars($id_card['name']); ?></div>
                <div style="font-size:13px; opacity:0.9; margin-bottom:16px;"><?php echo htmlspecialchars($id_card['enrollment_no']); ?></div>
                <div style="display:flex; justify-content:center; gap:20px; font-size:12px; opacity:0.85;">
                    <span><i class="fa fa-building"></i> <?php echo htmlspecialchars($id_card['department']); ?></span>
                </div>
                <?php if ($id_card['hostel_room']): ?>
                <div style="font-size:12px; opacity:0.85; margin-top:8px;"><i class="fa fa-home"></i> <?php echo htmlspecialchars($id_card['hostel_room']); ?></div>
                <?php endif; ?>
            </div>

            <div style="margin-top:24px;">
                <?php
                $status = $id_card['card_status'];
                $statusIcon = $status === 'Active' ? 'fa-check-circle' : 'fa-ban';
                $statusColor = $status === 'Active' ? '#27ae60' : '#e74c3c';
                ?>
                <span style="font-size:16px; font-weight:600;">
                    <i class="fa <?php echo $statusIcon; ?>" style="color:<?php echo $statusColor; ?>;"></i>
                    Status: <span class="badge <?php echo $status==='Active'?'badge-approved':'badge-failed'; ?>" style="font-size:14px;"><?php echo $status; ?></span>
                </span>

                <?php if ($status === 'Blocked'): ?>
                    <div style="margin-top:16px; padding:18px; background:#fde8e8; border-radius:12px; border:1px solid #f5c6c6; text-align:left;">
                        <h4 style="margin:0 0 10px; color:#721c24; font-size:15px;"><i class="fa fa-exclamation-triangle"></i> ID Card Blocked</h4>
                        <?php if ($id_card['block_reason']): ?>
                            <div style="font-size:13px; color:#721c24; margin-bottom:8px;">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($id_card['block_reason']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($id_card['blocked_by']): ?>
                            <div style="font-size:13px; color:#721c24; margin-bottom:8px;">
                                <strong>Blocked By:</strong> <?php echo htmlspecialchars($id_card['blocked_by']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($id_card['blocked_days'] > 0): ?>
                            <div style="font-size:13px; color:#721c24; margin-bottom:8px;">
                                <strong>Duration:</strong> <?php echo $id_card['blocked_days']; ?> day(s) (since <?php echo date('d M Y', strtotime($id_card['blocked_since'])); ?>)
                            </div>
                        <?php endif; ?>
                        <?php if ($id_card['unblock_date']): ?>
                            <div style="margin-top:10px; padding:10px 14px; background:#fff3cd; border-radius:8px; border:1px solid #ffeeba; font-size:13px; color:#856404;">
                                <i class="fa fa-clock-o"></i> <strong>Will be unblocked on:</strong> <?php echo date('d M Y, h:i A', strtotime($id_card['unblock_date'])); ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-top:10px; padding:10px 14px; background:#fde8e8; border-radius:8px; font-size:12px; color:#721c24;">
                                <i class="fa fa-exclamation-circle"></i> Contact the administration for unblocking.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card"><div class="empty-state"><i class="fa fa-credit-card"></i><p>No ID card record found. Please contact the administration.</p></div></div>
        <?php endif; ?>
    </div>
</body>
</html>
