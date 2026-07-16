<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_event') {
        $name = trim($_POST['event_name'] ?? '');
        $type = $_POST['event_type'] ?? 'Other';
        $date = $_POST['event_date'] ?? '';
        $start = $_POST['start_time'] ?? null;
        $end = $_POST['end_time'] ?? null;
        $venue = trim($_POST['venue'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $org = trim($_POST['organizer'] ?? '');
        $link = trim($_POST['registration_link'] ?? '') ?: null;
        $sem = intval($_POST['semester'] ?? 0);
        $batch = $_POST['b_batch'] ?? 'all';
        $branch = $_POST['b_branch'] ?? 'all';
        $section = $_POST['b_section'] ?? 'all';
        $campus = $_POST['campus_wide'] ?? '';

        if ($campus === 'BLR') {
            $batch = 'all'; $branch = 'all'; $section = 'all'; $sem = 0;
        }

        if ($name && $date) {
            $pdo->prepare('INSERT INTO events (event_name, event_type, event_date, start_time, end_time, venue, description, organizer, registration_link, semester, batch, department, section) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$name, $type, $date, $start, $end, $venue, $desc, $org, $link, $sem, $batch, $branch, $section]);
            $msg = 'add_success';
        }
    } elseif ($action === 'delete_event') {
        $eid = intval($_POST['event_id'] ?? 0);
        if ($eid) { $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$eid]); $msg = 'delete_success'; }
    }
}

$events = $pdo->query('SELECT * FROM events ORDER BY event_date DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita Admin - Events</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .delete-btn { background:linear-gradient(135deg,#c0392b,#e74c3c); color:#fff; border:none; padding:5px 12px; border-radius:6px; font-size:11px; font-weight:600; cursor:pointer; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Admin Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Events</div>
    <div class="main-content">
        <div class="page-header"><h1><i class="fa fa-calendar-o"></i> Manage Events</h1><a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a></div>
        <?php if ($msg === 'add_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Event added!</div>
        <?php elseif ($msg === 'delete_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Event deleted.</div><?php endif; ?>

        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Add New Event</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_event">
                <div class="form-row">
                    <div class="form-group"><label>Event Name</label><input type="text" class="form-control" name="event_name" required></div>
                    <div class="form-group"><label>Type</label>
                        <select class="form-control" name="event_type">
                            <option>Cultural</option><option>Technical</option><option>Sports</option><option>Workshop</option><option>Seminar</option><option>Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Date</label><input type="date" class="form-control" name="event_date" required></div>
                    <div class="form-group"><label>Start Time</label><input type="time" class="form-control" name="start_time"></div>
                    <div class="form-group"><label>End Time</label><input type="time" class="form-control" name="end_time"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Venue</label><input type="text" class="form-control" name="venue"></div>
                    <div class="form-group"><label>Organizer</label><input type="text" class="form-control" name="organizer"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Description</label><input type="text" class="form-control" name="description"></div>
                    <div class="form-group"><label>Registration Link</label><input type="url" class="form-control" name="registration_link"></div>
                </div>
                
                <div class="form-group" style="display:flex; gap:10px; align-items:center; margin-top:10px;">
                    <input type="checkbox" name="campus_wide" value="BLR" id="cb_blr">
                    <label for="cb_blr" style="margin:0; font-weight:700; color:#a4123f;">Broadcast to Entire Campus (BLR)</label>
                </div>
                <div style="font-size:12px; font-weight:700; color:#888; text-transform:uppercase; margin:15px 0 5px;">Or target specific cohort:</div>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
                    <select name="b_batch" class="form-control" style="flex:1;">
                        <option value="all">All Batches</option>
                        <?php foreach (['2022-2026', '2023-2027', '2024-2028', '2025-2029'] as $b): ?><option value="<?php echo $b; ?>"><?php echo $b; ?></option><?php endforeach; ?>
                    </select>
                    <select name="b_branch" class="form-control" style="flex:1;">
                        <option value="all">All Branches</option>
                        <option value="Computer Science & Engineering">CSE</option>
                        <option value="Artificial Intelligence & Data Science">AIDS</option>
                        <option value="Robotics & Artificial Intelligence">RAI</option>
                        <option value="Electronics & Communication">ECE</option>
                        <option value="Electrical & Electronics">EEE</option>
                        <option value="Electronics & Computer">EAC</option>
                        <option value="Mechanical Engineering">MECHANICAL</option>
                    </select>
                    <select name="b_section" class="form-control" style="flex:1;">
                        <option value="all">All Sections</option>
                        <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                    </select>
                    <select name="semester" class="form-control" style="flex:1;">
                        <option value="0">All Semesters</option>
                        <?php for ($i=1; $i<=8; $i++): ?><option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option><?php endfor; ?>
                    </select>
                </div>

                <button type="submit" class="submit-btn"><i class="fa fa-bullhorn"></i> Broadcast Event</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title">All Events (<?php echo count($events); ?>)</h2>
            <?php if (empty($events)): ?><div class="empty-state"><i class="fa fa-calendar-o"></i><p>No events.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Event</th><th>Type</th><th>Date</th><th>Target Cohort</th><th>Venue</th><th>Organizer</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($events as $e): 
                    $target = "BLR (Entire Campus)";
                    if ($e['batch'] !== 'all' || $e['department'] !== 'all' || $e['section'] !== 'all' || $e['semester'] > 0) {
                        $target = ($e['batch']!=='all'?$e['batch']:'All') . ' | ' . ($e['department']!=='all'?$e['department']:'All') . ' | Sec ' . ($e['section']!=='all'?$e['section']:'All') . ' | Sem ' . ($e['semester']?:'All');
                    }
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($e['event_name']); ?></strong></td>
                    <td><span class="badge badge-approved"><?php echo $e['event_type']; ?></span></td>
                    <td><?php echo date('d M Y', strtotime($e['event_date'])); ?></td>
                    <td style="font-size:11px; color:#666;"><?php echo htmlspecialchars($target); ?></td>
                    <td><?php echo htmlspecialchars($e['venue']); ?></td>
                    <td><?php echo htmlspecialchars($e['organizer']); ?></td>
                    <td><form method="POST" style="display:inline;" onsubmit="return confirm('Delete this event?');"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" value="<?php echo $e['id']; ?>"><button type="submit" class="delete-btn"><i class="fa fa-trash"></i></button></form></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
