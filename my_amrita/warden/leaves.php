<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$warden_name = $_SESSION['user_name'];

$search = trim($_GET['search'] ?? '');
$selected_month = $_GET['month'] ?? date('m');
$selected_year = $_GET['year'] ?? date('Y');

$months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'];

// Get pass counts per student
$sql = "SELECT s.id, s.name, s.enrollment_no, s.hostel_room, s.hostel_block,
    (SELECT COUNT(*) FROM gate_passes g WHERE g.student_id = s.id AND g.pass_type = 'Gate Pass' AND MONTH(g.created_at) = ? AND YEAR(g.created_at) = ?) as gate_count,
    (SELECT COUNT(*) FROM gate_passes g WHERE g.student_id = s.id AND g.pass_type = 'Home Pass' AND MONTH(g.created_at) = ? AND YEAR(g.created_at) = ?) as home_count,
    (SELECT COUNT(*) FROM gate_passes g WHERE g.student_id = s.id AND MONTH(g.created_at) = ? AND YEAR(g.created_at) = ?) as total_count
    FROM students s WHERE s.hostel_room IS NOT NULL AND s.warden_name = ?";
$params = [$selected_month, $selected_year, $selected_month, $selected_year, $selected_month, $selected_year, $warden_name];
if ($search) {
    $sql .= " AND (s.name LIKE ? OR s.enrollment_no LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " ORDER BY total_count DESC, s.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Warden - Leaves/Passes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .top-navbar { background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%); }
        .filter-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
        .filter-row label { font-size:11px; font-weight:600; color:#888; text-transform:uppercase; }
        .filter-row select, .filter-row input[type="text"] { padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Warden Panel</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($warden_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Warden Home</a> <span class="sep">/</span> Leaves & Passes</div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar"></i> Monthly Pass Summary</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Warden Home</a>
        </div>

        <div class="filter-row">
            <label>Month:</label>
            <select onchange="applyFilter()" id="selMonth">
                <?php foreach ($months as $mv => $mn): ?>
                    <option value="<?php echo $mv; ?>" <?php echo $mv==$selected_month?'selected':''; ?>><?php echo $mn; ?></option>
                <?php endforeach; ?>
            </select>
            <label>Year:</label>
            <select onchange="applyFilter()" id="selYear">
                <option value="2025" <?php echo $selected_year=='2025'?'selected':''; ?>>2025</option>
                <option value="2026" <?php echo $selected_year=='2026'?'selected':''; ?>>2026</option>
            </select>
            <label>Search:</label>
            <input type="text" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or Reg No..." onkeydown="if(event.key==='Enter')applyFilter()">
            <button onclick="applyFilter()" style="padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;"><i class="fa fa-search"></i></button>
        </div>

        <div class="card">
            <h2 class="card-title"><?php echo $months[$selected_month]; ?> <?php echo $selected_year; ?> – Pass Count</h2>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Room</th><th>Gate Passes</th><th>Home Passes</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($s['name']); ?></strong><br><small style="color:#888;"><?php echo htmlspecialchars($s['enrollment_no']); ?></small></td>
                    <td><?php echo htmlspecialchars($s['hostel_room'] ?? '—'); ?></td>
                    <td style="text-align:center;"><span style="font-size:16px; font-weight:700; color:#1565c0;"><?php echo $s['gate_count']; ?></span></td>
                    <td style="text-align:center;"><span style="font-size:16px; font-weight:700; color:#e65100;"><?php echo $s['home_count']; ?></span></td>
                    <td style="text-align:center;"><span style="font-size:16px; font-weight:700; color:#a4123f;"><?php echo $s['total_count']; ?></span></td>
                </tr>
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
        window.location.href = 'leaves.php?month='+m+'&year='+y+'&search='+encodeURIComponent(s);
    }
    </script>
</body>
</html>
