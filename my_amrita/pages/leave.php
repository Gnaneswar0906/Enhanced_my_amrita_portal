<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'apply_leave') {
        $type   = trim($_POST['leave_type'] ?? '');
        $custom = trim($_POST['custom_leave_type'] ?? '');
        $from   = $_POST['from_date'] ?? '';
        $to     = $_POST['to_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $final_type = ($type === 'Other' && $custom) ? $custom : $type;
        if ($final_type && $from && $to && $reason) {
            $stmt = $pdo->prepare('INSERT INTO leaves (student_id, leave_type, from_date, to_date, reason, custom_leave_type) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$student_id, $final_type, $from, $to, $reason, $custom ?: null]);
            $msg = 'leave_success';
        } else { $msg = 'leave_error'; }
    } elseif ($action === 'apply_duty_leave') {
        $from   = $_POST['from_date'] ?? '';
        $to     = $_POST['to_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        if ($from && $to && $reason) {
            // Internally stores as Duty Leave. As per request, this will integrate with class attendance upon admin approval.
            $stmt = $pdo->prepare('INSERT INTO leaves (student_id, leave_type, from_date, to_date, reason) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$student_id, 'Duty Leave', $from, $to, $reason]);
            $msg = 'leave_success';
        } else { $msg = 'leave_error'; }
    } elseif ($action === 'apply_medical') {
        $from = $_POST['from_date'] ?? '';
        $to   = $_POST['to_date'] ?? '';
        $cond = trim($_POST['condition_desc'] ?? '');
        $doc  = trim($_POST['doctor_name'] ?? '');
        $hosp = trim($_POST['hospital'] ?? '');
        if ($from && $to && $cond) {
            $stmt = $pdo->prepare('INSERT INTO medical_leaves (student_id, from_date, to_date, condition_desc, doctor_name, hospital) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$student_id, $from, $to, $cond, $doc, $hosp]);
            $ml_id = $pdo->lastInsertId();
            if (!empty($_FILES['medical_certs']['name'][0])) {
                foreach ($_FILES['medical_certs']['name'] as $key => $fname) {
                    if ($_FILES['medical_certs']['error'][$key] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($fname, PATHINFO_EXTENSION);
                        $newName = 'med_cert_' . $ml_id . '_' . ($key+1) . '.' . $ext;
                        $dest = '../uploads/medical/' . $newName;
                        @mkdir('../uploads/medical', 0777, true);
                        move_uploaded_file($_FILES['medical_certs']['tmp_name'][$key], $dest);
                        $pdo->prepare('INSERT INTO file_attachments (ref_type, ref_id, file_name, file_path) VALUES (?, ?, ?, ?)')->execute(['medical_leave', $ml_id, $fname, '/uploads/medical/' . $newName]);
                    }
                }
            }
            $msg = 'medical_success';
        } else { $msg = 'medical_error'; }
    }
}

// Fetch General Leaves
$stmt_g = $pdo->prepare('SELECT * FROM leaves WHERE student_id = ? AND leave_type != \'Duty Leave\' ORDER BY created_at DESC');
$stmt_g->execute([$student_id]);
$general_leaves = $stmt_g->fetchAll();

// Fetch Duty Leaves
$stmt_d = $pdo->prepare('SELECT * FROM leaves WHERE student_id = ? AND leave_type = \'Duty Leave\' ORDER BY created_at DESC');
$stmt_d->execute([$student_id]);
$duty_leaves = $stmt_d->fetchAll();

// Fetch Medical Leaves
$stmt_m = $pdo->prepare('SELECT ml.*, (SELECT COUNT(*) FROM file_attachments fa WHERE fa.ref_type="medical_leave" AND fa.ref_id=ml.id) as file_count FROM medical_leaves ml WHERE ml.student_id = ? ORDER BY ml.created_at DESC');
$stmt_m->execute([$student_id]);
$medical_leaves = $stmt_m->fetchAll();

?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita - Leaves</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .page-bg { background: #f4f6f8; min-height: 100vh; padding-bottom: 40px; }
        
        /* Styled Table Matching Image 2 */
        .clean-table-container { background: #fff; border-radius: 8px; border: 1px solid #e8e8e8; overflow-x: auto; margin-bottom: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.02); }
        .clean-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .clean-table th { color: #2c3e50; font-weight: 600; font-size: 13px; padding: 14px 16px; text-align: left; border-bottom: 1px solid #eaeaea; }
        .clean-table td { padding: 14px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .clean-table tr:hover td { background: #fcfcfc; }
        
        .id-link { color: #5c5fff; text-decoration: none; font-weight: 600; }
        .id-link:hover { text-decoration: underline; }
        
        #customLeaveField { display:none; margin-top:8px; }
    </style>
</head>
<body class="page-bg">
    <nav class="top-navbar"><span class="brand">Student Portal</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar" style="margin-bottom:20px;"><a href="../home.php">Home</a> <span class="sep">/</span> Leaves</div>
    
    <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">
        <div class="page-header" style="margin-bottom:20px;">
            <h1 style="font-size:24px; color:#333;"><i class="fa fa-calendar"></i> Leaves Management</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'leave_success' || $msg === 'medical_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Application submitted successfully!</div>
        <?php elseif ($msg === 'leave_error' || $msg === 'medical_error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all required fields.</div>
        <?php endif; ?>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('general')"><i class="fa fa-calendar"></i> General Leaves</button>
            <button class="tab-btn" onclick="switchTab('medical')"><i class="fa fa-medkit"></i> Medical Leaves</button>
            <button class="tab-btn" onclick="switchTab('duty')"><i class="fa fa-briefcase"></i> Duty Leaves</button>
        </div>

        <!-- TAB 1: General Leaves -->
        <div class="tab-content active" id="tab-general">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; font-size:16px; color:#333;">General Leave History</h3>
                <button class="submit-btn" style="padding:8px 16px; margin:0; font-size:12px;" onclick="document.getElementById('formGeneral').style.display='block';"><i class="fa fa-plus"></i> Apply Leave</button>
            </div>
            
            <div id="formGeneral" class="card form-section" style="display:none; margin-bottom:20px;">
                <h4>Apply for General Leave</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="apply_leave">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Leave Type</label>
                            <select class="form-control" id="leave_type" name="leave_type" required onchange="toggleCustomLeave()">
                                <option value="">Select type...</option>
                                <option value="Personal">Personal</option>
                                <option value="Family Emergency">Family Emergency</option>
                                <option value="Festival">Festival</option>
                                <option value="Travel">Travel</option>
                                <option value="Other">Other</option>
                            </select>
                            <div id="customLeaveField"><input type="text" class="form-control" name="custom_leave_type" placeholder="Specify your leave type..."></div>
                        </div>
                        <div class="form-group"><label>From Date</label><input type="date" class="form-control" name="from_date" required></div>
                        <div class="form-group"><label>To Date</label><input type="date" class="form-control" name="to_date" required></div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;"><label>Reason</label><textarea class="form-control" name="reason" placeholder="Reason for leave..." required></textarea></div>
                    <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Request</button>
                    <button type="button" class="btn-delete" style="padding:8px 16px; border:none; margin-left:10px; border-radius:6px; background:#f5f5f5; color:#333;" onclick="document.getElementById('formGeneral').style.display='none';">Cancel</button>
                </form>
            </div>

            <div class="clean-table-container">
                <table class="clean-table">
                    <thead><tr><th>ID</th><th>Type</th><th>From</th><th>To</th><th>Status</th><th>Reason</th><th>Created On</th></tr></thead>
                    <tbody>
                        <?php if (empty($general_leaves)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#888;">No general leave records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($general_leaves as $l): ?>
                            <tr>
                                <td><a href="#" class="id-link"><?php echo 80000 + $l['id']; ?></a></td>
                                <td><?php echo htmlspecialchars($l['custom_leave_type'] ?: $l['leave_type']); ?></td>
                                <td><?php echo $l['from_date']; ?></td>
                                <td><?php echo $l['to_date']; ?></td>
                                <td><?php echo htmlspecialchars($l['status']); ?></td>
                                <td><?php echo htmlspecialchars($l['reason']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($l['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: Medical Leaves -->
        <div class="tab-content" id="tab-medical">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; font-size:16px; color:#333;">Medical Leave History</h3>
                <button class="submit-btn" style="padding:8px 16px; margin:0; font-size:12px;" onclick="document.getElementById('formMedical').style.display='block';"><i class="fa fa-plus"></i> Apply Medical Leave</button>
            </div>
            
            <div id="formMedical" class="card form-section" style="display:none; margin-bottom:20px;">
                <h4>Apply for Medical Leave</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="apply_medical">
                    <div class="form-row">
                        <div class="form-group"><label>From Date</label><input type="date" class="form-control" name="from_date" required></div>
                        <div class="form-group"><label>To Date</label><input type="date" class="form-control" name="to_date" required></div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;"><label>Medical Condition</label><input type="text" class="form-control" name="condition_desc" placeholder="e.g. Viral Fever, Surgery, etc." required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Doctor Name</label><input type="text" class="form-control" name="doctor_name" placeholder="Treating doctor's name"></div>
                        <div class="form-group"><label>Hospital/Clinic</label><input type="text" class="form-control" name="hospital" placeholder="Hospital or clinic name"></div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Medical Certificates (Multiple files allowed)</label>
                        <input type="file" class="form-control" name="medical_certs[]" accept=".pdf,.png,.jpg,.jpeg" multiple style="padding:10px; border:2px dashed #d0d0d0; border-radius:10px; cursor:pointer;">
                    </div>
                    <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Request</button>
                    <button type="button" class="btn-delete" style="padding:8px 16px; border:none; margin-left:10px; border-radius:6px; background:#f5f5f5; color:#333;" onclick="document.getElementById('formMedical').style.display='none';">Cancel</button>
                </form>
            </div>

            <div class="clean-table-container">
                <table class="clean-table">
                    <thead><tr><th>ID</th><th>Type</th><th>From</th><th>To</th><th>Status</th><th>Reason</th><th>Created On</th></tr></thead>
                    <tbody>
                        <?php if (empty($medical_leaves)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#888;">No medical leave records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($medical_leaves as $ml): ?>
                            <tr>
                                <td><a href="#" class="id-link"><?php echo 60000 + $ml['id']; ?></a></td>
                                <td>Medical Leave</td>
                                <td><?php echo $ml['from_date']; ?></td>
                                <td><?php echo $ml['to_date']; ?></td>
                                <td><?php echo htmlspecialchars($ml['status']); ?></td>
                                <td><?php echo htmlspecialchars($ml['condition_desc']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($ml['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($medical_leaves)): ?>
            <h4 style="margin-top:30px; margin-bottom:16px; font-size:15px; color:#333;"><i class="fa fa-stethoscope"></i> Detailed Medical Reports</h4>
            <div class="card">
                <?php 
                $pipeline_steps = ['Submitted', 'Under Review', 'Verified', 'Approved'];
                foreach ($medical_leaves as $ml): 
                ?>
                <div style="background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:20px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <div>
                            <strong style="font-size:15px; color:#333;"><?php echo htmlspecialchars($ml['condition_desc']); ?></strong>
                            <div style="font-size:12px; color:#888; margin-top:4px;">
                                <?php echo date('d M Y', strtotime($ml['from_date'])); ?> – <?php echo date('d M Y', strtotime($ml['to_date'])); ?>
                            </div>
                        </div>
                        <?php if ($ml['status'] === 'Approved'): ?>
                            <span style="font-size:22px;"><i class="fa fa-check-circle" style="color:#27ae60;"></i></span>
                        <?php else: ?>
                            <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $ml['status'])); ?>"><?php echo $ml['status']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; gap:24px; font-size:13px; color:#555; margin-bottom:14px; flex-wrap:wrap;">
                        <?php if ($ml['doctor_name']): ?><div><i class="fa fa-user-md" style="color:#a4123f;"></i> <?php echo htmlspecialchars($ml['doctor_name']); ?></div><?php endif; ?>
                        <?php if ($ml['hospital']): ?><div><i class="fa fa-hospital-o" style="color:#a4123f;"></i> <?php echo htmlspecialchars($ml['hospital']); ?></div><?php endif; ?>
                        <?php if ($ml['file_count'] > 0): ?><div><i class="fa fa-paperclip" style="color:#a4123f;"></i> <?php echo $ml['file_count']; ?> attached file(s)</div><?php endif; ?>
                    </div>
                    
                    <div class="status-pipeline">
                        <?php
                        $current_status = $ml['status'];
                        $reached = true;
                        foreach ($pipeline_steps as $step):
                            $is_current = ($step === $current_status);
                            $is_rejected = ($current_status === 'Rejected' && $step === 'Approved');
                            if ($is_current) $reached = false;
                            $cls = '';
                            if ($is_rejected) $cls = 'rejected';
                            elseif ($is_current) { $cls = 'active'; $reached = false; }
                            elseif ($reached) $cls = 'completed';
                        ?>
                        <div class="pipeline-step <?php echo $cls; ?>">
                            <div class="step-dot">
                                <?php if ($cls === 'completed'): ?><i class="fa fa-check"></i>
                                <?php elseif ($cls === 'rejected'): ?><i class="fa fa-times"></i>
                                <?php elseif ($cls === 'active'): ?><i class="fa fa-clock-o"></i>
                                <?php endif; ?>
                            </div>
                            <div class="step-label"><?php echo $is_rejected ? 'Rejected' : $step; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB 3: Duty Leaves -->
        <div class="tab-content" id="tab-duty">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="margin:0; font-size:16px; color:#333;">Duty Leave History</h3>
                <button class="submit-btn" style="padding:8px 16px; margin:0; font-size:12px;" onclick="document.getElementById('formDuty').style.display='block';"><i class="fa fa-plus"></i> Apply Duty Leave</button>
            </div>
            
            <div id="formDuty" class="card form-section" style="display:none; margin-bottom:20px;">
                <h4>Apply for Duty Leave</h4>
                <p style="font-size:12px; color:#666; margin-top:-8px; margin-bottom:16px;">Approved duty leaves automatically count towards your class attendance.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="apply_duty_leave">
                    <div class="form-row">
                        <div class="form-group"><label>From Date</label><input type="date" class="form-control" name="from_date" required></div>
                        <div class="form-group"><label>To Date</label><input type="date" class="form-control" name="to_date" required></div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;"><label>Event / Reason</label><textarea class="form-control" name="reason" placeholder="Describe the event, workshop, or duty..." required></textarea></div>
                    <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Request</button>
                    <button type="button" class="btn-delete" style="padding:8px 16px; border:none; margin-left:10px; border-radius:6px; background:#f5f5f5; color:#333;" onclick="document.getElementById('formDuty').style.display='none';">Cancel</button>
                </form>
            </div>

            <div class="clean-table-container">
                <table class="clean-table">
                    <thead><tr><th>ID</th><th>Type</th><th>From</th><th>To</th><th>Status</th><th>Reason</th><th>Created On</th></tr></thead>
                    <tbody>
                        <?php if (empty($duty_leaves)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#888;">No duty leave records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($duty_leaves as $l): ?>
                            <tr>
                                <td><a href="#" class="id-link"><?php echo 229000 + $l['id']; ?></a></td>
                                <td>Duty Leave</td>
                                <td><?php echo $l['from_date']; ?></td>
                                <td><?php echo $l['to_date']; ?></td>
                                <td><?php echo htmlspecialchars($l['status']); ?></td>
                                <td><?php echo htmlspecialchars($l['reason']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($l['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
    function toggleCustomLeave() {
        var sel = document.getElementById('leave_type');
        document.getElementById('customLeaveField').style.display = sel.value === 'Other' ? 'block' : 'none';
    }
    </script>
<script src="../js/upload_validator.js"></script>
</body>
</html>

