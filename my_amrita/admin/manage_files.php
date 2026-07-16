<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header('Location: ../login.php'); exit(); }
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_file') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        if ($title && isset($_FILES['download_file']) && $_FILES['download_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/downloads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . preg_replace('/[^A-Za-z0-9.\-]/', '_', basename($_FILES['download_file']['name']));
            $dest = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['download_file']['tmp_name'], $dest)) {
                $fpath = '/uploads/downloads/' . $filename;
                $pdo->prepare('INSERT INTO downloads (title, file_path, category, uploaded_by) VALUES (?,?,?,?)')->execute([$title, $fpath, $category, $admin_name]);
                $msg = 'add_success';
            }
        }
    } elseif ($action === 'delete_file') {
        $fid = intval($_POST['file_id'] ?? 0);
        if ($fid) { $pdo->prepare('DELETE FROM downloads WHERE id=?')->execute([$fid]); $msg = 'delete_success'; }
    }
}

$files = $pdo->query('SELECT * FROM downloads ORDER BY uploaded_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>Admin - Manage Files</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .delete-btn-sm{background:linear-gradient(135deg,#c0392b,#e74c3c);color:#fff;border:none;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;transition:all .2s;font-family:'Inter',sans-serif;display:inline-block;} .delete-btn-sm:hover{transform:translateY(-1px);}
        .view-btn-sm{background:linear-gradient(135deg,#2980b9,#3498db);color:#fff;border:none;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;display:inline-block;margin-right:4px;} .view-btn-sm:hover{transform:translateY(-1px);color:#fff;}
        .download-btn-sm{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;border:none;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;display:inline-block;margin-right:4px;} .download-btn-sm:hover{transform:translateY(-1px);color:#fff;}
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Admin Panel (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Manage Files</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-file"></i> Manage Downloads</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a>
        </div>

        <?php if ($msg === 'add_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> File added!</div>
        <?php elseif ($msg === 'delete_success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> File deleted.</div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">All Files (<?php echo count($files); ?>)</h2>
            <?php if (empty($files)): ?><div class="empty-state"><i class="fa fa-file"></i><p>No files.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Title</th><th>Category</th><th>Uploaded By</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($files as $i => $f): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><strong><?php echo htmlspecialchars($f['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($f['category']); ?></td>
                            <td><?php echo htmlspecialchars($f['uploaded_by']); ?></td>
                            <td><?php echo date('d M Y', strtotime($f['uploaded_at'])); ?></td>
                            <td>
                                <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" class="view-btn-sm" title="View"><i class="fa fa-eye"></i> View</a>
                                <a href="..<?php echo htmlspecialchars($f['file_path']); ?>" download class="download-btn-sm" title="Download"><i class="fa fa-download"></i> Download</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');"><input type="hidden" name="action" value="delete_file"><input type="hidden" name="file_id" value="<?php echo $f['id']; ?>"><button type="submit" class="delete-btn-sm" title="Delete"><i class="fa fa-trash"></i> Delete</button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Add File</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_file">
                <div class="form-row">
                    <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" required></div>
                    <div class="form-group"><label>Category</label><select class="form-control" name="category"><option value="Academic">Academic</option><option value="Examination">Examination</option><option value="Hostel">Hostel</option><option value="Finance">Finance</option><option value="General">General</option></select></div>
                </div>
                <div class="form-group" style="margin-bottom:14px;"><label>File</label><input type="file" class="form-control" name="download_file" style="padding:10px; border:2px dashed #d0d0d0; border-radius:10px; background:#fafafa; cursor:pointer;" onchange="this.style.borderColor='#27ae60'; this.style.background='#f0fff4';"></div>
                <button type="submit" class="submit-btn"><i class="fa fa-upload"></i> Add File</button>
            </form>
        </div>
    </div>
<script src="../js/upload_validator.js"></script>
</body>
</html>

