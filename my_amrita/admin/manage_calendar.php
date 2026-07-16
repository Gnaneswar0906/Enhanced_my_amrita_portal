<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_event') {
        $title = trim($_POST['event_title'] ?? '');
        $date = $_POST['event_date'] ?? '';
        $end = $_POST['end_date'] ?? null; if ($end === '') $end = null;
        $type = $_POST['event_type'] ?? 'Academic';
        $desc = trim($_POST['description'] ?? '');
        $sem = intval($_POST['semester'] ?? 0);
        $batch = $_POST['b_batch'] ?? 'all';
        $branch = $_POST['b_branch'] ?? 'all';
        $section = $_POST['b_section'] ?? 'all';
        $campus = $_POST['campus_wide'] ?? '';

        if ($campus === 'BLR') {
            $batch = 'all'; $branch = 'all'; $section = 'all'; $sem = 0;
        }

        if ($title && $date) {
            $pdo->prepare('INSERT INTO academic_calendar (event_title, event_date, end_date, event_type, description, semester, batch, department, section) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$title,$date,$end,$type,$desc,$sem,$batch,$branch,$section]);
            $msg = 'add_success';
        }
    } elseif ($action === 'delete_event') {
        $eid = intval($_POST['cal_id'] ?? 0);
        if ($eid) { $pdo->prepare('DELETE FROM academic_calendar WHERE id = ?')->execute([$eid]); $msg = 'delete_success'; }
    }
}

$events = $pdo->query('SELECT * FROM academic_calendar ORDER BY event_date ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita Admin - Academic Calendar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .delete-btn { background:linear-gradient(135deg,#c0392b,#e74c3c); color:#fff; border:none; padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600; cursor:pointer; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Admin Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Academic Calendar</div>
    <div class="main-content">
        <div class="page-header"><h1><i class="fa fa-calendar"></i> Manage Academic Calendar</h1><a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a></div>
        <?php if ($msg === 'add_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Calendar event added!</div>
        <?php elseif ($msg === 'delete_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Event deleted.</div><?php endif; ?>

        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Add Calendar Event</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_event">
                <div class="form-row">
                    <div class="form-group"><label>Event Title</label><input type="text" class="form-control" name="event_title" required></div>
                    <div class="form-group"><label>Type</label>
                        <select class="form-control" name="event_type">
                            <option>Academic</option><option>Examination</option><option>Holiday</option><option>Deadline</option><option>Result</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Start Date</label><input type="date" class="form-control" name="event_date" required></div>
                    <div class="form-group"><label>End Date (optional)</label><input type="date" class="form-control" name="end_date"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Description</label><input type="text" class="form-control" name="description"></div>
                    <div class="form-group" style="display:flex; gap:10px; align-items:center; margin-top:28px;">
                        <input type="checkbox" name="campus_wide" value="BLR" id="cb_blr">
                        <label for="cb_blr" style="margin:0; font-weight:700; color:#a4123f;">Broadcast to Entire Campus (BLR)</label>
                    </div>
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
            <h2 class="card-title">Academic Calendar (<?php echo count($events); ?>)</h2>
            <?php if (empty($events)): ?><div class="empty-state"><i class="fa fa-calendar"></i><p>No events.</p></div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Event</th><th>Type</th><th>Date</th><th>Target Cohort</th><th>Description</th><th>Del</th></tr></thead>
                <tbody>
                <?php foreach ($events as $e): 
                    $batch_t = $e['batch'] ?? 'all';
                    $dept_t = $e['department'] ?? 'all';
                    $sec_t = $e['section'] ?? 'all';
                    $sem_t = $e['semester'] ?? 0;
                    
                    $target = "BLR (Entire Campus)";
                    if ($batch_t !== 'all' || $dept_t !== 'all' || $sec_t !== 'all' || $sem_t > 0) {
                        $target = ($batch_t !== 'all' ? $batch_t : 'All') . ' | ' . ($dept_t !== 'all' ? $dept_t : 'All') . ' | Sec ' . ($sec_t !== 'all' ? $sec_t : 'All') . ' | Sem ' . ($sem_t ?: 'All');
                    }
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($e['event_title'] ?? ''); ?></strong></td>
                    <td><span class="badge badge-approved"><?php echo htmlspecialchars($e['event_type'] ?? ''); ?></span></td>
                    <td><?php echo !empty($e['event_date']) ? date('d M Y', strtotime($e['event_date'])) : ''; ?><?php if (!empty($e['end_date'])): ?> – <?php echo date('d M Y', strtotime($e['end_date'])); ?><?php endif; ?></td>
                    <td style="font-size:11px; color:#666;"><?php echo htmlspecialchars($target); ?></td>
                    <td style="max-width:200px;"><?php echo htmlspecialchars(substr($e['description'] ?? '', 0, 80)); ?></td>
                    <td><form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="cal_id" value="<?php echo $e['id']; ?>"><button type="submit" class="delete-btn"><i class="fa fa-trash"></i></button></form></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
