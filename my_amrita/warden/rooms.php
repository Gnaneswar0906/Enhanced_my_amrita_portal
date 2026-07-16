<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$warden_name = $_SESSION['user_name'];

$selected_room = $_GET['room'] ?? '';

// Only show rooms assigned to THIS warden
$my_rooms = $pdo->prepare("SELECT * FROM hostel_rooms WHERE warden_name = ? ORDER BY floor, room_number");
$my_rooms->execute([$warden_name]);
$room_list = $my_rooms->fetchAll();

// Determine gender from rooms
$my_gender = 'Boys';
if (!empty($room_list)) $my_gender = $room_list[0]['gender'] ?? 'Boys';

// Get students in selected room (only this warden's students)
$room_students = [];
if ($selected_room) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE hostel_room LIKE ? AND warden_name = ? ORDER BY enrollment_no");
    $stmt->execute(['%' . $selected_room, $warden_name]);
    $room_students = $stmt->fetchAll();
}

// Stats
$total_students = $pdo->prepare("SELECT COUNT(*) FROM students WHERE warden_name = ? AND residence_type = 'Hostler'");
$total_students->execute([$warden_name]);
$my_student_count = $total_students->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Warden - Rooms</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar{background:linear-gradient(135deg,#a4123f,#c2185b);}
        .room-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:10px;margin-top:12px;}
        .room-btn{padding:14px 8px;border:1px solid #e0e0e0;border-radius:10px;text-align:center;font-size:14px;font-weight:700;text-decoration:none;color:#333;background:#fff;transition:all .2s;}
        .room-btn:hover{background:#fdf5f7;border-color:#a4123f;transform:translateY(-2px);box-shadow:0 4px 12px rgba(164,18,63,.1);}
        .room-btn.active{background:linear-gradient(135deg,#a4123f,#d4264f);color:#fff;}
        .room-btn .room-count{font-size:11px;color:#888;display:block;margin-top:3px;}.room-btn.active .room-count{color:rgba(255,255,255,.8);}
        .room-btn.full{border-left:3px solid #27ae60;}.room-btn.partial{border-left:3px solid #f39c12;}
        .student-card{background:#fafafa;border:1px solid #e8e8e8;border-radius:10px;padding:16px;margin-bottom:10px;transition:all .2s;}
        .student-card:hover{border-color:#a4123f;box-shadow:0 2px 8px rgba(164,18,63,.08);}
        .gender-badge{padding:4px 12px;border-radius:6px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;}
        .gender-badge.boys{background:#e3f2fd;color:#1565c0;}.gender-badge.girls{background:#fce4ec;color:#c2185b;}
        .stat-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:20px;}
        .stat-box{background:#fff;border:1px solid #e8e8e8;border-radius:10px;padding:14px;text-align:center;}
        .stat-box .stat-num{font-size:24px;font-weight:700;color:#a4123f;}.stat-box .stat-label{font-size:11px;color:#888;text-transform:uppercase;font-weight:600;}
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Warden Panel</span><div class="nav-links"><span style="font-size:13px;opacity:.9;"><?php echo htmlspecialchars($warden_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Warden Home</a> <span class="sep">/</span> My Rooms</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-building"></i> My Hostel Rooms</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Warden Home</a>
        </div>

        <!-- Stats -->
        <div class="stat-row">
            <div class="stat-box"><div class="stat-num"><?php echo $my_student_count; ?></div><div class="stat-label">My Students</div></div>
            <div class="stat-box"><div class="stat-num"><?php echo count($room_list); ?></div><div class="stat-label">My Rooms</div></div>
            <div class="stat-box"><span class="gender-badge <?php echo strtolower($my_gender); ?>"><i class="fa fa-<?php echo $my_gender==='Girls'?'female':'male'; ?>"></i> <?php echo $my_gender; ?></span><div class="stat-label" style="margin-top:6px;">Hostel Type</div></div>
        </div>

        <!-- Room Grid -->
        <div class="card">
            <h2 class="card-title"><i class="fa fa-th" style="color:#a4123f;"></i> My Rooms — <?php echo $my_gender; ?> Hostel</h2>
            <?php if (empty($room_list)): ?>
                <div class="empty-state"><i class="fa fa-building"></i><p>No rooms assigned to you.</p></div>
            <?php else: ?>
                <?php
                $grouped = [];
                foreach ($room_list as $r) { $grouped[$r['floor']][] = $r; }
                foreach ($grouped as $fl => $rms):
                ?>
                <h4 style="margin:14px 0 8px;font-size:13px;color:#666;">
                    <i class="fa fa-<?php echo $my_gender==='Girls'?'female':'male'; ?>" style="color:<?php echo $my_gender==='Girls'?'#c2185b':'#1565c0'; ?>;"></i>
                    Floor <?php echo $fl; ?>
                </h4>
                <div class="room-grid">
                    <?php foreach ($rms as $rm):
                        $cnt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE hostel_room LIKE ? AND warden_name = ?");
                        $cnt->execute(['%'.$rm['room_number'], $warden_name]);
                        $count = $cnt->fetchColumn();
                        $full_class = $count >= 4 ? 'full' : ($count > 0 ? 'partial' : '');
                    ?>
                    <a href="rooms.php?room=<?php echo $rm['room_number']; ?>" class="room-btn <?php echo $selected_room===$rm['room_number']?'active':''; ?> <?php echo $full_class; ?>">
                        <?php echo $rm['room_number']; ?>
                        <span class="room-count"><?php echo $count; ?>/4</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Students in Selected Room -->
        <?php if ($selected_room): ?>
        <div class="card">
            <h2 class="card-title"><i class="fa fa-users"></i> Room <?php echo htmlspecialchars($selected_room); ?> — <?php echo count($room_students); ?> Students</h2>
            <?php if (empty($room_students)): ?>
                <div class="empty-state"><i class="fa fa-user"></i><p>No students in this room.</p></div>
            <?php else: ?>
                <?php foreach ($room_students as $si => $s): ?>
                <div class="student-card">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                        <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Name</div><strong style="font-size:14px;"><?php echo htmlspecialchars($s['name']); ?></strong></div>
                        <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Reg No</div><span style="font-size:13px;color:#a4123f;font-weight:600;"><?php echo htmlspecialchars($s['enrollment_no']); ?></span></div>
                        <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Department</div><span style="font-size:13px;"><?php echo htmlspecialchars($s['department'] ?? 'CSE'); ?></span></div>
                        <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Phone</div><span style="font-size:13px;"><?php echo htmlspecialchars($s['phone'] ?? '—'); ?></span></div>
                        <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Email</div><span style="font-size:13px;"><?php echo htmlspecialchars($s['email'] ?? '—'); ?></span></div>
                        <div><div style="font-size:10px;color:#888;text-transform:uppercase;font-weight:600;">Gender</div><span class="gender-badge <?php echo strtolower($s['gender'])==='female'?'girls':'boys'; ?>"><i class="fa fa-<?php echo strtolower($s['gender'])==='female'?'female':'male'; ?>"></i> <?php echo $s['gender']; ?></span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
