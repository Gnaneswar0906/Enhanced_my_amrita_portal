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

$stmt = $pdo->prepare('SELECT * FROM timetable WHERE student_id = ? AND semester = ? ORDER BY FIELD(day_name, "Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"), time_slot');
$stmt->execute([$student_id, $current_sem]);
$timetable = $stmt->fetchAll();

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$day_data = [];
foreach ($days as $d) { $day_data[$d] = []; }
foreach ($timetable as $t) {
    if (in_array($t['day_name'], $days)) $day_data[$t['day_name']][] = $t;
}

// If weekend, still show Saturday/Sunday
$today_name = date('l');
$selected_day = $_GET['day'] ?? $today_name;

// Get unique time slots for grid - sorted by start time
$time_slots = [];
foreach ($timetable as $t) {
    if (!in_array($t['time_slot'], $time_slots) && in_array($t['day_name'], $days))
        $time_slots[] = $t['time_slot'];
}
// Custom sort: parse start time and sort chronologically
usort($time_slots, function($a, $b) {
    $ta = strtotime(trim(explode('-', $a)[0]));
    $tb = strtotime(trim(explode('-', $b)[0]));
    return $ta - $tb;
});

// Today/Tomorrow info
$today_day = date('l');
$tomorrow_day_name = date('l', strtotime('+1 day'));
$is_weekend = !in_array($today_day, $days);

function parse_time_slot($slot) {
    $parts = explode(' - ', $slot);
    return ['start' => trim($parts[0] ?? $slot), 'end' => trim($parts[1] ?? '')];
}

// Upcoming updates (today and tomorrow only, exclude Sat/Sun)
$changes = [];
try {
    $today_date = date('Y-m-d');
    $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
    $stmt_chg = $pdo->prepare("SELECT * FROM timetable_changes WHERE student_id = 0 AND effective_date IN (?, ?) ORDER BY effective_date, id DESC");
    $stmt_chg->execute([$today_date, $tomorrow_date]);
    $all_changes = $stmt_chg->fetchAll();
    
    // Filter to only courses the student is enrolled in
    $stmt_tc = $pdo->prepare("SELECT DISTINCT course_code FROM timetable WHERE student_id = ? AND semester = ?");
    $stmt_tc->execute([$student_id, $current_sem]);
    $my_courses = $stmt_tc->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($all_changes as $ch) {
        if (in_array($ch['course_code'], $my_courses)) {
            $changes[] = $ch;
        }
    }
} catch(Exception $e) {}

// Also sort day_data by time for daily view
foreach ($days as $d) {
    usort($day_data[$d], function($a, $b) {
        $ta = strtotime(trim(explode('-', $a['time_slot'])[0]));
        $tb = strtotime(trim(explode('-', $b['time_slot'])[0]));
        return $ta - $tb;
    });
}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Timetable</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .semester-selector { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px; padding:14px 18px; background:#fff; border:1px solid #e8e8e8; border-radius:10px; }
        .semester-selector label { font-size:12px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:0.5px; margin-right:4px; }
        .semester-selector select { padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif; cursor:pointer; }
        .semester-selector .sem-badge { padding:6px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-radius:8px; font-size:12px; font-weight:600; }
        .day-tabs { display:flex; gap:6px; margin-bottom:20px; flex-wrap:wrap; }
        .day-tab { padding:8px 16px; border-radius:8px; border:1px solid #e0e0e0; background:#fff; color:#666; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; text-decoration:none; }
        .day-tab:hover { border-color:#a4123f; color:#a4123f; }
        .day-tab.active { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-color:transparent; }
        .day-tab.today { position:relative; }
        .day-tab.today::after { content:'Today'; position:absolute; top:-8px; right:-6px; background:#27ae60; color:#fff; font-size:8px; padding:1px 5px; border-radius:6px; font-weight:700; }
        .slot-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:18px; margin-bottom:12px; display:flex; gap:18px; align-items:center; transition:all 0.2s; }
        .slot-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.06); }
        .slot-time { text-align:center; min-width:80px; }
        .slot-time .start { font-size:16px; font-weight:700; color:#a4123f; }
        .slot-time .end { font-size:12px; color:#888; }
        .slot-divider { width:3px; height:50px; background:linear-gradient(to bottom,#a4123f,#d4264f); border-radius:2px; flex-shrink:0; }
        .slot-info { flex:1; }
        .slot-info .code { font-size:11px; color:#a4123f; font-weight:700; text-transform:uppercase; letter-spacing:0.3px; }
        .slot-info .name { font-size:15px; font-weight:600; color:#333; margin:2px 0 6px; }
        .slot-info .faculty { font-size:12px; color:#666; }
        .slot-info .faculty i { color:#a4123f; margin-right:4px; }
        .slot-info .room { display:inline-block; background:#f5f5f5; padding:2px 8px; border-radius:6px; font-size:11px; color:#555; margin-top:4px; }
        /* Weekly Grid */
        .weekly-grid { width:100%; border-collapse:collapse; font-size:12px; }
        .weekly-grid th { background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; padding:10px 8px; text-align:center; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
        .weekly-grid td { padding:6px; border:1px solid #e8e8e8; vertical-align:top; min-width:120px; }
        .weekly-grid .time-cell { background:#f8f9fa; font-weight:600; color:#a4123f; text-align:center; white-space:nowrap; min-width:100px; }
        .grid-slot { background:#f0f4ff; border-radius:6px; padding:6px 8px; margin:2px 0; font-size:11px; }
        .grid-slot .gs-code { font-weight:700; color:#a4123f; font-size:10px; }
        .grid-slot .gs-name { color:#333; font-weight:500; }
        .grid-slot .gs-room { color:#888; font-size:10px; }
        .today-col { background:#fef9e7 !important; }
        .slot-card.break-slot { background:#f0f7f0; border-color:#c8e6c9; opacity:0.85; }
        .slot-card.break-slot .slot-divider { background:#81c784; }
        .slot-card.break-slot .slot-info .name { color:#388e3c; font-size:13px; }
        .slot-card.lunch-slot { background:#fff8e1; border-color:#ffe082; opacity:0.85; }
        .slot-card.lunch-slot .slot-divider { background:#ffa726; }
        .slot-card.lunch-slot .slot-info .name { color:#e65100; font-size:13px; }
        .slot-card.bus-slot { background:#e3f2fd; border-color:#90caf9; opacity:0.85; }
        .slot-card.bus-slot .slot-divider { background:#42a5f5; }
        .slot-card.bus-slot .slot-info .name { color:#1565c0; font-size:13px; }
        .slot-card.add-slot { background:#f3e5f5; border-color:#ce93d8; opacity:0.85; }
        .slot-card.add-slot .slot-divider { background:#ab47bc; }
        .slot-card.add-slot .slot-info .name { color:#7b1fa2; font-size:13px; }
        .grid-slot.break-grid { background:#e8f5e9; }
        .grid-slot.break-grid .gs-name { color:#388e3c; }
        .grid-slot.lunch-grid { background:#fff8e1; }
        .grid-slot.lunch-grid .gs-name { color:#e65100; }
        .grid-slot.bus-grid { background:#e3f2fd; }
        .grid-slot.bus-grid .gs-name { color:#1565c0; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Timetable</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-clock-o"></i> Class Timetable</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <!-- Semester Selector -->
        <div class="semester-selector">
            <label>Academic Year:</label>
            <select onchange="changeSemester()" id="semYear">
                <?php foreach (array_keys($semester_map) as $yr): ?>
                    <option value="<?php echo $yr; ?>" <?php echo $yr === $selected_year ? 'selected' : ''; ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>
            <label>Semester:</label>
            <select onchange="changeSemester()" id="semType">
                <option value="odd" <?php echo $selected_type === 'odd' ? 'selected' : ''; ?>>Odd</option>
                <option value="even" <?php echo $selected_type === 'even' ? 'selected' : ''; ?>>Even</option>
            </select>
            <span class="sem-badge">Semester <?php echo $current_sem; ?></span>
        </div>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('daily', this)"><i class="fa fa-calendar"></i> Daily</button>
            <button class="tab-btn" onclick="switchTab('grid', this)"><i class="fa fa-th"></i> Grid</button>
            <button class="tab-btn" onclick="switchTab('changes', this)"><i class="fa fa-bell"></i> Changes</button>
        </div>

        <!-- TAB 1: Daily View -->
        <div class="tab-content active" id="tab-daily">
            <div class="day-tabs">
                <?php foreach ($days as $d): ?>
                <a href="?year=<?php echo $selected_year; ?>&sem_type=<?php echo $selected_type; ?>&day=<?php echo $d; ?>" class="day-tab <?php echo $d === $selected_day ? 'active' : ''; ?> <?php echo $d === $today_name ? 'today' : ''; ?>">
                    <?php echo substr($d, 0, 3); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h2 class="card-title"><i class="fa fa-calendar" style="color:#a4123f;"></i> <?php echo $selected_day; ?>'s Schedule
                    <?php if ($selected_day === $today_name): ?>
                        <span style="font-size:12px; background:#e8f5e9; color:#27ae60; padding:2px 8px; border-radius:10px; font-weight:600; margin-left:8px;">Today</span>
                    <?php endif; ?>
                </h2>
                <?php if (in_array($selected_day, ['Saturday','Sunday'])): ?>
                    <div class="empty-state"><i class="fa fa-coffee"></i><p>No Classes — Enjoy your weekend!</p></div>
                <?php elseif (empty($day_data[$selected_day])): ?>
                    <div class="empty-state"><i class="fa fa-coffee"></i><p>No classes scheduled for <?php echo $selected_day; ?>!</p></div>
                <?php else: ?>
                    <?php foreach ($day_data[$selected_day] as $slot):
                        $times = parse_time_slot($slot['time_slot']);
                        $is_break = in_array($slot['course_code'], ['BREAK','LUNCH','BUS','ADDSLOT']);
                        $slot_class = 'slot-card';
                        if ($slot['course_code'] === 'BREAK') $slot_class .= ' break-slot';
                        elseif ($slot['course_code'] === 'LUNCH') $slot_class .= ' lunch-slot';
                        elseif ($slot['course_code'] === 'BUS') $slot_class .= ' bus-slot';
                        elseif ($slot['course_code'] === 'ADDSLOT') $slot_class .= ' add-slot';
                        $break_icon = match($slot['course_code']) { 'BREAK' => 'fa-coffee', 'LUNCH' => 'fa-cutlery', 'BUS' => 'fa-bus', 'ADDSLOT' => 'fa-plus-circle', default => '' };
                    ?>
                    <div class="<?php echo $slot_class; ?>">
                        <div class="slot-time"><div class="start"><?php echo htmlspecialchars($times['start']); ?></div><div class="end"><?php echo htmlspecialchars($times['end']); ?></div></div>
                        <div class="slot-divider"></div>
                        <div class="slot-info">
                            <?php if ($is_break): ?>
                                <div class="name"><i class="fa <?php echo $break_icon; ?>"></i> <?php echo htmlspecialchars($slot['course_name']); ?></div>
                            <?php else: ?>
                                <div class="code"><?php echo htmlspecialchars($slot['course_code']); ?></div>
                                <div class="name"><?php echo htmlspecialchars($slot['course_name']); ?></div>
                                <div class="faculty"><i class="fa fa-user"></i> <?php echo htmlspecialchars($slot['faculty_name'] ?? 'Faculty TBA'); ?></div>
                                <?php if ($slot['room']): ?><span class="room"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($slot['room']); ?></span><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 2: Weekly Grid -->
        <div class="tab-content" id="tab-grid">
            <div class="card">
                <h2 class="card-title"><i class="fa fa-th" style="color:#a4123f;"></i> Weekly Grid View</h2>
                <?php if (empty($timetable)): ?>
                    <div class="empty-state"><i class="fa fa-calendar"></i><p>No timetable data.</p></div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="weekly-grid">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <?php foreach ($days as $d): ?>
                                <th class="<?php echo $d === $today_name ? 'today-col' : ''; ?>"><?php echo substr($d,0,3); ?><?php if ($d === $today_name) echo ' <span style="font-size:8px;">TODAY</span>'; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($time_slots as $ts): ?>
                            <tr>
                                <td class="time-cell"><?php echo htmlspecialchars($ts); ?></td>
                                <?php foreach ($days as $d):
                                    $match = null;
                                    foreach ($day_data[$d] as $item) { if ($item['time_slot'] === $ts) { $match = $item; break; } }
                                ?>
                                <td class="<?php echo $d === $today_name ? 'today-col' : ''; ?>">
                                    <?php if ($match):
                                        $gclass = 'grid-slot';
                                        if ($match['course_code'] === 'BREAK') $gclass .= ' break-grid';
                                        elseif ($match['course_code'] === 'LUNCH') $gclass .= ' lunch-grid';
                                        elseif (in_array($match['course_code'], ['BUS','ADDSLOT'])) $gclass .= ' bus-grid';
                                    ?>
                                    <div class="<?php echo $gclass; ?>">
                                        <?php if (in_array($match['course_code'], ['BREAK','LUNCH','BUS','ADDSLOT'])): ?>
                                            <div class="gs-name"><?php echo htmlspecialchars($match['course_name']); ?></div>
                                        <?php else: ?>
                                            <div class="gs-code"><?php echo htmlspecialchars($match['course_code']); ?></div>
                                            <div class="gs-name"><?php echo htmlspecialchars($match['course_name']); ?></div>
                                            <div class="gs-room"><?php echo htmlspecialchars($match['room'] ?? '—'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- TAB 3: Changes View -->
        <div class="tab-content" id="tab-changes">
            <div class="card" style="border-left:4px solid #f5a623; background:#fffbf0;">
                <h2 class="card-title" style="color:#e65100;"><i class="fa fa-bell"></i> Timetable Changes (Today & Tomorrow)</h2>
                <?php if (empty($changes)): ?>
                    <div class="empty-state"><i class="fa fa-check-circle" style="color:#27ae60;"></i><p>No timetable changes posted for your courses.</p></div>
                <?php else: ?>
                    <?php
                    $grouped = [];
                    foreach ($changes as $chg) {
                        $dt = $chg['effective_date'];
                        if (!isset($grouped[$dt])) $grouped[$dt] = [];
                        $grouped[$dt][] = $chg;
                    }
                    foreach ($grouped as $dt => $day_changes):
                        $day_label = date('l, d M Y', strtotime($dt));
                    ?>
                    <div style="font-size:13px; font-weight:700; color:#a4123f; text-transform:uppercase; margin:16px 0 8px; letter-spacing:0.5px;"><?php echo $day_label; ?></div>
                    <?php foreach ($day_changes as $chg): ?>
                    <div style="background:#fff; border:1px solid #e8e8e8; border-radius:8px; padding:16px; margin-bottom:10px; display:flex; gap:16px; align-items:flex-start;">
                        <div style="width:42px; height:42px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;
                            <?php echo match($chg['change_type']) {
                                'Room Change' => 'background:#e3f2fd; color:#1565c0;',
                                'Extra Class' => 'background:#e8f5e9; color:#2e7d32;',
                                'Cancelled' => 'background:#fde8e8; color:#e74c3c;',
                                'Time Change' => 'background:#fff3cd; color:#856404;',
                                default => 'background:#f5f5f5; color:#666;'
                            }; ?>">
                            <i class="fa <?php echo match($chg['change_type']) {
                                'Room Change' => 'fa-exchange',
                                'Extra Class' => 'fa-plus-circle',
                                'Cancelled' => 'fa-times-circle',
                                'Time Change' => 'fa-clock-o',
                                default => 'fa-info-circle'
                            }; ?>"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:14px; color:#1a5276; font-weight:700; text-transform:uppercase; margin-bottom:4px;"><?php echo htmlspecialchars($chg['course_code']); ?> – <?php echo htmlspecialchars($chg['course_name']); ?></div>
                            <div style="font-size:15px; font-weight:600; color:#333; margin-bottom:6px;"><?php echo htmlspecialchars($chg['change_type']); ?></div>
                            
                            <div style="display:flex; gap:20px; font-size:13px;">
                                <?php if ($chg['old_value']): ?>
                                <div style="color:#888;"><span style="font-size:11px; text-transform:uppercase; font-weight:600; color:#bbb;">Original:</span> <del><?php echo htmlspecialchars($chg['old_value']); ?></del></div>
                                <?php endif; ?>
                                <?php if ($chg['new_value']): ?>
                                <div style="color:#27ae60; font-weight:600;"><span style="font-size:11px; text-transform:uppercase; font-weight:600; color:#bbb;">New:</span> <?php echo htmlspecialchars($chg['new_value']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 4: Full Table -->
        <div class="tab-content" id="tab-table">
            <div class="card">
                <h2 class="card-title"><i class="fa fa-table"></i> Full Week Overview</h2>
                <?php if (empty($timetable)): ?>
                    <div class="empty-state"><i class="fa fa-calendar"></i><p>No timetable data available.</p></div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead><tr><th>Day</th><th>Time Slot</th><th>Course Code</th><th>Course Name</th><th>Faculty</th><th>Room</th></tr></thead>
                        <tbody>
                            <?php foreach ($timetable as $t): ?>
                            <tr style="<?php echo $t['day_name'] === $today_name ? 'background:#fef9e7;' : ''; ?>">
                                <td><strong><?php echo htmlspecialchars($t['day_name']); ?></strong></td>
                                <td style="white-space:nowrap;"><?php echo htmlspecialchars($t['time_slot']); ?></td>
                                <td><strong style="color:#a4123f;"><?php echo htmlspecialchars($t['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($t['course_name']); ?></td>
                                <td><i class="fa fa-user" style="color:#a4123f; margin-right:4px;"></i> <?php echo htmlspecialchars($t['faculty_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($t['room'] ?? '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tab, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            if(btn) btn.classList.add('active');
        }
        function changeSemester() {
            var yr = document.getElementById('semYear').value;
            var sem = document.getElementById('semType').value;
            window.location.href = 'timetable.php?year=' + yr + '&sem_type=' + sem;
        }
    </script>
</body>
</html>
