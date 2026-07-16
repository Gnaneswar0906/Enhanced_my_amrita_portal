<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = trim($_POST['incident_type'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $date = $_POST['incident_date'] ?? '';
    if ($type && $desc && $date) {
        $stmt = $pdo->prepare('INSERT INTO incidents (student_id, incident_type, description, incident_date) VALUES (?, ?, ?, ?)');
        $stmt->execute([$student_id, $type, $desc, $date]);
        $msg = 'success';
    } else {
        $msg = 'error';
    }
}

$stmt = $pdo->prepare('SELECT * FROM incidents WHERE student_id = ? ORDER BY created_at DESC');
$stmt->execute([$student_id]);
$incidents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita - Incidents</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <nav class="top-navbar">
        <span class="brand">Student Portal (Beta)</span>
        <div class="nav-links">
            <span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>

    <div class="breadcrumb-bar">
        <a href="../home.php">Home</a> <span class="sep">/</span> Incidents
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-exclamation-circle"></i> Incidents</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Incident reported successfully!</div>
        <?php elseif ($msg === 'error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all fields.</div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">Incident Reports</h2>
            <?php if (empty($incidents)): ?>
                <div class="empty-state"><i class="fa fa-exclamation-circle"></i><p>No incidents reported.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>Type</th><th>Description</th><th>Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidents as $i => $inc): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($inc['incident_type']); ?></strong></td>
                            <td><?php echo htmlspecialchars($inc['description']); ?></td>
                            <td><?php echo date('d M Y', strtotime($inc['incident_date'])); ?></td>
                            <td>
                                <?php $cls = strtolower(str_replace(' ', '-', $inc['status'])); ?>
                                <span class="badge badge-<?php echo $cls; ?>"><?php echo $inc['status']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Report an Incident</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="incident_type">Incident Type</label>
                        <select class="form-control" id="incident_type" name="incident_type" required>
                            <option value="">Select type...</option>
                            <option value="Lost Property">Lost Property</option>
                            <option value="Infrastructure Issue">Infrastructure Issue</option>
                            <option value="Safety Concern">Safety Concern</option>
                            <option value="Ragging">Ragging</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="incident_date">Incident Date</label>
                        <input type="date" class="form-control" id="incident_date" name="incident_date" required>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" placeholder="Describe the incident..." required></textarea>
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Report Incident</button>
            </form>
        </div>
    </div>

</body>
</html>
