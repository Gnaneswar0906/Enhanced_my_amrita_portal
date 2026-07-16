<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$selected_type = $_GET['type'] ?? 'mid';

$stmt = $pdo->prepare('SELECT * FROM seating_arrangements WHERE student_id = ? ORDER BY exam_date');
$stmt->execute([$student_id]);
$all_seatings = $stmt->fetchAll();

// Separate by exam_type
$mid_seatings = [];
$end_seatings = [];
foreach ($all_seatings as $s) {
    $type = strtolower($s['exam_type'] ?? '');
    if (strpos($type, 'end') !== false) {
        $end_seatings[] = $s;
    } else {
        $mid_seatings[] = $s;
    }
}

$seatings = $selected_type === 'end' ? $end_seatings : $mid_seatings;
$type_label = $selected_type === 'end' ? 'End Semester' : 'Mid Semester';
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Seating</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .seating-table { width:100%; border-collapse:separate; border-spacing:0; border-radius:10px; overflow:hidden; background:#fff; }
        .seating-table thead th { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; padding:12px 16px; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; text-align:left; white-space:nowrap; }
        .seating-table tbody td { padding:12px 16px; font-size:13px; border-bottom:1px solid #f0f0f0; color:#333; }
        .seating-table tbody tr:hover { background:#fdf5f7; }
        .seating-table tbody tr:last-child td { border-bottom:none; }
        .room-badge { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:600; }
        .room-badge.classroom { background:#e3f2fd; color:#1565c0; }
        .room-badge.lab { background:#f3e5f5; color:#7b1fa2; }
        .date-cell { color:#a4123f; font-weight:600; }
        .exam-tabs { display:flex; gap:8px; margin-bottom:20px; }
        .exam-tab { padding:10px 24px; border-radius:8px; border:2px solid #e0e0e0; background:#fff; color:#666; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.2s; }
        .exam-tab:hover { border-color:#a4123f; color:#a4123f; }
        .exam-tab.active { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-color:transparent; }
        .exam-tab .count { display:inline-block; background:rgba(255,255,255,0.3); padding:1px 8px; border-radius:10px; font-size:11px; margin-left:6px; }
        .exam-tab.active .count { background:rgba(255,255,255,0.3); }
        .exam-tab:not(.active) .count { background:#f0f0f0; color:#888; }
        .hackathon-badge { background:#fff3e0; color:#e65100; padding:3px 8px; border-radius:4px; font-size:10px; font-weight:700; margin-left:6px; }
        .lab-exam-badge { background:#e8f5e9; color:#2e7d32; padding:3px 8px; border-radius:4px; font-size:10px; font-weight:700; margin-left:6px; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Seating Arrangements</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-map-marker"></i> Exam Seating Arrangements</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <div class="alert-banner info"><i class="fa fa-info-circle"></i><span class="alert-text">
            Exam venues: <strong>A/B/C Blocks</strong> (Rooms 101-310, 35 seats each) | <strong>E-Block</strong> (Rooms 210-212, 60 seats each) | <strong>E-Block 5th Floor</strong> (CSE Lab 1-4, 60 seats) | <strong>CIR/Hackathon Labs</strong> (80-100 seats)
        </span></div>

        <!-- Mid / End Tabs -->
        <div class="exam-tabs">
            <a href="seating.php?type=mid" class="exam-tab <?php echo $selected_type === 'mid' ? 'active' : ''; ?>">
                <i class="fa fa-pencil-square-o"></i> Mid Semester <span class="count"><?php echo count($mid_seatings); ?></span>
            </a>
            <a href="seating.php?type=end" class="exam-tab <?php echo $selected_type === 'end' ? 'active' : ''; ?>">
                <i class="fa fa-graduation-cap"></i> End Semester <span class="count"><?php echo count($end_seatings); ?></span>
            </a>
        </div>

        <div class="card">
            <h2 class="card-title"><i class="fa fa-th" style="color:#a4123f;"></i> <?php echo $type_label; ?> – Seating Assignments</h2>
            <?php if (empty($seatings)): ?>
                <div class="empty-state"><i class="fa fa-map-marker"></i><p>No seating arrangements for <?php echo strtolower($type_label); ?> yet.</p></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="seating-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Exam Name</th>
                                <th>Course Code</th>
                                <th>Exam Date</th>
                                <th>Block</th>
                                <th>Floor</th>
                                <th>Room</th>
                                <th>Seat No.</th>
                                <th>Venue Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($seatings as $i => $s):
                                $isLab = stripos($s['hall_name'] ?? '', 'Lab') !== false;
                                $roomType = $isLab ? 'lab' : 'classroom';
                                $isHackathon = stripos($s['exam_name'] ?? '', 'Hackathon') !== false;
                                $isLabExam = stripos($s['exam_name'] ?? '', '(Lab)') !== false;
                                $isViva = stripos($s['exam_name'] ?? '', 'Viva') !== false;
                            ?>
                            <tr>
                                <td><strong><?php echo $i + 1; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($s['exam_name']); ?></strong>
                                    <?php if ($isHackathon): ?><span class="hackathon-badge"><i class="fa fa-code"></i> HACKATHON</span><?php endif; ?>
                                    <?php if ($isLabExam): ?><span class="lab-exam-badge"><i class="fa fa-desktop"></i> LAB EXAM</span><?php endif; ?>
                                    <?php if ($isViva): ?><span class="lab-exam-badge"><i class="fa fa-microphone"></i> VIVA</span><?php endif; ?>
                                </td>
                                <td><span style="color:#a4123f; font-weight:600;"><?php echo htmlspecialchars($s['course_code']); ?></span></td>
                                <td class="date-cell"><?php echo date('d M Y (l)', strtotime($s['exam_date'])); ?></td>
                                <td><i class="fa fa-building" style="color:#a4123f; margin-right:4px;"></i><?php echo htmlspecialchars($s['block']); ?></td>
                                <td><?php echo htmlspecialchars($s['floor']); ?></td>
                                <td>
                                    <span class="room-badge <?php echo $roomType; ?>">
                                        <i class="fa <?php echo $isLab ? 'fa-desktop' : 'fa-home'; ?>"></i>
                                        <?php echo htmlspecialchars($s['room_number']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($s['seat_number']); ?></strong></td>
                                <td>
                                    <span class="room-badge <?php echo $roomType; ?>">
                                        <i class="fa <?php echo $isLab ? 'fa-flask' : 'fa-university'; ?>"></i>
                                        <?php echo $isLab ? 'Laboratory' : 'Classroom'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
