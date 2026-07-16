<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$warden_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sid = intval($_POST['student_id'] ?? 0);
    if ($action === 'block_id' && $sid) {
        $reason = trim($_POST['block_reason'] ?? '');
        $days   = intval($_POST['blocked_days'] ?? 0);
        $pdo->prepare('UPDATE student_id_cards SET card_status="Blocked", block_reason=?, blocked_since=CURDATE(), blocked_days=?, unblock_date=DATE_ADD(CURDATE(), INTERVAL ? DAY), blocked_by=? WHERE student_id=?')->execute([$reason, $days, $days, $warden_name . ' (Warden)', $sid]);
        $msg = 'blocked';
    } elseif ($action === 'unblock_id' && $sid) {
        $pdo->prepare('UPDATE student_id_cards SET card_status="Active", block_reason=NULL, blocked_since=NULL, blocked_days=0 WHERE student_id=?')->execute([$sid]);
        $msg = 'unblocked';
    }
}

$stmt = $pdo->prepare('SELECT ic.*, s.name, s.enrollment_no, s.hostel_block, s.hostel_room FROM student_id_cards ic JOIN students s ON ic.student_id = s.id WHERE s.warden_name = ? ORDER BY ic.card_status DESC, s.name ASC');
$stmt->execute([$warden_name]);
$cards = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Warden - ID Card Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>.action-btn{display:inline-block;padding:5px 12px;border-radius:6px;font-size:11px;font-weight:600;border:none;cursor:pointer;transition:all .2s;font-family:'Inter',sans-serif;} .block-btn{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;} .unblock-btn{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;} .action-btn:hover{transform:translateY(-1px);}</style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Warden Portal</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($warden_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Warden Home</a> <span class="sep">/</span> ID Card Management</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-credit-card"></i> ID Card Management</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>

        <?php if ($msg === 'blocked'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> ID card blocked.</div>
        <?php elseif ($msg === 'unblocked'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> ID card unblocked.</div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">All Student IDs (<?php echo count($cards); ?>)</h2>
            <table class="data-table">
                <thead><tr><th>#</th><th>Student</th><th>Block / Room</th><th>Status</th><th>Block Reason</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($cards as $i => $c): ?>
                    <tr>
                        <td><?php echo $i+1; ?></td>
                        <td><strong><?php echo htmlspecialchars($c['name']); ?></strong><br><small><?php echo htmlspecialchars($c['enrollment_no']); ?></small></td>
                        <td><?php echo htmlspecialchars($c['hostel_room'] ?? '—'); ?></td>
                        <td>
                            <?php $cls = $c['card_status']==='Active' ? 'badge-approved' : 'badge-failed'; ?>
                            <span class="badge <?php echo $cls; ?>"><?php echo $c['card_status']; ?></span>
                        </td>
                        <td style="max-width:200px; font-size:12px;"><?php echo htmlspecialchars($c['block_reason'] ?? '—'); ?></td>
                        <td>
                            <?php if ($c['card_status'] === 'Active'): ?>
                                <button class="action-btn block-btn" onclick="blockId(<?php echo $c['student_id']; ?>, '<?php echo htmlspecialchars($c['name']); ?>')"><i class="fa fa-ban"></i> Block</button>
                            <?php else: ?>
                                <form method="POST" style="display:inline;"><input type="hidden" name="action" value="unblock_id"><input type="hidden" name="student_id" value="<?php echo $c['student_id']; ?>"><button type="submit" class="action-btn unblock-btn"><i class="fa fa-check"></i> Unblock</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Block Modal -->
    <div id="blockModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; padding:32px; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <h3 style="margin:0 0 16px; color:#c0392b;"><i class="fa fa-ban"></i> Block ID Card</h3>
            <p style="font-size:13px; color:#888;" id="blockStudentName"></p>
            <form method="POST">
                <input type="hidden" name="action" value="block_id">
                <input type="hidden" name="student_id" id="blockStudentId">
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-size:13px; font-weight:600; color:#333;">Reason</label>
                    <textarea class="form-control" name="block_reason" placeholder="Reason for blocking..." required></textarea>
                </div>
                <div class="form-group" style="margin-bottom:18px;">
                    <label style="font-size:13px; font-weight:600; color:#333;">Block Duration (days)</label>
                    <input type="number" class="form-control" name="blocked_days" value="7" min="1" max="365">
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="action-btn block-btn" style="padding:10px 20px; font-size:13px;"><i class="fa fa-ban"></i> Block ID</button>
                    <button type="button" onclick="document.getElementById('blockModal').style.display='none'" style="padding:10px 20px; background:#eee; border:none; border-radius:6px; font-size:13px; cursor:pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function blockId(sid, name) {
        document.getElementById('blockStudentId').value = sid;
        document.getElementById('blockStudentName').textContent = 'Blocking ID for: ' + name;
        document.getElementById('blockModal').style.display = 'flex';
    }
    </script>
</body>
</html>
