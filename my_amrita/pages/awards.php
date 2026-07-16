<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
// Handle award submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'submit_award') {
        $atype = trim($_POST['award_type'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $word_path = '';
        if (!empty($_FILES['word_docs']['name'][0])) {
            @mkdir('../uploads/awards', 0777, true);
            $word_paths = [];
            foreach ($_FILES['word_docs']['name'] as $key => $fn) {
                if ($_FILES['word_docs']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($fn, PATHINFO_EXTENSION);
                    $newName = 'award_doc_' . $student_id . '_' . time() . '_' . ($key+1) . '.' . $ext;
                    $dest = '../uploads/awards/' . $newName;
                    move_uploaded_file($_FILES['word_docs']['tmp_name'][$key], $dest);
                    $word_paths[] = '/uploads/awards/' . $newName;
                }
            }
            $word_path = !empty($word_paths) ? $word_paths[0] : '';
        }
        if ($title && $atype) {
            $pdo->prepare('INSERT INTO award_submissions (student_id, award_type, title, description, word_doc_path) VALUES (?,?,?,?,?)')
                ->execute([$student_id, $atype, $title, $desc, $word_path]);
            $sub_id = $pdo->lastInsertId();
            if (!empty($_FILES['certificates']['name'][0])) {
                @mkdir('../uploads/awards', 0777, true);
                foreach ($_FILES['certificates']['name'] as $key => $cfn) {
                    if ($_FILES['certificates']['error'][$key] === UPLOAD_ERR_OK) {
                        $cext = pathinfo($cfn, PATHINFO_EXTENSION);
                        $cName = 'award_cert_' . $student_id . '_' . time() . '_' . ($key+1) . '.' . $cext;
                        $cdest = '../uploads/awards/' . $cName;
                        move_uploaded_file($_FILES['certificates']['tmp_name'][$key], $cdest);
                        $pdo->prepare("INSERT INTO file_attachments (entity_type, entity_id, file_name, file_path, uploaded_by) VALUES ('award', ?, ?, ?, ?)")
                            ->execute([$sub_id, $cfn, '/uploads/awards/' . $cName, $student_id]);
                    }
                }
            }
            $msg = 'award_submitted';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM awards WHERE student_id = ? ORDER BY award_date DESC');
$stmt->execute([$student_id]);
$awards = $stmt->fetchAll();

// Get student award submissions
$award_subs = [];
try {
    $stmt_as = $pdo->prepare('SELECT * FROM award_submissions WHERE student_id = ? ORDER BY created_at DESC');
    $stmt_as->execute([$student_id]);
    $award_subs = $stmt_as->fetchAll();
} catch(Exception $e) {}

$stmt = $pdo->prepare('SELECT * FROM grace_marks WHERE student_id = ? ORDER BY applied_date DESC');
$stmt->execute([$student_id]);
$grace = $stmt->fetchAll();

$sgpa_original = !empty($grace) ? $grace[0]['sgpa_before'] : 0;
$sgpa_final = !empty($grace) ? end($grace)['sgpa_after'] : 0;
$sgpa_improvement = $sgpa_final - $sgpa_original;
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Awards & Grace Marks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Awards & Grace Marks</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-trophy"></i> Awards, Publications & Grace Marks</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'award_submitted'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Award submission sent to faculty advisor for review!</div>
        <?php endif; ?>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('awards')"><i class="fa fa-trophy"></i> Awards & Publications</button>
            <button class="tab-btn" onclick="switchTab('submit')"><i class="fa fa-upload"></i> Submit Award</button>
            <button class="tab-btn" onclick="switchTab('mysubs')"><i class="fa fa-list"></i> My Submissions (<?php echo count($award_subs); ?>)</button>
            <button class="tab-btn" onclick="switchTab('grace')"><i class="fa fa-star"></i> Grace Marks</button>
        </div>

        <!-- TAB 1: Awards -->
        <div class="tab-content active" id="tab-awards">
            <?php if (empty($awards)): ?>
                <div class="card"><div class="empty-state"><i class="fa fa-trophy"></i><p>No awards or achievements recorded.</p></div></div>
            <?php else: ?>
                <div class="awards-grid">
                    <?php foreach ($awards as $a): ?>
                    <div class="award-card">
                        <div class="trophy-icon"><i class="fa fa-trophy"></i></div>
                        <h3><?php echo htmlspecialchars($a['award_name']); ?></h3>
                        <p><?php echo htmlspecialchars($a['description']); ?></p>
                        <div class="award-date"><i class="fa fa-calendar"></i> <?php echo date('d M Y', strtotime($a['award_date'])); ?></div>

                        <!-- Certificate & Approval Status -->
                        <div style="margin-top:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                            <?php if (!empty($a['certificate_file'])): ?>
                                <a href="<?php echo htmlspecialchars($a['certificate_file']); ?>" style="color:#a4123f; font-size:12px; font-weight:600;">
                                    <i class="fa fa-file-pdf-o"></i> Certificate
                                </a>
                            <?php else: ?>
                                <span style="font-size:12px; color:#999;"><i class="fa fa-upload"></i> No certificate</span>
                            <?php endif; ?>
                            <?php
                            $as = $a['approval_status'] ?? 'Pending';
                            $asCls = match($as) { 'Approved' => 'badge-approved', 'Rejected' => 'badge-failed', default => 'badge-pending' };
                            ?>
                            <span class="badge <?php echo $asCls; ?>"><?php echo $as; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: Submit Award -->
        <div class="tab-content" id="tab-submit">
            <div class="card form-section">
                <h3><i class="fa fa-upload"></i> Submit Award / Publication for Grace Marks</h3>
                <p style="font-size:13px; color:#666; margin-bottom:16px;">Fill out the details and upload the required documents. Your submission goes to the Faculty Advisor and Admin for review. Grace marks will be awarded upon approval.</p>
                <div class="alert-banner info" style="margin-bottom:16px;"><i class="fa fa-info-circle"></i><span class="alert-text">Download the <a href="../uploads/templates/award_template.docx" style="color:#a4123f; font-weight:600;">Word Document Template</a>, fill it, and re-upload below.</span></div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_award">
                    <div class="form-row">
                        <div class="form-group"><label>Award Type</label>
                            <select class="form-control" name="award_type" required>
                                <option value="">Select type...</option>
                                <option value="Paper Publication">Paper Publication</option>
                                <option value="Hackathon">Hackathon / Competition</option>
                                <option value="Sports">Sports Achievement</option>
                                <option value="Cultural">Cultural Achievement</option>
                                <option value="Internship">Internship / Industry Project</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" placeholder="Award or publication title" required></div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;"><label>Description</label><textarea class="form-control" name="description" placeholder="Brief description of your achievement..." rows="3"></textarea></div>
                    <div class="form-row">
                        <div class="form-group"><label>Word Documents (.doc/.docx - Multiple allowed)</label><input type="file" class="form-control" name="word_docs[]" accept=".doc,.docx,.pdf" multiple style="padding:8px;"></div>
                        <div class="form-group"><label>Certificates / Proofs (PDF/Image - Multiple allowed)</label><input type="file" class="form-control" name="certificates[]" accept=".pdf,.jpg,.jpeg,.png" multiple style="padding:8px;"></div>
                    </div>
                    <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit for Review</button>
                </form>
            </div>
        </div>

        <!-- TAB: My Submissions -->
        <div class="tab-content" id="tab-mysubs">
            <div class="card">
                <h2 class="card-title">My Award Submissions</h2>
                <?php if (empty($award_subs)): ?>
                    <div class="empty-state"><i class="fa fa-upload"></i><p>No submissions yet. Use the "Submit Award" tab to get started.</p></div>
                <?php else: ?>
                    <?php foreach ($award_subs as $as): ?>
                    <div style="background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:16px; margin-bottom:12px; border-left:4px solid <?php echo match($as['status']) { 'Approved'=>'#27ae60', 'Rejected'=>'#e74c3c', 'Under Review'=>'#3498db', default=>'#f5a623' }; ?>;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-size:11px; color:#a4123f; font-weight:600; text-transform:uppercase;"><?php echo htmlspecialchars($as['award_type']); ?></div>
                                <strong style="font-size:15px;"><?php echo htmlspecialchars($as['title']); ?></strong>
                                <?php if ($as['description']): ?><div style="font-size:12px; color:#666; margin-top:4px;"><?php echo htmlspecialchars($as['description']); ?></div><?php endif; ?>
                            </div>
                            <span class="badge badge-<?php echo strtolower(str_replace(' ','-',$as['status'])); ?>"><?php echo $as['status']; ?></span>
                        </div>
                        <div style="margin-top:8px; display:flex; gap:12px; flex-wrap:wrap; font-size:12px;">
                            <?php if ($as['word_doc_path']): ?><a href="<?php echo htmlspecialchars($as['word_doc_path']); ?>" style="color:#1565c0; font-weight:600;" download><i class="fa fa-file-word-o"></i> Word Doc</a><?php endif; ?>
                            <?php if ($as['grace_marks'] > 0): ?><span style="color:#27ae60; font-weight:600;"><i class="fa fa-star"></i> Grace Marks: +<?php echo number_format($as['grace_marks'], 1); ?></span><?php endif; ?>
                            <?php if ($as['review_notes']): ?><span style="color:#666;"><i class="fa fa-comment"></i> <?php echo htmlspecialchars($as['review_notes']); ?></span><?php endif; ?>
                        </div>
                        <div style="font-size:11px; color:#888; margin-top:6px;">Submitted: <?php echo date('d M Y', strtotime($as['created_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 2: Grace Marks -->
        <div class="tab-content" id="tab-grace">
            <?php if (!empty($grace)): ?>
            <div class="sgpa-display">
                <div class="sgpa-card secondary"><div class="sgpa-label">SGPA Before</div><div class="sgpa-value"><?php echo number_format($sgpa_original, 2); ?></div><div class="sgpa-sub">Original</div></div>
                <div class="sgpa-card"><div class="sgpa-label">SGPA After</div><div class="sgpa-value"><?php echo number_format($sgpa_final, 2); ?></div><div class="sgpa-sub">+<?php echo number_format($sgpa_improvement, 2); ?> improvement</div></div>
            </div>
            <?php endif; ?>
            <div class="card">
                <h2 class="card-title">Grace Marks Details</h2>
                <?php if (empty($grace)): ?>
                    <div class="empty-state"><i class="fa fa-star"></i><p>No grace marks applied yet.</p></div>
                <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Award/Publication</th><th>Category</th><th>Grace Marks</th><th>Applied To</th><th>Old → New</th><th>Grade</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($grace as $gm): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($gm['award_or_publication']); ?></strong></td>
                                <td><?php $cat_colors = ['Award'=>'badge-approved','Paper Publication'=>'badge-in-progress','Hackathon'=>'badge-pending','Sports'=>'badge-reported','Cultural'=>'badge-result-declared']; $cc = $cat_colors[$gm['category']] ?? 'badge-pending'; ?><span class="badge <?php echo $cc; ?>"><?php echo $gm['category']; ?></span></td>
                                <td><strong>+<?php echo number_format($gm['marks_awarded'], 1); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($gm['applied_to_course']); ?></strong><br><small><?php echo htmlspecialchars($gm['applied_to_course_name']); ?></small></td>
                                <td><span style="text-decoration:line-through; color:#999;"><?php echo number_format($gm['old_total'], 1); ?></span> <i class="fa fa-arrow-right" style="margin:0 6px; color:#a4123f; font-size:10px;"></i> <strong style="color:#27ae60;"><?php echo number_format($gm['new_total'], 1); ?></strong></td>
                                <td><?php if ($gm['old_grade'] !== $gm['new_grade']): ?><span style="color:#999;"><?php echo $gm['old_grade']; ?></span> <i class="fa fa-arrow-right" style="margin:0 4px; font-size:10px; color:#a4123f;"></i> <span class="badge badge-approved"><?php echo $gm['new_grade']; ?></span><?php else: ?><span class="badge badge-in-progress"><?php echo $gm['new_grade']; ?></span><?php endif; ?></td>
                                <td><span class="badge badge-<?php echo strtolower($gm['status']); ?>"><?php echo $gm['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    function switchTab(tab) { document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active')); document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active')); document.getElementById('tab-' + tab).classList.add('active'); event.target.closest('.tab-btn').classList.add('active'); }
    </script>
<script src="../js/upload_validator.js"></script>
</body>
</html>

