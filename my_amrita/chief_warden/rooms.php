<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chief_warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$cw_name = $_SESSION['user_name'];
$cw_id = $_SESSION['user_id'];

// Get chief warden's gender assignment
$cw_gender_row = $pdo->prepare("SELECT hostel_gender FROM users WHERE id = ?");
$cw_gender_row->execute([$cw_id]);
$cw_gender = $cw_gender_row->fetchColumn() ?: 'All';

$selected_floor = $_GET['floor'] ?? 'all';
$selected_room = $_GET['room'] ?? '';

// Show only rooms matching this chief warden's gender
if ($cw_gender !== 'All') {
    if ($selected_floor !== 'all') {
        $rooms = $pdo->prepare("SELECT * FROM hostel_rooms WHERE floor = ? AND gender = ? ORDER BY room_number");
        $rooms->execute([intval($selected_floor), $cw_gender]);
    } else {
        $rooms = $pdo->prepare("SELECT * FROM hostel_rooms WHERE gender = ? ORDER BY floor, room_number");
        $rooms->execute([$cw_gender]);
    }
} else {
    if ($selected_floor !== 'all') {
        $rooms = $pdo->prepare("SELECT * FROM hostel_rooms WHERE floor = ? ORDER BY room_number");
        $rooms->execute([intval($selected_floor)]);
    } else {
        $rooms = $pdo->query("SELECT * FROM hostel_rooms ORDER BY floor, room_number");
    }
}
$room_list = $rooms->fetchAll();

// Students in selected room
$room_students = [];
if ($selected_room) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE hostel_room LIKE ? AND residence_type='Hostler' ORDER BY enrollment_no");
    $stmt->execute(['%' . $selected_room]);
    $room_students = $stmt->fetchAll();
}

// Stats
$gender_filter = ($cw_gender !== 'All') ? $cw_gender : '';
$student_gender = $cw_gender === 'Boys' ? 'Male' : ($cw_gender === 'Girls' ? 'Female' : '');
if ($student_gender) {
    $tc = $pdo->prepare("SELECT COUNT(*) FROM students WHERE gender=? AND residence_type='Hostler'");
    $tc->execute([$student_gender]); $total_count = $tc->fetchColumn();
} else {
    $total_count = $pdo->query("SELECT COUNT(*) FROM students WHERE residence_type='Hostler'")->fetchColumn();
}

// Get floors
$floors = [];
foreach ($room_list as $r) $floors[$r['floor']] = $r['gender'];
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Chief Warden - Rooms</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar{background:linear-gradient(135deg,#a4123f,#c2185b);}
        .floor-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;}
        .floor-tab{padding:8px 16px;border:1px solid #e0e0e0;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;color:#666;background:#fff;transition:.2s;}
        .floor-tab:hover{border-color:#a4123f;color:#a4123f;}.floor-tab.active{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;border-color:#a4123f;}
        .floor-tab.boys{border-left:3px solid #1565c0;}.floor-tab.girls{border-left:3px solid #e91e63;}
        .room-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(85px,1fr));gap:8px;}
        .room-btn{padding:14px 8px;border:1px solid #e0e0e0;border-radius:10px;text-align:center;font-size:14px;font-weight:700;text-decoration:none;color:#333;background:#fff;transition:.2s;}
        .room-btn:hover{background:#fdf5f7;border-color:#a4123f;transform:translateY(-2px);}
        .room-btn.active{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;}
        .room-btn .room-count{font-size:11px;color:#888;display:block;margin-top:3px;}.room-btn.active .room-count{color:rgba(255,255,255,.8);}
        .stat-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:20px;}
        .stat-box{background:#fff;border:1px solid #e8e8e8;border-radius:10px;padding:14px;text-align:center;}
        .stat-box .stat-num{font-size:24px;font-weight:700;color:#a4123f;}.stat-box .stat-label{font-size:11px;color:#888;text-transform:uppercase;font-weight:600;}
        .gender-badge{padding:4px 12px;border-radius:6px;font-size:12px;font-weight:700;}
        .gender-badge.boys{background:#e3f2fd;color:#1565c0;}.gender-badge.girls{background:#fce4ec;color:#c2185b;}
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Chief Warden Panel</span><div class="nav-links"><span style="font-size:13px;opacity:.9;"><?php echo htmlspecialchars($cw_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Chief Warden Home</a> <span class="sep">/</span> Rooms</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-building"></i> Hostel Rooms — <?php echo $cw_gender !== 'All' ? $cw_gender : 'All'; ?></h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>

        <div class="stat-row">
            <div class="stat-box"><div class="stat-num"><?php echo $total_count; ?></div><div class="stat-label">Total Students</div></div>
            <div class="stat-box"><div class="stat-num"><?php echo count($room_list); ?></div><div class="stat-label">Total Rooms</div></div>
            <div class="stat-box"><div class="stat-num"><?php echo count($floors); ?></div><div class="stat-label">Floors</div></div>
            <div class="stat-box"><span class="gender-badge <?php echo strtolower($cw_gender); ?>"><?php echo $cw_gender; ?> Hostel</span><div class="stat-label" style="margin-top:6px;">Type</div></div>
        </div>

        <div class="floor-tabs">
            <a href="rooms.php?floor=all" class="floor-tab <?php echo $selected_floor==='all'?'active':''; ?>">All Floors</a>
            <?php foreach ($floors as $fl => $gen): $g_class = $gen === 'Girls' ? 'girls' : 'boys'; ?>
            <a href="rooms.php?floor=<?php echo $fl; ?>" class="floor-tab <?php echo $g_class; ?> <?php echo $selected_floor==strval($fl)?'active':''; ?>">Floor <?php echo $fl; ?> (<?php echo $gen; ?>)</a>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2 class="card-title"><?php echo $selected_floor==='all'?'All Rooms':'Floor '.$selected_floor.' Rooms'; ?></h2>
            <?php
            $grouped = [];
            foreach ($room_list as $r) { $grouped[$r['floor']][] = $r; }
            foreach ($grouped as $fl => $rms): $gender = $rms[0]['gender'] ?? 'Boys';
            ?>
            <h4 style="margin:14px 0 8px;font-size:13px;color:#666;">
                <i class="fa fa-<?php echo $gender==='Girls'?'female':'male'; ?>" style="color:<?php echo $gender==='Girls'?'#e91e63':'#1565c0'; ?>;"></i>
                Floor <?php echo $fl; ?> — <?php echo $gender; ?>
            </h4>
            <div class="room-grid" style="margin-bottom:16px;">
                <?php foreach ($rms as $rm):
                    $cnt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE hostel_room LIKE ?"); $cnt->execute(['%'.$rm['room_number']]); $count = $cnt->fetchColumn();
                ?>
                <a href="rooms.php?floor=<?php echo $fl; ?>&room=<?php echo $rm['room_number']; ?>" class="room-btn <?php echo $selected_room===$rm['room_number']?'active':''; ?>">
                    <?php echo $rm['room_number']; ?><span class="room-count"><?php echo $count; ?>/4</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($selected_room && !empty($room_students)): ?>
        <div class="card">
            <h2 class="card-title"><i class="fa fa-users"></i> Room <?php echo htmlspecialchars($selected_room); ?> (<?php echo count($room_students); ?>)</h2>
            <?php foreach ($room_students as $s): ?>
            <div style="background:#fafafa;border:1px solid #e8e8e8;border-radius:10px;padding:16px;margin-bottom:10px;">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                    <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Name</div><strong style="font-size:14px;"><?php echo htmlspecialchars($s['name']); ?></strong></div>
                    <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Reg No</div><span style="font-size:13px;color:#a4123f;font-weight:600;"><?php echo htmlspecialchars($s['enrollment_no']); ?></span></div>
                    <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Gender</div><span class="gender-badge <?php echo strtolower($s['gender'])==='female'?'girls':'boys'; ?>"><?php echo $s['gender']; ?></span></div>
                    <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Warden</div><span style="font-size:13px;"><?php echo htmlspecialchars($s['warden_name'] ?? '—'); ?></span></div>
                    <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Phone</div><span style="font-size:13px;"><?php echo htmlspecialchars($s['phone'] ?? '—'); ?></span></div>
                    <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Email</div><span style="font-size:13px;"><?php echo htmlspecialchars($s['email'] ?? '—'); ?></span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
