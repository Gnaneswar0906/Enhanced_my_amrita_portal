<?php
require_once '../api/auth.php';
require_once '../api/db.php';

// Handle profile edit request
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'request_edit') {
        $reason = trim($_POST['edit_reason'] ?? 'Profile update needed');
        $stmt = $pdo->prepare('INSERT INTO profile_edit_requests (student_id, reason) VALUES (?, ?)');
        $stmt->execute([$student_id, $reason]);
        $msg = 'edit_requested';
    } elseif ($_POST['action'] === 'update_profile') {
        // Check if edit is allowed
        $chk = $pdo->prepare('SELECT profile_edit_allowed FROM students WHERE id = ?');
        $chk->execute([$student_id]);
        $allowed = $chk->fetchColumn();
        if ($allowed) {
            $stmt = $pdo->prepare('UPDATE students SET 
                phone=?, personal_email=?, college_email=?, address_line=?, village=?, district=?, state=?, country=?, pin_code=?,
                aadhar_number=?, father_name=?, mother_name=?, father_occupation=?, mother_occupation=?, father_phone=?, mother_phone=?,
                guardian_name=?, guardian_phone=?, guardian_occupation=?,
                bank_account=?, ifsc_code=?, bank_name=?, profile_edit_allowed=0
            WHERE id=?');
            $stmt->execute([
                trim($_POST['phone'] ?? ''), trim($_POST['personal_email'] ?? ''), trim($_POST['college_email'] ?? ''),
                trim($_POST['address_line'] ?? ''), trim($_POST['village'] ?? ''), trim($_POST['district'] ?? ''),
                trim($_POST['state'] ?? ''), trim($_POST['country'] ?? ''), trim($_POST['pin_code'] ?? ''),
                trim($_POST['aadhar_number'] ?? ''), trim($_POST['father_name'] ?? ''), trim($_POST['mother_name'] ?? ''),
                trim($_POST['father_occupation'] ?? ''), trim($_POST['mother_occupation'] ?? ''),
                trim($_POST['father_phone'] ?? ''), trim($_POST['mother_phone'] ?? ''),
                trim($_POST['guardian_name'] ?? ''), trim($_POST['guardian_phone'] ?? ''), trim($_POST['guardian_occupation'] ?? ''),
                trim($_POST['bank_account'] ?? ''), trim($_POST['ifsc_code'] ?? ''), trim($_POST['bank_name'] ?? ''),
                $student_id
            ]);
            $msg = 'success';
        } else {
            $msg = 'not_allowed';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$student_id]);
$student = $stmt->fetch();

$stmt2 = $pdo->prepare('SELECT * FROM faculty_advisors WHERE student_id = ?');
$stmt2->execute([$student_id]);
$advisor = $stmt2->fetch();

// Check for pending edit request
$editReq = null;
try {
    $stmt3 = $pdo->prepare('SELECT * FROM profile_edit_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
    $stmt3->execute([$student_id]);
    $editReq = $stmt3->fetch();
} catch(Exception $e) {}

// Teachers section removed — only Faculty Advisor shown

$canEdit = ($student['profile_edit_allowed'] ?? 0) == 1;
$dis = $canEdit ? '' : 'disabled';
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita - Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .section-divider { margin:28px 0 16px; padding-bottom:8px; border-bottom:2px solid #f0f0f0; color:#a4123f; font-size:15px; font-weight:700; }
        .section-divider i { margin-right:8px; }
        .edit-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; }
        .edit-badge.allowed { background:#e8f5e9; color:#2e7d32; }
        .edit-badge.locked { background:#fde8e8; color:#c62828; }
        .edit-badge.pending { background:#fff3cd; color:#856404; }
        .request-edit-btn { background:linear-gradient(135deg,#f5a623,#f7c948); color:#333; border:none; padding:8px 18px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s; font-family:'Inter',sans-serif; }
        .request-edit-btn:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(245,166,35,0.3); }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 28px; }
        .info-item label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:0.5px; font-weight:600; margin-bottom:2px; display:block; }
        .info-item .value { font-size:14px; font-weight:500; color:#333; }
        .teacher-card { background:#fafafa; border:1px solid #e8e8e8; border-radius:10px; padding:14px; display:flex; gap:12px; align-items:center; }
        .teacher-avatar { width:40px; height:40px; background:linear-gradient(135deg,#a4123f,#d4264f); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:14px; flex-shrink:0; }
        @media (max-width:600px) { .info-grid { grid-template-columns:1fr; } }
    </style>
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
        <a href="../home.php">Home</a> <span class="sep">/</span> Profile
    </div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-user"></i> My Profile</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Profile updated successfully! Edit access has been revoked.</div>
        <?php elseif ($msg === 'edit_requested'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Edit request submitted! Admin will review and grant access.</div>
        <?php elseif ($msg === 'not_allowed'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Edit not allowed. Please request edit access from admin first.</div>
        <?php endif; ?>

        <!-- Edit Access Status -->
        <div class="card" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
            <div>
                <strong style="font-size:14px;">Profile Edit Access</strong>
                <?php if ($canEdit): ?>
                    <span class="edit-badge allowed"><i class="fa fa-unlock"></i> Edit Allowed</span>
                <?php elseif ($editReq && $editReq['status'] === 'Pending'): ?>
                    <span class="edit-badge pending"><i class="fa fa-clock-o"></i> Request Pending</span>
                <?php else: ?>
                    <span class="edit-badge locked"><i class="fa fa-lock"></i> Locked</span>
                <?php endif; ?>
            </div>
            <?php if (!$canEdit && (!$editReq || $editReq['status'] !== 'Pending')): ?>
            <form method="POST" style="display:flex; gap:8px; align-items:center;">
                <input type="hidden" name="action" value="request_edit">
                <input type="text" name="edit_reason" class="form-control" placeholder="Reason for edit..." style="max-width:250px; padding:6px 12px; font-size:12px;">
                <button type="submit" class="request-edit-btn"><i class="fa fa-pencil"></i> Request Edit Access</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Personal Information (Read-only) -->
        <div class="card">
            <div class="section-divider" style="border:none; margin-top:0;"><i class="fa fa-user"></i> Personal Information</div>
            <div class="info-grid">
                <div class="info-item"><label>Full Name</label><div class="value"><?php echo htmlspecialchars($student['name']); ?></div></div>
                <div class="info-item"><label>Enrollment No</label><div class="value"><?php echo htmlspecialchars($student['enrollment_no']); ?></div></div>
                <div class="info-item"><label>Department</label><div class="value"><?php echo htmlspecialchars($student['department']); ?></div></div>
                <div class="info-item"><label>Semester</label><div class="value"><?php echo $student['semester']; ?></div></div>
                <div class="info-item"><label>Date of Birth</label><div class="value"><?php echo $student['dob'] ? date('d M Y', strtotime($student['dob'])) : '—'; ?></div></div>
                <div class="info-item"><label>Hostel</label><div class="value"><?php echo htmlspecialchars($student['hostel_room'] ?? '—'); ?></div></div>
                <div class="info-item"><label>College Email</label><div class="value" style="color:#a4123f; font-weight:600;"><?php echo htmlspecialchars($student['college_email'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Personal Email</label><div class="value"><?php echo htmlspecialchars($student['personal_email'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Aadhar Number</label><div class="value"><?php echo htmlspecialchars($student['aadhar_number'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Phone</label><div class="value"><?php echo htmlspecialchars($student['phone'] ?? '—'); ?></div></div>
            </div>
        </div>

        <!-- Family Details (Read-only) -->
        <div class="card">
            <div class="section-divider" style="border:none; margin-top:0;"><i class="fa fa-users"></i> Family Details</div>
            <div class="info-grid">
                <div class="info-item"><label>Father's Name</label><div class="value"><?php echo htmlspecialchars($student['father_name'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Father's Occupation</label><div class="value"><?php echo htmlspecialchars($student['father_occupation'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Father's Phone</label><div class="value"><?php echo htmlspecialchars($student['father_phone'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Mother's Name</label><div class="value"><?php echo htmlspecialchars($student['mother_name'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Mother's Occupation</label><div class="value"><?php echo htmlspecialchars($student['mother_occupation'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Mother's Phone</label><div class="value"><?php echo htmlspecialchars($student['mother_phone'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Guardian Name</label><div class="value"><?php echo htmlspecialchars($student['guardian_name'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Guardian Phone</label><div class="value"><?php echo htmlspecialchars($student['guardian_phone'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Guardian Occupation</label><div class="value"><?php echo htmlspecialchars($student['guardian_occupation'] ?? '—'); ?></div></div>
            </div>
        </div>

        <!-- Address (Read-only) -->
        <div class="card">
            <div class="section-divider" style="border:none; margin-top:0;"><i class="fa fa-map-marker"></i> Address</div>
            <div class="info-grid">
                <div class="info-item"><label>Address Line</label><div class="value"><?php echo htmlspecialchars($student['address_line'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Village/Town</label><div class="value"><?php echo htmlspecialchars($student['village'] ?? '—'); ?></div></div>
                <div class="info-item"><label>District</label><div class="value"><?php echo htmlspecialchars($student['district'] ?? '—'); ?></div></div>
                <div class="info-item"><label>State</label><div class="value"><?php echo htmlspecialchars($student['state'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Country</label><div class="value"><?php echo htmlspecialchars($student['country'] ?? '—'); ?></div></div>
                <div class="info-item"><label>Pin Code</label><div class="value"><?php echo htmlspecialchars($student['pin_code'] ?? '—'); ?></div></div>
            </div>
        </div>

        <!-- Faculty Advisor -->
        <?php if ($advisor): ?>
        <div class="card" style="border-left:4px solid #27ae60;">
            <div class="section-divider" style="border:none; margin-top:0;"><i class="fa fa-user-circle" style="color:#a4123f;"></i> Faculty Advisor</div>
            <div class="info-grid">
                <div class="info-item"><label>Name</label><div class="value" style="font-weight:600;"><?php echo htmlspecialchars($advisor['faculty_name']); ?></div></div>
                <div class="info-item"><label>Designation</label><div class="value"><?php echo htmlspecialchars($advisor['designation']); ?></div></div>
                <div class="info-item"><label>Email</label><div class="value"><a href="mailto:<?php echo htmlspecialchars($advisor['email']); ?>" style="color:#a4123f;"><?php echo htmlspecialchars($advisor['email']); ?></a></div></div>
                <div class="info-item"><label>Phone</label><div class="value"><?php echo htmlspecialchars($advisor['phone']); ?></div></div>
                <div class="info-item"><label>Office</label><div class="value"><i class="fa fa-map-marker" style="color:#a4123f;"></i> <?php echo htmlspecialchars($advisor['office_room']); ?></div></div>
            </div>
        </div>
        <?php endif; ?>




        <!-- Editable Profile Form (only if allowed) -->
        <div class="card form-section">
            <h3><i class="fa fa-edit"></i> Edit Profile
                <?php if (!$canEdit): ?>
                    <span style="font-size:11px; color:#888; font-weight:400;"> — Request edit access above to make changes</span>
                <?php endif; ?>
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">

                <div class="section-divider"><i class="fa fa-envelope"></i> Contact</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" <?php echo $dis; ?>>
                    </div>
                    <div class="form-group">
                        <label>Personal Email</label>
                        <input type="email" class="form-control" name="personal_email" value="<?php echo htmlspecialchars($student['personal_email'] ?? ''); ?>" <?php echo $dis; ?>>
                    </div>
                    <div class="form-group">
                        <label>College Email</label>
                        <input type="email" class="form-control" name="college_email" value="<?php echo htmlspecialchars($student['college_email'] ?? ''); ?>" <?php echo $dis; ?>>
                    </div>
                </div>

                <div class="section-divider"><i class="fa fa-credit-card"></i> Aadhar</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Aadhar Number</label>
                        <input type="text" class="form-control" name="aadhar_number" value="<?php echo htmlspecialchars($student['aadhar_number'] ?? ''); ?>" placeholder="XXXX XXXX XXXX" <?php echo $dis; ?>>
                    </div>
                </div>

                <div class="section-divider"><i class="fa fa-users"></i> Family Details</div>
                <div class="form-row">
                    <div class="form-group"><label>Father's Name</label><input type="text" class="form-control" name="father_name" value="<?php echo htmlspecialchars($student['father_name'] ?? ''); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>Father's Occupation</label><input type="text" class="form-control" name="father_occupation" value="<?php echo htmlspecialchars($student['father_occupation'] ?? ''); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>Father's Phone</label><input type="text" class="form-control" name="father_phone" value="<?php echo htmlspecialchars($student['father_phone'] ?? ''); ?>" <?php echo $dis; ?>></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Mother's Name</label><input type="text" class="form-control" name="mother_name" value="<?php echo htmlspecialchars($student['mother_name'] ?? ''); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>Mother's Occupation</label><input type="text" class="form-control" name="mother_occupation" value="<?php echo htmlspecialchars($student['mother_occupation'] ?? ''); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>Mother's Phone</label><input type="text" class="form-control" name="mother_phone" value="<?php echo htmlspecialchars($student['mother_phone'] ?? ''); ?>" <?php echo $dis; ?>></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Guardian Name</label><input type="text" class="form-control" name="guardian_name" value="<?php echo htmlspecialchars($student['guardian_name'] ?? ''); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>Guardian Phone</label><input type="text" class="form-control" name="guardian_phone" value="<?php echo htmlspecialchars($student['guardian_phone'] ?? ''); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>Guardian Occupation</label><input type="text" class="form-control" name="guardian_occupation" value="<?php echo htmlspecialchars($student['guardian_occupation'] ?? ''); ?>" <?php echo $dis; ?>></div>
                </div>

                <div class="section-divider"><i class="fa fa-map-marker"></i> Address</div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Address Line (House/Street/Locality)</label>
                    <textarea class="form-control" name="address_line" rows="2" <?php echo $dis; ?>><?php echo htmlspecialchars($student['address_line'] ?? ''); ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Village/Town</label><input type="text" class="form-control" name="village" value="<?php echo htmlspecialchars($student['village'] ?? ''); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>District</label><input type="text" class="form-control" name="district" value="<?php echo htmlspecialchars($student['district'] ?? ''); ?>" <?php echo $dis; ?>></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>State</label><input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($student['state'] ?? ''); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>Country</label><input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($student['country'] ?? 'India'); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>Pin Code</label><input type="text" class="form-control" name="pin_code" value="<?php echo htmlspecialchars($student['pin_code'] ?? ''); ?>" <?php echo $dis; ?>></div>
                </div>

                <div class="section-divider"><i class="fa fa-bank"></i> Bank Account Details</div>
                <div class="form-row">
                    <div class="form-group"><label>Bank Name</label><input type="text" class="form-control" name="bank_name" value="<?php echo htmlspecialchars($student['bank_name'] ?? ''); ?>" placeholder="e.g. State Bank of India" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>Account Number</label><input type="text" class="form-control" name="bank_account" value="<?php echo htmlspecialchars($student['bank_account'] ?? ''); ?>" <?php echo $dis; ?>></div>
                    <div class="form-group"><label>IFSC Code</label><input type="text" class="form-control" name="ifsc_code" value="<?php echo htmlspecialchars($student['ifsc_code'] ?? ''); ?>" <?php echo $dis; ?>></div>
                </div>

                <?php if ($canEdit): ?>
                    <button type="submit" class="submit-btn"><i class="fa fa-save"></i> Save Changes</button>
                <?php else: ?>
                    <button type="button" class="submit-btn" style="opacity:0.5; cursor:not-allowed;" disabled><i class="fa fa-lock"></i> Edit Locked — Request Access</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>
