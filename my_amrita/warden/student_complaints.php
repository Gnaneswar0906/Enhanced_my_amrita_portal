<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'warden') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$warden_name = $_SESSION['user_name'];

// Fetch all complaints/services for this warden's students
$stmt = $pdo->prepare('SELECT s2.*, st.name, st.enrollment_no FROM services s2 JOIN students st ON s2.student_id = st.id WHERE st.warden_name = ? ORDER BY s2.created_at DESC');
$stmt->execute([$warden_name]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Warden - Student Complaints</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-navbar"><span class="brand">Warden Portal</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($warden_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Warden Home</a> <span class="sep">/</span> Student Complaints</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-exclamation-circle"></i> Student Complaints & Services</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>

        <div class="filter-bar">
            <button class="filter-btn active" onclick="filterItems('all', this)">All</button>
            <button class="filter-btn" onclick="filterItems('Service', this)">Services</button>
            <button class="filter-btn" onclick="filterItems('Complaint', this)">Complaints</button>
        </div>

        <div class="card">
            <h2 class="card-title">All Requests (<?php echo count($items); ?>)</h2>
            <?php if (empty($items)): ?>
                <div class="empty-state"><i class="fa fa-check-circle"></i><p>No complaints or service requests.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Student</th><th>Type</th><th>Category</th><th>Description</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                        <tr class="filterable-row" data-category="<?php echo htmlspecialchars($item['category'] ?? 'Service'); ?>">
                            <td><?php echo $i+1; ?></td>
                            <td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo htmlspecialchars($item['enrollment_no']); ?></small></td>
                            <td><?php echo htmlspecialchars($item['service_type']); ?></td>
                            <td><span class="badge <?php echo ($item['category'] ?? '') === 'Complaint' ? 'badge-failed' : 'badge-approved'; ?>"><?php echo $item['category'] ?? 'Service'; ?></span></td>
                            <td style="max-width:250px; font-size:12px;"><?php echo htmlspecialchars($item['description']); ?></td>
                            <td><span class="badge badge-<?php echo strtolower(str_replace(' ','-',$item['status'])); ?>"><?php echo $item['status']; ?></span></td>
                            <td><?php echo date('d M Y', strtotime($item['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function filterItems(cat, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.filterable-row').forEach(row => {
            if (cat === 'all' || row.getAttribute('data-category') === cat) row.style.display = '';
            else row.style.display = 'none';
        });
    }
    </script>
</body>
</html>
