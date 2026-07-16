<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code     = trim($_POST['course_code'] ?? '');
    $name     = trim($_POST['course_name'] ?? '');
    $comments = trim($_POST['comments'] ?? '');
    // Multi-section ratings
    $r_content   = intval($_POST['r_content'] ?? 0);
    $r_delivery  = intval($_POST['r_delivery'] ?? 0);
    $r_assessment = intval($_POST['r_assessment'] ?? 0);
    $r_resources = intval($_POST['r_resources'] ?? 0);
    $rating = round(($r_content + $r_delivery + $r_assessment + $r_resources) / 4);
    if ($code && $name && $rating >= 1) {
        $stmt = $pdo->prepare('INSERT INTO course_feedback (student_id, course_code, course_name, rating, content_rating, delivery_rating, assessment_rating, resource_rating, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$student_id, $code, $name, $rating, $r_content, $r_delivery, $r_assessment, $r_resources, $comments]);
        $msg = 'success';
    } else { $msg = 'error'; }
}

$stmt = $pdo->prepare('SELECT * FROM course_feedback WHERE student_id = ? ORDER BY created_at DESC');
$stmt->execute([$student_id]);
$feedbacks = $stmt->fetchAll();

$courses = $pdo->prepare('SELECT DISTINCT course_code, course_name FROM attendance WHERE student_id = ?');
$courses->execute([$student_id]);
$courseList = $courses->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Course Feedback</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .feedback-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; }
        .feedback-modal-content { background:#fff; border-radius:20px; padding:36px; max-width:560px; width:95%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
        .rating-section { margin-bottom:18px; }
        .rating-section label { font-size:13px; font-weight:600; color:#333; margin-bottom:6px; display:block; }
        .rating-section .hint { font-size:11px; color:#888; margin-bottom:6px; }
        .rating-stars { display:flex; gap:6px; }
        .rating-stars input[type="radio"] { display:none; }
        .rating-stars label { font-size:28px; color:#ddd; cursor:pointer; transition:color 0.2s; }
        .rating-stars input:checked ~ label { color:#ddd; }
        .rating-stars label:hover, .rating-stars label:hover ~ label { color:#f5a623; }
        .rating-stars input:checked + label, .rs-checked { color:#f5a623 !important; }
        .section-header { padding:10px 0; border-bottom:2px solid #f0f0f0; margin-bottom:14px; color:#a4123f; font-size:14px; font-weight:700; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Course Feedback</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-commenting-o"></i> Course Feedback</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Feedback submitted!</div>
        <?php elseif ($msg === 'error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all fields and rate all sections.</div><?php endif; ?>

        <div class="card">
            <h2 class="card-title">Previous Feedback</h2>
            <?php if (empty($feedbacks)): ?>
                <div class="empty-state"><i class="fa fa-commenting-o"></i><p>No feedback submitted yet.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Course</th><th>Name</th><th>Rating</th><th>Comments</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($feedbacks as $f): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($f['course_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($f['course_name']); ?></td>
                            <td><?php for ($s=1;$s<=5;$s++) echo '<i class="fa fa-star" style="color:'.($s<=$f['rating']?'#f5a623':'#ddd').'; font-size:14px;"></i>'; ?></td>
                            <td style="max-width:250px; white-space:pre-line; font-size:12px;"><?php echo htmlspecialchars($f['comments']); ?></td>
                            <td><?php echo date('d M Y', strtotime($f['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Submit Feedback Button -->
        <div class="card" style="text-align:center; padding:30px;">
            <h3 style="margin:0 0 8px; color:#333;">Share Your Course Feedback</h3>
            <p style="font-size:13px; color:#888; margin-bottom:16px;">Help us improve our courses by submitting detailed feedback</p>
            <button onclick="document.getElementById('feedbackModal').style.display='flex'" class="submit-btn" style="padding:12px 28px;"><i class="fa fa-edit"></i> Submit Course Feedback</button>
        </div>

        <!-- Google Form-style Modal -->
        <div class="feedback-modal" id="feedbackModal">
            <div class="feedback-modal-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 style="margin:0; color:#a4123f; font-size:20px;"><i class="fa fa-star"></i> Course Feedback Form</h2>
                    <button onclick="document.getElementById('feedbackModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer; color:#888;">&times;</button>
                </div>
                <div style="background:linear-gradient(135deg,#a4123f,#d4264f); padding:16px 20px; border-radius:12px; color:#fff; margin-bottom:24px;">
                    <div style="font-size:16px; font-weight:600;">Amrita Vishwa Vidyapeetham</div>
                    <div style="font-size:12px; opacity:0.8;">Course Evaluation & Feedback – Semester 6</div>
                </div>

                <form method="POST">
                    <input type="hidden" name="course_code" id="fb_code">
                    <input type="hidden" name="course_name" id="fb_name">
                    
                    <div class="section-header"><i class="fa fa-book"></i> Select Course</div>
                    <div class="form-group" style="margin-bottom:20px;">
                        <select class="form-control" required onchange="var o=this.options[this.selectedIndex]; document.getElementById('fb_code').value=o.value; document.getElementById('fb_name').value=o.getAttribute('data-name')||'';">
                            <option value="">Choose a course...</option>
                            <?php foreach ($courseList as $c): ?><option value="<?php echo htmlspecialchars($c['course_code']); ?>" data-name="<?php echo htmlspecialchars($c['course_name']); ?>"><?php echo htmlspecialchars($c['course_code'] . ' – ' . $c['course_name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <div class="section-header"><i class="fa fa-file-text-o"></i> Course Content Quality</div>
                    <div class="rating-section">
                        <label>How would you rate the course content?</label>
                        <div class="hint">Relevance, depth, and organization of the material</div>
                        <div class="star-rating"><?php for($s=5;$s>=1;$s--): ?><input type="radio" name="r_content" value="<?php echo $s; ?>" id="rc<?php echo $s; ?>" required><label for="rc<?php echo $s; ?>">&#9733;</label><?php endfor; ?></div>
                    </div>

                    <div class="section-header"><i class="fa fa-bullhorn"></i> Teaching & Delivery</div>
                    <div class="rating-section">
                        <label>How effective was the teaching?</label>
                        <div class="hint">Clarity of explanation, pace, and engagement</div>
                        <div class="star-rating"><?php for($s=5;$s>=1;$s--): ?><input type="radio" name="r_delivery" value="<?php echo $s; ?>" id="rd<?php echo $s; ?>" required><label for="rd<?php echo $s; ?>">&#9733;</label><?php endfor; ?></div>
                    </div>

                    <div class="section-header"><i class="fa fa-check-square-o"></i> Assessment & Evaluation</div>
                    <div class="rating-section">
                        <label>How fair were the assessments?</label>
                        <div class="hint">Exams, assignments, and grading fairness</div>
                        <div class="star-rating"><?php for($s=5;$s>=1;$s--): ?><input type="radio" name="r_assessment" value="<?php echo $s; ?>" id="ra<?php echo $s; ?>" required><label for="ra<?php echo $s; ?>">&#9733;</label><?php endfor; ?></div>
                    </div>

                    <div class="section-header"><i class="fa fa-laptop"></i> Learning Resources</div>
                    <div class="rating-section">
                        <label>How useful were the resources?</label>
                        <div class="hint">Notes, lab materials, reference books, online resources</div>
                        <div class="star-rating"><?php for($s=5;$s>=1;$s--): ?><input type="radio" name="r_resources" value="<?php echo $s; ?>" id="rr<?php echo $s; ?>" required><label for="rr<?php echo $s; ?>">&#9733;</label><?php endfor; ?></div>
                    </div>

                    <div class="section-header"><i class="fa fa-pencil"></i> Additional Comments</div>
                    <div class="form-group" style="margin-bottom:20px;">
                        <textarea class="form-control" name="comments" placeholder="Share any additional feedback, suggestions, or concerns..." rows="3"></textarea>
                    </div>

                    <button type="submit" class="submit-btn" style="width:100%; padding:14px;"><i class="fa fa-paper-plane"></i> Submit Feedback</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
