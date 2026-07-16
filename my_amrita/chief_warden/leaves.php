<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chief_warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$cw_name = $_SESSION['user_name'];
$selected_floor = $_GET['floor'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');
$boys_floors = [3,4,6,7,8]; $girls_floors = [1,2];
$months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'];

$cw_gender_row = $pdo->prepare("SELECT hostel_gender FROM users WHERE id = ?");
$cw_gender_row->execute([$_SESSION['user_id']]);
$cw_gender = $cw_gender_row->fetchColumn() ?: 'All';
$gender_filter = ($cw_gender === 'Boys') ? 'Male' : (($cw_gender === 'Girls') ? 'Female' : '');

$sql = "SELECT s.id, s.name, s.enrollment_no, s.hostel_room, s.hostel_block,
    (SELECT COUNT(*) FROM gate_passes g WHERE g.student_id = s.id AND g.pass_type = 'Gate Pass' AND MONTH(g.created_at) = ? AND YEAR(g.created_at) = ?) as gate_count,
    (SELECT COUNT(*) FROM gate_passes g WHERE g.student_id = s.id AND g.pass_type = 'Home Pass' AND MONTH(g.created_at) = ? AND YEAR(g.created_at) = ?) as home_count,
    (SELECT COUNT(*) FROM gate_passes g WHERE g.student_id = s.id AND MONTH(g.created_at) = ? AND YEAR(g.created_at) = ?) as total_count
    FROM students s WHERE s.hostel_room IS NOT NULL";
$params = [$selected_month, $selected_year, $selected_month, $selected_year, $selected_month, $selected_year];

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
if ($search) { $sql .= " AND (s.name LIKE ? OR s.enrollment_no LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY total_count DESC, s.name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Chief Warden - Leaves</title>
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
    <div class="breadcrumb-bar"><a href="home.php">Chief Warden Home</a> <span class="sep">/</span> Leaves & Passes</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar"></i> Monthly Pass Summary</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:14px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px;">
            <label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Month:</label>
            <select onchange="applyFilter()" id="selMonth"><?php foreach ($months as $mv => $mn): ?><option value="<?php echo $mv; ?>" <?php echo $mv==$selected_month?'selected':''; ?>><?php echo $mn; ?></option><?php endforeach; ?></select>
            <label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Year:</label>
            <select onchange="applyFilter()" id="selYear"><option value="2025" <?php echo $selected_year=='2025'?'selected':''; ?>>2025</option><option value="2026" <?php echo $selected_year=='2026'?'selected':''; ?>>2026</option></select>
            <input type="text" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search..." onkeydown="if(event.key==='Enter')applyFilter()" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
            <button onclick="applyFilter()" style="padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;"><i class="fa fa-search"></i></button>
        </div>
        <div class="floor-tabs">
            <a href="javascript:void(0)" onclick="document.getElementById('selFloor').value='all';applyFilter()" class="floor-tab <?php echo $selected_floor==='all'?'active':''; ?>">All</a>
            <?php if ($cw_gender === 'All' || $cw_gender === 'Girls'): ?>
            <?php foreach ($girls_floors as $fl): ?><a href="javascript:void(0)" onclick="document.getElementById('selFloor').value='<?php echo $fl; ?>';applyFilter()" class="floor-tab <?php echo $selected_floor==strval($fl)?'active':''; ?>">Floor <?php echo $fl; ?> (Girls)</a><?php endforeach; ?>
            <?php endif; ?>
            <?php if ($cw_gender === 'All' || $cw_gender === 'Boys'): ?>
            <?php foreach ($boys_floors as $fl): ?><a href="javascript:void(0)" onclick="document.getElementById('selFloor').value='<?php echo $fl; ?>';applyFilter()" class="floor-tab <?php echo $selected_floor==strval($fl)?'active':''; ?>">Floor <?php echo $fl; ?> (Boys)</a><?php endforeach; ?>
            <?php endif; ?>
        </div>
        <input type="hidden" id="selFloor" value="<?php echo $selected_floor; ?>">
        <div class="card">
            <h2 class="card-title"><?php echo $months[$selected_month]; ?> <?php echo $selected_year; ?> – Pass Count</h2>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Room</th><th>Gate Passes</th><th>Home Passes</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): ?>
                <tr><td><strong><?php echo htmlspecialchars($s['name']); ?></strong><br><small><?php echo htmlspecialchars($s['enrollment_no']); ?></small></td>
                    <td><?php echo htmlspecialchars($s['hostel_room']); ?></td>
                    <td style="text-align:center;"><strong style="color:#1565c0;"><?php echo $s['gate_count']; ?></strong></td>
                    <td style="text-align:center;"><strong style="color:#e65100;"><?php echo $s['home_count']; ?></strong></td>
                    <td style="text-align:center;"><strong style="color:#a4123f;"><?php echo $s['total_count']; ?></strong></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    function applyFilter() {
        var m = document.getElementById('selMonth').value;
        var y = document.getElementById('selYear').value;
        var s = document.getElementById('searchInput').value;
        var f = document.getElementById('selFloor').value;
        window.location.href = 'leaves.php?month='+m+'&year='+y+'&floor='+f+'&search='+encodeURIComponent(s);
    }
    </script>
</body>
</html>
