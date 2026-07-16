<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$stmt = $pdo->prepare('SELECT * FROM fee_notifications WHERE student_id = ? ORDER BY FIELD(status, "Overdue","Active","Upcoming","Completed"), deadline ASC');
$stmt->execute([$student_id]);
$fees = $stmt->fetchAll();

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita - Fee Alerts</title>
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
        <a href="../home.php">Home</a> <span class="sep">/</span> Fee Alerts
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-bell"></i> Fee Alerts & Notifications</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <!-- Overdue Alert -->
        <?php
        $overdue = array_filter($fees, function($f) { return $f['status'] === 'Overdue'; });
        if (!empty($overdue)):
        ?>
        <div class="alert-banner danger">
            <i class="fa fa-exclamation-circle"></i>
            <span class="alert-text">You have <strong><?php echo count($overdue); ?></strong> overdue fee payment(s). Please clear them immediately to avoid penalties.</span>
        </div>
        <?php endif; ?>

        <div class="filter-bar">
            <button class="filter-btn active" onclick="filterFee('all', this)">All</button>
            <button class="filter-btn" onclick="filterFee('overdue', this)">Overdue</button>
            <button class="filter-btn" onclick="filterFee('active', this)">Active</button>
            <button class="filter-btn" onclick="filterFee('upcoming', this)">Upcoming</button>
            <button class="filter-btn" onclick="filterFee('completed', this)">Completed</button>
        </div>

        <?php if (empty($fees)): ?>
            <div class="card">
                <div class="empty-state"><i class="fa fa-bell"></i><p>No fee notifications.</p></div>
            </div>
        <?php else: ?>
            <?php foreach ($fees as $f):
                $status_lower = strtolower($f['status']);
                $deadline = $f['deadline'];
                $days_left = (strtotime($deadline) - strtotime($today)) / 86400;
            ?>
            <div class="fee-alert-card <?php echo $status_lower; ?>" data-status="<?php echo $status_lower; ?>">
                <div class="fee-header">
                    <div class="fee-title"><?php echo htmlspecialchars($f['title']); ?></div>
                    <span class="badge badge-<?php echo $status_lower; ?>"><?php echo $f['status']; ?></span>
                </div>
                <div class="fee-type"><?php echo htmlspecialchars($f['fee_type']); ?></div>

                <div class="fee-amounts">
                    <?php if ($f['old_amount']): ?>
                    <div class="fee-amount-item">
                        <span class="fee-amount-label">Previous Amount</span>
                        <span class="fee-amount-value old">₹<?php echo number_format($f['old_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($f['new_amount']): ?>
                    <div class="fee-amount-item">
                        <span class="fee-amount-label"><?php echo $f['old_amount'] ? 'Revised Amount' : 'Amount'; ?></span>
                        <span class="fee-amount-value new">₹<?php echo number_format($f['new_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="fee-desc"><?php echo htmlspecialchars($f['description']); ?></div>

                <div class="fee-deadline">
                    <i class="fa fa-clock-o"></i>
                    <span>Deadline: <?php echo date('d M Y', strtotime($deadline)); ?></span>
                    <?php if ($days_left > 0 && $status_lower !== 'completed'): ?>
                        <span style="color:#888; font-weight:400;">(<?php echo (int)$days_left; ?> days left)</span>
                    <?php elseif ($days_left <= 0 && $status_lower !== 'completed'): ?>
                        <span style="color:#c0392b;">(Overdue)</span>
                    <?php endif; ?>
                </div>

                <?php if ($f['action_required']): ?>
                <div class="fee-action">
                    <i class="fa fa-info-circle"></i> <?php echo htmlspecialchars($f['action_required']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function filterFee(status, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.fee-alert-card').forEach(card => {
            if (status === 'all' || card.getAttribute('data-status') === status) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
    </script>

</body>
</html>
