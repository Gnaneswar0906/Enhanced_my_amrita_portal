<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_session') {
    $pdate = $_POST['preferred_date'] ?? '';
    $slot  = trim($_POST['time_slot'] ?? '');
    $cat   = trim($_POST['reason_category'] ?? 'Other');
    $desc  = trim($_POST['description'] ?? '');
    if ($pdate && $slot) {
        $stmt = $pdo->prepare('INSERT INTO counselling_requests (student_id, preferred_date, time_slot, reason_category, description) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$student_id, $pdate, $slot, $cat, $desc]);
        $msg = 'success';
    } else { $msg = 'error'; }
}

$stmt = $pdo->prepare('SELECT * FROM counselling_requests WHERE student_id = ? ORDER BY created_at DESC');
$stmt->execute([$student_id]);
$requests = $stmt->fetchAll();
$pipeline_steps = ['Pending','Scheduled','Completed'];
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Counselling</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Counselling</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-heart"></i> Counselling Services</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Counselling session booked successfully!</div>
        <?php elseif ($msg === 'error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all required fields.</div>
        <?php endif; ?>

        <div class="alert-banner info"><i class="fa fa-info-circle"></i><span class="alert-text">All counselling sessions are confidential. You can book sessions for academic guidance, personal issues, career planning, or mental health support.</span></div>

        <!-- Requests -->
        <div class="card">
            <h2 class="card-title">My Requests</h2>
            <?php if (empty($requests)): ?>
                <div class="empty-state"><i class="fa fa-heart"></i><p>No counselling requests yet.</p></div>
            <?php else: ?>
                <?php foreach ($requests as $r): ?>
                <div style="background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:20px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <div>
                            <strong style="font-size:15px;"><?php echo htmlspecialchars($r['reason_category']); ?></strong>
                            <div style="font-size:12px; color:#888; margin-top:4px;"><?php echo date('d M Y', strtotime($r['preferred_date'])) . ' • ' . htmlspecialchars($r['time_slot']); ?></div>
                        </div>
                        <span class="badge badge-<?php echo strtolower($r['status']); ?>"><?php echo $r['status']; ?></span>
                    </div>
                    <?php if ($r['description']): ?>
                        <div style="font-size:13px; color:#555; margin-bottom:14px;"><?php echo htmlspecialchars($r['description']); ?></div>
                    <?php endif; ?>
                    <?php if ($r['counsellor_name']): ?>
                        <div style="font-size:13px; color:#555;"><i class="fa fa-user-md" style="color:#a4123f;"></i> Counsellor: <strong><?php echo htmlspecialchars($r['counsellor_name']); ?></strong></div>
                    <?php endif; ?>
                    <div class="status-pipeline">
                        <?php
                        $cs = $r['status']; $reached = true;
                        foreach ($pipeline_steps as $step):
                            $is_current = ($step === $cs);
                            $is_cancelled = ($cs === 'Cancelled' && $step === 'Completed');
                            if ($is_current) $reached = false;
                            $cls = $is_cancelled ? 'rejected' : ($is_current ? 'active' : ($reached ? 'completed' : ''));
                        ?>
                        <div class="pipeline-step <?php echo $cls; ?>">
                            <div class="step-dot"><?php if ($cls==='completed') echo '<i class="fa fa-check"></i>'; elseif($cls==='active') echo '<i class="fa fa-clock-o"></i>'; elseif($cls==='rejected') echo '<i class="fa fa-times"></i>'; ?></div>
                            <div class="step-label"><?php echo $is_cancelled ? 'Cancelled' : $step; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Book Session -->
        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Book Counselling Session</h3>
            <form method="POST">
                <input type="hidden" name="action" value="book_session">
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="reason_category" required>
                            <option value="Academic">Academic</option>
                            <option value="Personal">Personal</option>
                            <option value="Career">Career</option>
                            <option value="Mental Health">Mental Health</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Preferred Date</label>
                        <input type="date" class="form-control" name="preferred_date" required>
                    </div>
                    <div class="form-group">
                        <label>Time Slot</label>
                        <select class="form-control" name="time_slot" required>
                            <option value="09:00-10:00">09:00 – 10:00 AM</option>
                            <option value="10:00-11:00">10:00 – 11:00 AM</option>
                            <option value="11:00-12:00">11:00 – 12:00 PM</option>
                            <option value="14:00-15:00">02:00 – 03:00 PM</option>
                            <option value="15:00-16:00">03:00 – 04:00 PM</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Description (Optional)</label>
                    <textarea class="form-control" name="description" placeholder="Briefly describe what you'd like to discuss..." rows="3"></textarea>
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-calendar-plus-o"></i> Book Session</button>
            </form>
        </div>
    </div>
</body>
</html>
