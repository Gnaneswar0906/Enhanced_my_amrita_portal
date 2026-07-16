<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chief_warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$cw_name = $_SESSION['user_name'];

$selected_floor = $_GET['floor'] ?? 'all';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$boys_floors = [3,4,6,7,8]; $girls_floors = [1,2];

$cw_gender_row = $pdo->prepare("SELECT hostel_gender FROM users WHERE id = ?");
$cw_gender_row->execute([$_SESSION['user_id']]);
$cw_gender = $cw_gender_row->fetchColumn() ?: 'All';
$gender_filter = ($cw_gender === 'Boys') ? 'Male' : (($cw_gender === 'Girls') ? 'Female' : '');

// Students who didn't give hostel attendance
$sql = "SELECT s.id, s.name, s.enrollment_no, s.department, s.hostel_room, s.hostel_block FROM students s WHERE s.hostel_room IS NOT NULL AND s.id NOT IN (SELECT ha.student_id FROM hostel_attendance ha WHERE ha.attendance_date = ? AND ha.status IN ('Present','Late'))";
$params = [$selected_date];

if ($gender_filter) {
    $sql .= " AND s.gender = ?";
    $params[] = $gender_filter;
}

if ($selected_floor !== 'all') {
    $fl = intval($selected_floor);
    $floor_suffixes = ['1st','2nd','3rd','4th','5th','6th','7th','8th','9th'];
    $floor_label = ($fl >= 1 && $fl <= 9) ? $floor_suffixes[$fl - 1] : $fl.'th';
    $sql .= " AND s.hostel_room LIKE ?";
    $params[] = $floor_label . ' floor%';
}
$sql .= " ORDER BY s.hostel_room";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$absent = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Chief Warden - Hostel Attendance</title>
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
    <div class="breadcrumb-bar"><a href="home.php">Chief Warden Home</a> <span class="sep">/</span> Hostel Attendance</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar-times-o"></i> Hostel Attendance – Absent Students</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
        <div style="display:flex; gap:10px; align-items:center; margin-bottom:14px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px;">
            <label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Date:</label>
            <input type="date" id="selDate" value="<?php echo $selected_date; ?>" onchange="window.location.href='hostel_attendance.php?floor=<?php echo $selected_floor; ?>&date='+this.value" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
            <span style="padding:4px 12px; background:#fde8e8; border-radius:6px; font-size:12px; font-weight:600; color:#c62828;"><?php echo count($absent); ?> absent</span>
        </div>
        <div class="floor-tabs">
            <a href="hostel_attendance.php?floor=all&date=<?php echo $selected_date; ?>" class="floor-tab <?php echo $selected_floor==='all'?'active':''; ?>">All</a>
            <?php if ($cw_gender === 'All' || $cw_gender === 'Girls'): ?>
            <?php foreach ($girls_floors as $fl): ?><a href="hostel_attendance.php?floor=<?php echo $fl; ?>&date=<?php echo $selected_date; ?>" class="floor-tab <?php echo $selected_floor==strval($fl)?'active':''; ?>">Floor <?php echo $fl; ?> (Girls)</a><?php endforeach; ?>
            <?php endif; ?>
            <?php if ($cw_gender === 'All' || $cw_gender === 'Boys'): ?>
            <?php foreach ($boys_floors as $fl): ?><a href="hostel_attendance.php?floor=<?php echo $fl; ?>&date=<?php echo $selected_date; ?>" class="floor-tab <?php echo $selected_floor==strval($fl)?'active':''; ?>">Floor <?php echo $fl; ?> (Boys)</a><?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2 class="card-title">Absent on <?php echo date('d M Y (l)', strtotime($selected_date)); ?></h2>
            <?php if (empty($absent)): ?>
                <div class="empty-state"><i class="fa fa-check-circle" style="color:#27ae60;"></i><p>All students attended hostel check-in<?php echo $selected_floor!=='all'?' on this floor':''; ?>!</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Student</th><th>Room</th><th>Branch</th></tr></thead>
                    <tbody>
                    <?php foreach ($absent as $i => $a): ?>
                    <tr style="background:#fde8e8;">
                        <td><?php echo $i+1; ?></td>
                        <td><strong><?php echo htmlspecialchars($a['name']); ?></strong><br><small><?php echo htmlspecialchars($a['enrollment_no']); ?></small></td>
                        <td><strong style="color:#a4123f;"><?php echo htmlspecialchars($a['hostel_room']); ?></strong></td>
                        <td><?php echo htmlspecialchars($a['department']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
