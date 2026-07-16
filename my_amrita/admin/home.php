<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];

$total_students = $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$pending_regs = $pdo->query("SELECT COUNT(*) FROM student_registrations WHERE status = 'Pending'")->fetchColumn();
$total_events = $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita Admin</title>
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

        .pending-badge { background:#e74c3c; color:#fff; border-radius:10px; padding:2px 8px; font-size:11px; font-weight:600; }

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
        <span class="brand">Admin Panel</span>
        <div class="nav-links">
            <span><?php echo htmlspecialchars($admin_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>

    <div class="breadcrumb-bar">
        <a href="home.php">Home</a>
    </div>

    <div class="main-content">
        <div class="welcome-heading">Welcome! <?php echo htmlspecialchars($admin_name); ?></div>
        <div class="welcome-sub">Manage students, teachers, events, and campus operations</div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-users"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $total_students; ?></div><div class="stat-label">Students</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-book"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $total_teachers; ?></div><div class="stat-label">Teachers</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-pencil-square-o"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $pending_regs; ?></div><div class="stat-label">Pending Registrations</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-calendar-o"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $total_events; ?></div><div class="stat-label">Events</div></div>
            </div>
        </div>

        <!-- Management Section -->
        <div class="section-title"><i class="fa fa-cogs"></i> Core Management</div>
        <div class="modules-grid">
            <a class="module-card" href="students.php">
                <div><div class="module-name">Manage Students</div><div class="module-desc">Add, edit, delete students</div></div>
                <span class="module-icon"><i class="fa fa-users"></i></span>
            </a>
            <a class="module-card" href="manage_teachers.php">
                <div><div class="module-name">Manage Teachers</div><div class="module-desc">Add, edit, delete teachers</div></div>
                <span class="module-icon"><i class="fa fa-book"></i></span>
            </a>
            <a class="module-card" href="manage_registrations.php">
                <div><div class="module-name">Registrations <?php if($pending_regs > 0): ?><span class="pending-badge"><?php echo $pending_regs; ?></span><?php endif; ?></div><div class="module-desc">Approve / reject new registrations</div></div>
                <span class="module-icon"><i class="fa fa-pencil-square-o"></i></span>
            </a>
            <a class="module-card" href="manage_advisees.php">
                <div><div class="module-name">Manage Advisees</div><div class="module-desc">Assign students to faculty advisors</div></div>
                <span class="module-icon"><i class="fa fa-link"></i></span>
            </a>
            <a class="module-card" href="manage_id_cards.php">
                <div><div class="module-name">Manage ID Cards</div><div class="module-desc">Block/unblock student ID cards</div></div>
                <span class="module-icon"><i class="fa fa-credit-card"></i></span>
            </a>
            <a class="module-card" href="manage_documents.php">
                <div><div class="module-name">Documents</div><div class="module-desc">Verify uploaded student docs</div></div>
                <span class="module-icon"><i class="fa fa-file-text-o"></i></span>
            </a>
        </div>

        <div class="section-title"><i class="fa fa-graduation-cap"></i> Academic & Exam Management</div>
        <div class="modules-grid">
            <a class="module-card" href="manage_calendar.php">
                <div><div class="module-name">Academic Calendar</div><div class="module-desc">Update calendar dates</div></div>
                <span class="module-icon"><i class="fa fa-calendar"></i></span>
            </a>
            <a class="module-card" href="manage_timetable.php">
                <div><div class="module-name">Timetable</div><div class="module-desc">Update student timetables</div></div>
                <span class="module-icon"><i class="fa fa-clock-o"></i></span>
            </a>
            <a class="module-card" href="manage_timetable_changes.php">
                <div><div class="module-name">Timetable Changes</div><div class="module-desc">Ad-hoc class updates</div></div>
                <span class="module-icon"><i class="fa fa-bell"></i></span>
            </a>
            <a class="module-card" href="edit_attendance.php">
                <div><div class="module-name">Attendance</div><div class="module-desc">View & edit student attendance</div></div>
                <span class="module-icon"><i class="fa fa-check-square-o"></i></span>
            </a>
            <a class="module-card" href="manage_marks.php">
                <div><div class="module-name">Marks & Grades</div><div class="module-desc">Manage all student marks</div></div>
                <span class="module-icon"><i class="fa fa-bar-chart-o"></i></span>
            </a>
            <a class="module-card" href="manage_supplementary.php">
                <div><div class="module-name">Supplementary</div><div class="module-desc">Manage exams & results</div></div>
                <span class="module-icon"><i class="fa fa-repeat"></i></span>
            </a>
            <a class="module-card" href="manage_seating.php">
                <div><div class="module-name">Exam Seating</div><div class="module-desc">Exam seating arrangements</div></div>
                <span class="module-icon"><i class="fa fa-th"></i></span>
            </a>
            <a class="module-card" href="manage_admit_cards.php">
                <div><div class="module-name">Admit Cards</div><div class="module-desc">Generate/block admit cards</div></div>
                <span class="module-icon"><i class="fa fa-ticket"></i></span>
            </a>
            <a class="module-card" href="view_faculty.php">
                <div><div class="module-name">View Faculty</div><div class="module-desc">View faculty profiles & courses</div></div>
                <span class="module-icon"><i class="fa fa-file-text-o"></i></span>
            </a>
        </div>

        <div class="section-title"><i class="fa fa-money"></i> Finance & Services</div>
        <div class="modules-grid">
            <a class="module-card" href="manage_payments.php">
                <div><div class="module-name">Payments</div><div class="module-desc">Track fee payments</div></div>
                <span class="module-icon"><i class="fa fa-credit-card"></i></span>
            </a>
            <a class="module-card" href="manage_fees.php">
                <div><div class="module-name">Fee Structures</div><div class="module-desc">Manage fee requirements</div></div>
                <span class="module-icon"><i class="fa fa-inr"></i></span>
            </a>
            <a class="module-card" href="manage_refunds.php">
                <div><div class="module-name">Refunds</div><div class="module-desc">Process refund requests</div></div>
                <span class="module-icon"><i class="fa fa-undo"></i></span>
            </a>
            <a class="module-card" href="manage_services.php">
                <div><div class="module-name">Services & Complaints</div><div class="module-desc">Resolve IT/Maintenance requests</div></div>
                <span class="module-icon"><i class="fa fa-wrench"></i></span>
            </a>
            <a class="module-card" href="manage_files.php">
                <div><div class="module-name">Downloads</div><div class="module-desc">Manage downloadable files</div></div>
                <span class="module-icon"><i class="fa fa-download"></i></span>
            </a>
        </div>

        <div class="section-title"><i class="fa fa-shield"></i> Hostel, Welfare & Events</div>
        <div class="modules-grid">
            <a class="module-card" href="manage_gatepasses.php">
                <div><div class="module-name">Gate Passes (L2)</div><div class="module-desc">Approve/reject gate passes</div></div>
                <span class="module-icon"><i class="fa fa-sign-out"></i></span>
            </a>
            <a class="module-card" href="manage_medical_leaves.php">
                <div><div class="module-name">Medical Leaves</div><div class="module-desc">Manage student leaves</div></div>
                <span class="module-icon"><i class="fa fa-medkit"></i></span>
            </a>
            <a class="module-card" href="manage_incidents.php">
                <div><div class="module-name">Incidents</div><div class="module-desc">Record disciplinary actions</div></div>
                <span class="module-icon"><i class="fa fa-exclamation-triangle"></i></span>
            </a>
            <a class="module-card" href="manage_counselling.php">
                <div><div class="module-name">Counselling</div><div class="module-desc">Schedule counselling sessions</div></div>
                <span class="module-icon"><i class="fa fa-user-md"></i></span>
            </a>
            <a class="module-card" href="manage_events.php">
                <div><div class="module-name">Events</div><div class="module-desc">Add and manage campus events</div></div>
                <span class="module-icon"><i class="fa fa-calendar-o"></i></span>
            </a>
            <a class="module-card" href="view_feedback.php">
                <div><div class="module-name">TLP Feedback</div><div class="module-desc">View student TLP feedback</div></div>
                <span class="module-icon"><i class="fa fa-comments"></i></span>
            </a>
        </div>
    </div>
</body>
</html>
