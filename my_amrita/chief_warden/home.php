<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chief_warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$cw_name = $_SESSION['user_name'];

$pending_l3 = $pdo->query('SELECT COUNT(*) FROM gate_passes WHERE level2_status = "Approved" AND level3_status = "Pending"')->fetchColumn();
$total_hostel = $pdo->query('SELECT COUNT(*) FROM students WHERE hostel_room IS NOT NULL')->fetchColumn();
$blocked_ids = $pdo->query('SELECT COUNT(*) FROM student_id_cards WHERE card_status = "Blocked"')->fetchColumn();
$total_complaints = $pdo->query('SELECT COUNT(*) FROM services WHERE category = "Complaint" AND status = "Open"')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Chief Warden Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link rel="icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin:0; padding:0; font-family:'Inter','Segoe UI',Arial,sans-serif; background:#f5f5f5; color:#333; }

        .top-navbar {
            background: linear-gradient(135deg, #a4123f 0%, #c2185b 100%);
            color:#fff; padding:10px 20px; display:flex; align-items:center;
            justify-content:space-between; box-shadow:0 2px 8px rgba(0,0,0,0.15);
            position:sticky; top:0; z-index:1000;
        }
        .top-navbar .brand { font-size:18px; font-weight:600; letter-spacing:0.5px; }
        .top-navbar .nav-links { display:flex; align-items:center; gap:18px; }
        .top-navbar .nav-links span { font-size:13px; opacity:0.9; }

        .logout-btn {
            background:none; border:1px solid rgba(255,255,255,0.4); color:#fff;
            padding:5px 14px; border-radius:6px; font-size:12px; cursor:pointer;
            transition:all 0.2s; text-decoration:none; font-family:'Inter',sans-serif;
        }
        .logout-btn:hover { background:rgba(255,255,255,0.15); border-color:#fff; }

        .breadcrumb-bar {
            background:#fff; padding:8px 20px; font-size:13px; color:#888;
            border-bottom:1px solid #e0e0e0;
        }
        .breadcrumb-bar a { color:#a4123f; text-decoration:none; }

        .main-content { max-width:1100px; margin:0 auto; padding:20px; }

        .welcome-heading { color:#a4123f; font-size:16px; font-weight:600; margin-bottom:6px; }
        .welcome-sub { font-size:13px; color:#888; margin-bottom:20px; }

        /* Stats */
        .stats-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:26px; }
        .stat-card {
            background:#fff; border-radius:8px; padding:18px; border:1px solid #e8e8e8;
            display:flex; align-items:center; gap:14px; transition:all 0.25s;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(164,18,63,0.10); }
        .stat-icon {
            width:46px; height:46px; border-radius:8px; display:flex;
            align-items:center; justify-content:center; font-size:20px; color:#fff; flex-shrink:0;
            background: linear-gradient(135deg, #a4123f, #d4264f);
        }
        .stat-info .stat-value { font-size:22px; font-weight:700; color:#333; }
        .stat-info .stat-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:0.5px; }

        /* Section titles */
        .section-title { font-size:14px; font-weight:700; color:#a4123f; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px; }

        /* Module grid */
        .modules-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:26px; }
        .module-card {
            background:#fff; border:1px solid #e8e8e8; border-radius:8px;
            padding:16px 14px; display:flex; align-items:center; justify-content:space-between;
            cursor:pointer; transition:all 0.25s ease; text-decoration:none; color:#333;
        }
        .module-card:hover { transform:translateY(-3px); box-shadow:0 6px 20px rgba(164,18,63,0.12); border-color:#d4264f; }
        .module-card .module-name { font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; color:#444; }
        .module-card .module-desc { font-size:11px; color:#999; margin-top:3px; }
        .module-card .module-icon {
            width:36px; height:36px;
            background:linear-gradient(135deg,#a4123f,#d4264f);
            border-radius:6px; display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:16px; flex-shrink:0;
        }

        @media (max-width:900px) { .modules-grid { grid-template-columns:repeat(2,1fr); } }
        @media (max-width:480px) { .modules-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Chief Warden Panel</span>
        <div class="nav-links">
            <span><?php echo htmlspecialchars($cw_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>

    <div class="breadcrumb-bar">
        <a href="home.php">Home</a>
    </div>

    <div class="main-content">
        <div class="welcome-heading">Welcome! <?php echo htmlspecialchars($cw_name); ?></div>
        <div class="welcome-sub">Final approval authority for gate passes and hostel oversight</div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-credit-card"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $pending_l3; ?></div><div class="stat-label">Pending Gate Passes (L3)</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-users"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $total_hostel; ?></div><div class="stat-label">Hostel Students</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-ban"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $blocked_ids; ?></div><div class="stat-label">Blocked IDs</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-exclamation-circle"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $total_complaints; ?></div><div class="stat-label">Open Complaints</div></div>
            </div>
        </div>

        <!-- Management -->
        <div class="section-title"><i class="fa fa-star"></i> Chief Warden Management</div>
        <div class="modules-grid">
            <a class="module-card" href="gate_passes.php">
                <div><div class="module-name">Gate Pass (L3 Final)</div><div class="module-desc">Final approval after warden L2</div></div>
                <span class="module-icon"><i class="fa fa-ticket"></i></span>
            </a>
            <a class="module-card" href="hostel_overview.php">
                <div><div class="module-name">Hostel Overview</div><div class="module-desc">Attendance, IDs, complaints overview</div></div>
                <span class="module-icon"><i class="fa fa-building"></i></span>
            </a>
            <a class="module-card" href="id_cards.php">
                <div><div class="module-name">ID Cards</div><div class="module-desc">Floor-wise blocked ID cards</div></div>
                <span class="module-icon"><i class="fa fa-credit-card"></i></span>
            </a>
            <a class="module-card" href="hostel_attendance.php">
                <div><div class="module-name">Hostel Attendance</div><div class="module-desc">Floor-wise absent students</div></div>
                <span class="module-icon"><i class="fa fa-calendar-times-o"></i></span>
            </a>
            <a class="module-card" href="rooms.php">
                <div><div class="module-name">Rooms</div><div class="module-desc">Floor-wise rooms & students</div></div>
                <span class="module-icon"><i class="fa fa-th"></i></span>
            </a>
            <a class="module-card" href="leaves.php">
                <div><div class="module-name">Leaves & Passes</div><div class="module-desc">Floor-wise monthly pass count</div></div>
                <span class="module-icon"><i class="fa fa-calendar"></i></span>
            </a>
        </div>
    </div>
</body>
</html>
