<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chief_warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$cw_name = $_SESSION['user_name'];
$msg = '';

$selected_floor = $_GET['floor'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unblock') {
    $cid = intval($_POST['card_id'] ?? 0);
    if ($cid) {
        $pdo->prepare("UPDATE student_id_cards SET card_status='Active', block_reason=NULL WHERE id=?")->execute([$cid]);
        $msg = 'unblocked';
    }
}

$cw_gender_row = $pdo->prepare("SELECT hostel_gender FROM users WHERE id = ?");
$cw_gender_row->execute([$_SESSION['user_id']]);
$cw_gender = $cw_gender_row->fetchColumn() ?: 'All';
$gender_filter = ($cw_gender === 'Boys') ? 'Male' : (($cw_gender === 'Girls') ? 'Female' : '');

$sql = "SELECT ic.*, s.name, s.enrollment_no, s.department, s.hostel_room, s.hostel_block FROM student_id_cards ic JOIN students s ON ic.student_id = s.id WHERE ic.card_status = 'Blocked'";
$params = [];

if ($gender_filter) {
    $sql .= " AND s.gender = ?";
    $params[] = $gender_filter;
}

if ($selected_floor !== 'all') {
    $floor_num = intval($selected_floor);
    $floor_suffixes = ['1st','2nd','3rd','4th','5th','6th','7th','8th','9th'];
    $floor_label = ($floor_num >= 1 && $floor_num <= 9) ? $floor_suffixes[$floor_num - 1] : $floor_num.'th';
    $sql .= " AND s.hostel_room LIKE ?";
    $params[] = $floor_label . ' floor%';
    $stmt = $pdo->prepare($sql . " ORDER BY s.hostel_room");
    $stmt->execute($params);
} else {
    $stmt = $pdo->prepare($sql . " ORDER BY s.hostel_room");
    $stmt->execute($params);
}
$blocked = $stmt->fetchAll();
$boys_floors = [3,4,6,7,8]; $girls_floors = [1,2];
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Chief Warden - ID Cards</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar { background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%); }
        .floor-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
        .floor-tab { padding:6px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none; color:#666; background:#fff; }
        .floor-tab.active { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-color:#a4123f; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Chief Warden Panel</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($cw_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Chief Warden Home</a> <span class="sep">/</span> ID Cards</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-credit-card"></i> Blocked ID Cards</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
        <?php if ($msg === 'unblocked'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> ID card unblocked.</div><?php endif; ?>
        <div class="floor-tabs">
            <a href="id_cards.php?floor=all" class="floor-tab <?php echo $selected_floor==='all'?'active':''; ?>">All Floors</a>
            <?php if ($cw_gender === 'All' || $cw_gender === 'Girls'): ?>
            <?php foreach ($girls_floors as $fl): ?><a href="id_cards.php?floor=<?php echo $fl; ?>" class="floor-tab <?php echo $selected_floor==strval($fl)?'active':''; ?>">Floor <?php echo $fl; ?> (Girls)</a><?php endforeach; ?>
            <?php endif; ?>
            <?php if ($cw_gender === 'All' || $cw_gender === 'Boys'): ?>
            <?php foreach ($boys_floors as $fl): ?><a href="id_cards.php?floor=<?php echo $fl; ?>" class="floor-tab <?php echo $selected_floor==strval($fl)?'active':''; ?>">Floor <?php echo $fl; ?> (Boys)</a><?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2 class="card-title">Blocked IDs (<?php echo count($blocked); ?>)</h2>
            <?php if (empty($blocked)): ?>
                <div class="empty-state"><i class="fa fa-check-circle"></i><p>No blocked ID cards<?php echo $selected_floor!=='all'?' on this floor':''; ?>.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Room</th><th>Branch</th><th>Reason</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($blocked as $b): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($b['name']); ?></strong><br><small><?php echo htmlspecialchars($b['enrollment_no']); ?></small></td>
                        <td><?php echo htmlspecialchars($b['hostel_room']); ?></td>
                        <td><?php echo htmlspecialchars($b['department']); ?></td>
                        <td style="color:#c62828;"><?php echo htmlspecialchars($b['block_reason'] ?? '—'); ?></td>
                        <td>
                            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="unblock"><input type="hidden" name="card_id" value="<?php echo $b['id']; ?>">
                                <button type="submit" style="background:linear-gradient(135deg,#27ae60,#2ecc71); color:#fff; border:none; padding:5px 12px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer;"><i class="fa fa-unlock"></i> Unblock</button>
                            </form>
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
