<?php
require_once '../api/auth.php';
require_once '../api/db.php';

// Handle download tracking
if (isset($_GET['download_id'])) {
    $did = intval($_GET['download_id']);
    $dl = $pdo->prepare('SELECT * FROM downloads WHERE id = ?');
    $dl->execute([$did]);
    $file = $dl->fetch();
    if ($file) {
        // Track the download
        $pdo->prepare('INSERT INTO student_downloads (student_id, download_id) VALUES (?, ?)')->execute([$student_id, $did]);
        // Redirect to actual file
        header('Location: ..' . $file['file_path']);
        exit();
    }
}

$stmt = $pdo->prepare('SELECT * FROM downloads ORDER BY uploaded_at DESC');
$stmt->execute();
$downloads = $stmt->fetchAll();

// Get student's download history
$my_downloads = [];
try {
    $stmt2 = $pdo->prepare('SELECT sd.*, d.category as dl_category, d.title as file_name FROM student_downloads sd LEFT JOIN downloads d ON sd.download_id = d.id WHERE sd.student_id = ? ORDER BY sd.downloaded_at DESC');
    $stmt2->execute([$student_id]);
    $my_downloads = $stmt2->fetchAll();
} catch(Exception $e) {}

// Group by category
$categories = [];
foreach ($downloads as $d) {
    $cat = $d['category'] ?? 'General';
    if (!isset($categories[$cat])) $categories[$cat] = [];
    $categories[$cat][] = $d;
}
$cat_icons = ['Academic' => 'fa-graduation-cap', 'Examination' => 'fa-pencil', 'Hostel' => 'fa-building', 'Finance' => 'fa-money', 'General' => 'fa-file'];
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Downloads</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Downloads</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-download"></i> Downloads</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('available')"><i class="fa fa-folder-open"></i> Available Files</button>
            <button class="tab-btn" onclick="switchTab('history')"><i class="fa fa-history"></i> My Downloads (<?php echo count($my_downloads); ?>)</button>
        </div>

        <!-- TAB 1: Available Downloads -->
        <div class="tab-content active" id="tab-available">
            <?php if (empty($downloads)): ?>
                <div class="card"><div class="empty-state"><i class="fa fa-download"></i><p>No files available for download.</p></div></div>
            <?php else: ?>
                <?php foreach ($categories as $cat => $files): ?>
                <div class="card">
                    <h2 class="card-title"><i class="fa <?php echo $cat_icons[$cat] ?? 'fa-file'; ?>" style="color:#a4123f;"></i> <?php echo htmlspecialchars($cat); ?></h2>
                    <?php foreach ($files as $f): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #f0f0f0;">
                        <div>
                            <strong style="font-size:14px;"><?php echo htmlspecialchars($f['title']); ?></strong>
                            <div style="font-size:11px; color:#888;">Uploaded by <?php echo htmlspecialchars($f['uploaded_by']); ?> • <?php echo date('d M Y', strtotime($f['uploaded_at'])); ?></div>
                        </div>
                        <a href="?download_id=<?php echo $f['id']; ?>" class="submit-btn" style="padding:6px 14px; font-size:12px; text-decoration:none;">
                            <i class="fa fa-download"></i> Download
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- TAB 2: My Downloads History -->
        <div class="tab-content" id="tab-history">
            <div class="card">
                <h2 class="card-title"><i class="fa fa-history" style="color:#a4123f;"></i> Files You've Downloaded</h2>
                <?php if (empty($my_downloads)): ?>
                    <div class="empty-state"><i class="fa fa-download"></i><p>You haven't downloaded any files yet.</p></div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>#</th><th>File Name</th><th>Category</th><th>Downloaded On</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($my_downloads as $i => $md): ?>
                            <tr>
                                <td><?php echo $i+1; ?></td>
                                <td><strong><?php echo htmlspecialchars($md['file_name']); ?></strong></td>
                                <td><span class="badge badge-in-progress"><?php echo htmlspecialchars($md['dl_category'] ?? 'General'); ?></span></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($md['downloaded_at'])); ?></td>
                                <td>
                                    <?php if ($md['download_id']): ?>
                                    <a href="?download_id=<?php echo $md['download_id']; ?>" style="color:#a4123f; font-size:12px;"><i class="fa fa-download"></i> Re-download</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.closest('.tab-btn').classList.add('active');
    }
    </script>
</body>
</html>
