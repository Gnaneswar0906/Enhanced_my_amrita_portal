<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_doc') {
        $doc_type = trim($_POST['doc_type'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        if ($doc_type && !empty($_FILES['doc_files']['name'][0])) {
            @mkdir('../uploads/docs', 0777, true);
            foreach ($_FILES['doc_files']['name'] as $key => $fname) {
                if ($_FILES['doc_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($fname, PATHINFO_EXTENSION);
                    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $doc_type) . '_' . $student_id . '_' . time() . '_' . ($key+1) . '.' . $ext;
                    $dest = '../uploads/docs/' . $safe_name;
                    move_uploaded_file($_FILES['doc_files']['tmp_name'][$key], $dest);
                    $stmt = $pdo->prepare('INSERT INTO documents (student_id, doc_type, category, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$student_id, $doc_type, $category, $fname, '/uploads/docs/' . $safe_name, 'student']);
                }
            }
            $msg = 'upload_success';
        } else { $msg = 'upload_error'; }
    } elseif ($_POST['action'] === 'delete_doc') {
        $doc_id = intval($_POST['doc_id'] ?? 0);
        if ($doc_id) {
            $pdo->prepare('DELETE FROM documents WHERE id = ? AND student_id = ?')->execute([$doc_id, $student_id]);
            $msg = 'delete_success';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM documents WHERE student_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$student_id]);
$docs = $stmt->fetchAll();

$stmt2 = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt2->execute([$student_id]);
$student = $stmt2->fetch();

$doc_categories = [];
foreach ($docs as $d) {
    $cat = $d['category'] ?? $d['doc_type'] ?? 'General';
    if (!isset($doc_categories[$cat])) $doc_categories[$cat] = [];
    $doc_categories[$cat][] = $d;
}
$cat_icons = ['Academic' => 'fa-graduation-cap', 'Identity' => 'fa-credit-card', 'Financial' => 'fa-money', 'Medical' => 'fa-medkit', 'Certificates' => 'fa-certificate', 'Resume' => 'fa-file-text', 'General' => 'fa-file'];

// Generate application number hash from enrollment_no for display
$app_no = strtoupper(substr(md5($student['enrollment_no']), 0, 7));
// Default program data
$program = 'B.Tech';
$specialization = 'Computer Science and Engineering';
$campus = 'Bengaluru';
$school = 'School of Computing- Bengaluru';
$allotment = 'Merit';
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Documents</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .page-bg { background: #f4f6f8; min-height: 100vh; padding-bottom: 40px; }
        .payment-header { background: #eaedf2; padding: 12px 20px; border-radius: 8px 8px 0 0; color: #444; font-weight: 600; font-size: 16px; margin-bottom: 0; }
        .profile-card { background: #fff; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; padding: 24px; margin-bottom: 24px; }
        .profile-photo-container { width: 180px; flex-shrink: 0; background: #fafafa; border: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: center; padding: 16px; margin-right: 32px; }
        .profile-photo { max-width: 100%; max-height: 160px; object-fit: contain; }
        .profile-details { flex-grow: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 0; border-top: 1px solid #f0f0f0; border-left: 1px solid #f0f0f0; }
        .detail-row { display: contents; }
        .detail-cell { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; border-right: 1px solid #f0f0f0; font-size: 13px; }
        .detail-label { color: #666; font-weight: 500; }
        .detail-val { color: #333; }

        .doc-card { background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:16px; display:flex; gap:14px; align-items:center; margin-bottom:10px; transition:all 0.2s; }
        .doc-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.06); }
        .doc-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
        .doc-icon.pdf { background:#fde8e8; color:#e74c3c; }
        .doc-icon.img { background:#e8f5e9; color:#27ae60; }
        .doc-icon.doc { background:#e3f2fd; color:#1565c0; }
        .doc-icon.other { background:#f5f5f5; color:#666; }
        .doc-actions { display:flex; gap:6px; flex-wrap:wrap; }
        .doc-actions a, .doc-actions button { padding:6px 12px; border-radius:6px; font-size:11px; font-weight:600; text-decoration:none; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:4px; }
        .btn-view { background:#e8f5e9; color:#2e7d32; }
        .btn-download { background:#e3f2fd; color:#1565c0; }
        .btn-delete { background:#fde8e8; color:#e74c3c; }
        /* Preview Modal */
        .preview-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center; }
        .preview-overlay.active { display:flex; }
        .preview-box { background:#fff; border-radius:16px; padding:24px; max-width:90vw; max-height:90vh; overflow:auto; position:relative; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
        .preview-box .close-btn { position:absolute; top:12px; right:16px; background:none; border:none; font-size:24px; cursor:pointer; color:#888; z-index:10; }
        .preview-box img { max-width:100%; max-height:70vh; border-radius:8px; }
        .preview-box iframe { width:100%; min-width:600px; height:70vh; border:none; border-radius:8px; }
    </style>
</head>
<body class="page-bg">
    <nav class="top-navbar"><span class="brand">Student Portal</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar" style="margin-bottom:20px;"><a href="../home.php">Home</a> <span class="sep">/</span> Documents</div>
    
    <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">

        <!-- Academic Details Profile Card -->
        <div class="payment-header">Academic Details</div>
        <div class="profile-card">
            <div class="profile-photo-container">
                <?php $photo = !empty($student['photo_url']) ? $student['photo_url'] : '../images/default_avatar.png'; ?>
                <img src="<?php echo htmlspecialchars($photo); ?>" alt="Student Photo" class="profile-photo">
            </div>
            <div class="profile-details">
                <div class="detail-cell detail-label">Name</div><div class="detail-cell detail-val"><?php echo htmlspecialchars($student['name']); ?></div>
                <div class="detail-cell detail-label">Application No</div><div class="detail-cell detail-val"><?php echo $app_no; ?></div>
                
                <div class="detail-cell detail-label">Date of Birth</div><div class="detail-cell detail-val"><?php echo $student['dob'] ? date('Y-m-d', strtotime($student['dob'])) : '—'; ?></div>
                <div class="detail-cell detail-label">Program</div><div class="detail-cell detail-val"><?php echo $program; ?></div>
                
                <div class="detail-cell detail-label">Mobile Number</div><div class="detail-cell detail-val"><?php echo htmlspecialchars($student['phone']); ?></div>
                <div class="detail-cell detail-label">Specialization</div><div class="detail-cell detail-val"><?php echo $specialization; ?></div>
                
                <div class="detail-cell detail-label">Email</div><div class="detail-cell detail-val"><?php echo htmlspecialchars($student['email']); ?></div>
                <div class="detail-cell detail-label">Batch</div><div class="detail-cell detail-val"><?php echo htmlspecialchars($student['batch'] ?? 'BL23UCSEB'); ?></div>
                
                <div class="detail-cell detail-label">Campus</div><div class="detail-cell detail-val"><?php echo $campus; ?></div>
                <div class="detail-cell detail-label">School</div><div class="detail-cell detail-val"><?php echo $school; ?></div>
                
                <div class="detail-cell detail-label">Allotment</div><div class="detail-cell detail-val"><?php echo $allotment; ?></div>
                <div class="detail-cell detail-label">Roll Number</div><div class="detail-cell detail-val"><?php echo htmlspecialchars($student['enrollment_no']); ?></div>
            </div>
        </div>

        <?php if ($msg === 'upload_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Document uploaded successfully!</div>
        <?php elseif ($msg === 'upload_error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Please select a document type and file to upload.</div>
        <?php elseif ($msg === 'delete_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Document deleted.</div>
        <?php endif; ?>

        <div class="alert-banner info" style="margin-bottom:20px;"><i class="fa fa-info-circle"></i><span class="alert-text">Upload your resumes, certificates, images, and important documents. These are accessible to you, your faculty advisor, and admin.</span></div>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('all')"><i class="fa fa-folder-open"></i> Document Upload (<?php echo count($docs); ?>)</button>
            <button class="tab-btn" onclick="switchTab('upload')"><i class="fa fa-cloud-upload"></i> Upload New</button>
            <div style="margin-left:auto; display:flex; align-items:center; padding-right:10px; font-size:13px; color:#666;">
                Document Verification Final Status - <span style="color:#27ae60; font-weight:bold; margin-left:4px;">Verified</span>
            </div>
        </div>

        <!-- TAB 1: All Documents -->
        <div class="tab-content active" id="tab-all">
            <?php if (empty($docs)): ?>
                <div class="card"><div class="empty-state"><i class="fa fa-folder-open"></i><p>No documents uploaded yet. Upload your certificates, resumes, and important files.</p></div></div>
            <?php else: ?>
                <?php foreach ($doc_categories as $cat => $cat_docs): ?>
                <div class="card" style="margin-bottom:20px;">
                    <h2 class="card-title" style="font-size:16px; color:#333; margin-bottom:14px; border-bottom:1px solid #eee; padding-bottom:8px;">
                        <i class="fa <?php echo $cat_icons[$cat] ?? 'fa-file'; ?>" style="color:#a4123f;"></i> <?php echo htmlspecialchars($cat); ?> (<?php echo count($cat_docs); ?>)
                    </h2>
                    <?php foreach ($cat_docs as $d):
                        $ext = strtolower(pathinfo($d['file_name'], PATHINFO_EXTENSION));
                        $iconClass = in_array($ext, ['pdf']) ? 'pdf' : (in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'img' : (in_array($ext, ['doc','docx']) ? 'doc' : 'other'));
                        $icon = match($iconClass) { 'pdf' => 'fa-file-pdf-o', 'img' => 'fa-file-image-o', 'doc' => 'fa-file-word-o', default => 'fa-file-o' };
                        $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                        $isPdf = $ext === 'pdf';
                        $filePath = '..' . htmlspecialchars($d['file_path']);
                    ?>
                    <div class="doc-card">
                        <div class="doc-icon <?php echo $iconClass; ?>"><i class="fa <?php echo $icon; ?>"></i></div>
                        <div style="flex:1;">
                            <div style="font-weight:600; font-size:14px; color:#333;"><?php echo htmlspecialchars($d['doc_type']); ?></div>
                            <div style="font-size:12px; color:#888;"><?php echo htmlspecialchars($d['file_name']); ?> • Uploaded <?php echo date('d M Y', strtotime($d['uploaded_at'])); ?> by <?php echo $d['uploaded_by']; ?></div>
                            <?php if (isset($d['verification_status'])): ?>
                            <div style="margin-top:4px;">
                                <?php $vs = $d['verification_status'] ?? 'Pending'; $vc = match($vs) { 'Verified' => 'badge-approved', 'Rejected' => 'badge-failed', default => 'badge-pending' }; ?>
                                <span class="badge <?php echo $vc; ?>" style="font-size:10px;"><?php echo $vs; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="doc-actions">
                            <a href="<?php echo $filePath; ?>" target="_blank" class="btn-view" onclick="event.preventDefault(); previewFile('<?php echo $filePath; ?>','<?php echo $ext; ?>','<?php echo htmlspecialchars($d['doc_type']); ?>')"><i class="fa fa-eye"></i> View</a>
                            <a href="<?php echo $filePath; ?>" download class="btn-download"><i class="fa fa-download"></i> Download</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this document?');">
                                <input type="hidden" name="action" value="delete_doc">
                                <input type="hidden" name="doc_id" value="<?php echo $d['id']; ?>">
                                <button type="submit" class="btn-delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- TAB 2: Upload New Document -->
        <div class="tab-content" id="tab-upload">
            <div class="card form-section" style="margin-bottom:20px;">
                <h3><i class="fa fa-cloud-upload"></i> Upload Document</h3>
                <p style="font-size:13px; color:#666; margin-bottom:16px;">Upload your certificates, resumes, images, and important documents. These will be accessible to you, your faculty advisor, and the admin.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_doc">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Document Type</label>
                            <input type="text" class="form-control" name="doc_type" placeholder="e.g. Resume, SSC Marksheet, Internship Certificate" required>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select class="form-control" name="category">
                                <option value="Academic">Academic</option>
                                <option value="Identity">Identity Documents</option>
                                <option value="Financial">Financial</option>
                                <option value="Medical">Medical</option>
                                <option value="Certificates">Certificates</option>
                                <option value="Resume">Resume / CV</option>
                                <option value="General" selected>General</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:18px;">
                        <label>Select Files (Multiple allowed)</label>
                        <input type="file" class="form-control" name="doc_files[]" accept=".pdf,.png,.jpg,.jpeg,.gif,.doc,.docx,.xlsx,.pptx" required multiple style="padding:10px; border:2px dashed #d0d0d0; border-radius:10px; background:#fafafa; cursor:pointer; width:100%;">
                    </div>
                    <button type="submit" class="submit-btn"><i class="fa fa-upload"></i> Upload Document</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="preview-overlay" id="previewOverlay" onclick="if(event.target===this)closePreview()">
        <div class="preview-box">
            <button class="close-btn" onclick="closePreview()">&times;</button>
            <h3 id="previewTitle" style="margin:0 0 16px; font-size:16px; color:#a4123f;"></h3>
            <div id="previewContent"></div>
            <div style="margin-top:16px; text-align:center;">
                <a id="previewDownload" href="#" download class="submit-btn" style="display:inline-block; text-decoration:none; padding:10px 24px;"><i class="fa fa-download"></i> Download File</a>
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
    function previewFile(path, ext, title) {
        var content = document.getElementById('previewContent');
        document.getElementById('previewTitle').textContent = title;
        document.getElementById('previewDownload').href = path;
        if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
            content.innerHTML = '<img src="'+path+'" alt="'+title+'">';
        } else if (ext === 'pdf') {
            content.innerHTML = '<iframe src="'+path+'"></iframe>';
        } else {
            content.innerHTML = '<div style="text-align:center; padding:40px; color:#888;"><i class="fa fa-file-o" style="font-size:48px; margin-bottom:12px; display:block;"></i><p>Preview not available for this file type.<br>Please download to view.</p></div>';
        }
        document.getElementById('previewOverlay').classList.add('active');
    }
    function closePreview() {
        document.getElementById('previewOverlay').classList.remove('active');
        document.getElementById('previewContent').innerHTML = '';
    }
    </script>
<script src="../js/upload_validator.js"></script>
</body>
</html>

