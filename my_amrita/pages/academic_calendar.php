<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$semester_map = [
    '2023-24' => ['odd' => 1, 'even' => 2],
    '2024-25' => ['odd' => 3, 'even' => 4],
    '2025-26' => ['odd' => 5, 'even' => 6],
    '2026-27' => ['odd' => 7, 'even' => 8],
];
$selected_year = $_GET['year'] ?? '2025-26';
$selected_type = $_GET['sem_type'] ?? 'even';
$current_sem = $semester_map[$selected_year][$selected_type] ?? 6;
$selected_month = $_GET['month'] ?? date('n');

$stmt = $pdo->prepare('SELECT * FROM academic_calendar WHERE semester = ? ORDER BY event_date ASC');
$stmt->execute([$current_sem]);
$events = $stmt->fetchAll();

// Group by month
$months_data = [];
foreach ($events as $ev) {
    $m = intval(date('n', strtotime($ev['event_date'])));
    if (!isset($months_data[$m])) $months_data[$m] = [];
    $months_data[$m][] = $ev;
}
$month_names = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Academic Calendar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .month-tabs { display:flex; gap:6px; margin-bottom:20px; flex-wrap:wrap; }
        .month-tab { padding:8px 16px; border-radius:8px; border:1px solid #e0e0e0; background:#fff; color:#666; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; text-decoration:none; }
        .month-tab:hover { border-color:#a4123f; color:#a4123f; }
        .month-tab.active { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-color:transparent; }
        .month-tab.current::after { content:'Now'; position:absolute; top:-8px; right:-6px; background:#27ae60; color:#fff; font-size:8px; padding:1px 5px; border-radius:6px; font-weight:700; }
        .month-tab.current { position:relative; }
        .cal-table { width:100%; border-collapse:collapse; font-size:13px; }
        .cal-table th { background:linear-gradient(135deg,#e87722,#f5a623); color:#fff; padding:10px 12px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
        .cal-table td { padding:8px 12px; border-bottom:1px solid #eee; vertical-align:middle; }
        .cal-table tr:hover { background:#f8f9fa; }
        .cal-table tr.holiday-row { background:#fff3e0; }
        .cal-table tr.holiday-row td { color:#a4123f; font-weight:500; }
        .cal-table tr.today-row { background:#e8f5e9; border-left:4px solid #27ae60; }
        .cal-table tr.today-row td:first-child { font-weight:700; }
        .cal-table .h-badge { display:inline-block; background:#a4123f; color:#fff; font-size:10px; font-weight:700; padding:2px 8px; border-radius:4px; }
        .cal-table .wd-num { display:inline-block; background:#e3f2fd; color:#1565c0; font-size:11px; font-weight:700; padding:2px 8px; border-radius:4px; min-width:28px; text-align:center; }
        .cal-table .sat-lbl { float:right; font-size:10px; color:#888; font-style:italic; }
        .cal-table .event-text { font-weight:500; color:#333; }
        .event-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:10px; font-weight:600; margin-left:6px; }
        .event-badge.exam { background:#fde8e8; color:#e74c3c; }
        .event-badge.deadline { background:#fff3cd; color:#856404; }
        .event-badge.holiday { background:#ffe0b2; color:#e65100; }
        .download-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:linear-gradient(135deg,#27ae60,#2ecc71); color:#fff; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; transition:all 0.2s; }
        .download-btn:hover { opacity:0.9; color:#fff; }
        .month-header { display:flex; align-items:center; justify-content:space-between; }
        .month-header h2 { margin:0; }
        .stats-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
        .stat-chip { padding:6px 14px; border-radius:8px; font-size:12px; font-weight:600; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Academic Calendar</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar"></i> Academic Calendar</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <!-- Semester Selector + Download -->
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px;">
            <label style="font-size:12px; font-weight:600; color:#888; text-transform:uppercase;">Academic Year:</label>
            <select onchange="changeSemester()" id="semYear" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
                <?php foreach (array_keys($semester_map) as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $yr === $selected_year ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>
            <label style="font-size:12px; font-weight:600; color:#888; text-transform:uppercase;">Semester:</label>
            <select onchange="changeSemester()" id="semType" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
                <option value="odd" <?php echo $selected_type === 'odd' ? 'selected' : ''; ?>>Odd</option>
                <option value="even" <?php echo $selected_type === 'even' ? 'selected' : ''; ?>>Even</option>
            </select>
            <span style="padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-radius:8px; font-size:12px; font-weight:600;">Semester <?php echo $current_sem; ?></span>
            <a href="../uploads/docs/academic_calendar_2025_26_even.html" target="_blank" class="download-btn" style="margin-left:auto;"><i class="fa fa-download"></i> Download Calendar</a>
        </div>

        <!-- Month Tabs -->
        <div class="month-tabs">
            <?php
            $current_month = intval(date('n'));
            foreach ($months_data as $m => $data):
                $is_active = intval($selected_month) === $m;
                $is_current = $m === $current_month;
            ?>
            <a href="?year=<?php echo $selected_year; ?>&sem_type=<?php echo $selected_type; ?>&month=<?php echo $m; ?>" class="month-tab <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_current ? 'current' : ''; ?>">
                <?php echo $month_names[$m]; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Month Calendar Table -->
        <?php $month_events = $months_data[intval($selected_month)] ?? []; ?>
        <div class="card">
            <div class="month-header">
                <h2 class="card-title"><i class="fa fa-calendar" style="color:#e87722;"></i> <?php echo $month_names[intval($selected_month)] ?? 'January'; ?> 2026</h2>
            </div>

            <?php
            // Stats
            $working = 0; $holidays = 0; $exam_days = 0;
            foreach ($month_events as $ev) {
                if (!empty($ev['is_holiday'])) $holidays++;
                elseif (!empty($ev['working_day'])) $working++;
                if (strtolower($ev['event_type']) === 'examination') $exam_days++;
            }
            ?>
            <div class="stats-row" style="margin:12px 0 16px;">
                <span class="stat-chip" style="background:#e8f5e9; color:#2e7d32;"><i class="fa fa-book"></i> <?php echo $working; ?> Working Days</span>
                <span class="stat-chip" style="background:#fff3e0; color:#e65100;"><i class="fa fa-calendar-times-o"></i> <?php echo $holidays; ?> Holidays</span>
                <?php if ($exam_days): ?><span class="stat-chip" style="background:#fde8e8; color:#e74c3c;"><i class="fa fa-pencil"></i> <?php echo $exam_days; ?> Exam Days</span><?php endif; ?>
            </div>

            <?php if (empty($month_events)): ?>
                <div class="empty-state"><i class="fa fa-calendar"></i><p>No calendar data for this month.</p></div>
            <?php else: ?>
            <table class="cal-table">
                <thead>
                    <tr>
                        <th style="width:90px;">Date</th>
                        <th style="width:50px;">Day</th>
                        <th style="width:60px;">Working Day</th>
                        <th>Events / Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($month_events as $ev):
                        $dt = $ev['event_date'];
                        $is_h = !empty($ev['is_holiday']);
                        $is_today = $dt === $today;
                        $row_class = '';
                        if ($is_today) $row_class = 'today-row';
                        elseif ($is_h) $row_class = 'holiday-row';
                        $day_short = date('d-M', strtotime($dt));
                        $day_name = $ev['day_name'] ?? date('D', strtotime($dt));
                        $wd = $ev['working_day'] ?? null;
                        $title = $ev['event_title'] ?? '';
                        $sat = $ev['sat_label'] ?? '';
                        $etype = strtolower($ev['event_type'] ?? '');
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td><?php echo $day_short; ?><?php if ($is_today): ?> <span style="font-size:9px; background:#27ae60; color:#fff; padding:1px 5px; border-radius:4px;">TODAY</span><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($day_name); ?></td>
                        <td>
                            <?php if ($is_h): ?>
                                <span class="h-badge">H</span>
                            <?php elseif ($wd): ?>
                                <span class="wd-num"><?php echo $wd; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($title): ?>
                                <span class="event-text"><?php echo htmlspecialchars($title); ?></span>
                                <?php if ($etype === 'examination'): ?><span class="event-badge exam">Exam</span><?php endif; ?>
                                <?php if ($etype === 'deadline'): ?><span class="event-badge deadline">Deadline</span><?php endif; ?>
                            <?php endif; ?>
                            <?php if ($sat): ?><span class="sat-lbl"><?php echo $sat; ?></span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function changeSemester() {
        var y = document.getElementById('semYear').value;
        var t = document.getElementById('semType').value;
        window.location.href = 'academic_calendar.php?year=' + y + '&sem_type=' + t;
    }
    </script>
</body>
</html>
