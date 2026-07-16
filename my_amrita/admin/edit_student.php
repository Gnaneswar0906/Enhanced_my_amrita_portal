<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

$sid = intval($_GET['id'] ?? 0);
if (!$sid) { header('Location: students.php'); exit(); }

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $dept   = trim($_POST['department'] ?? '');
    $sem    = intval($_POST['semester'] ?? 1);
    $dob    = trim($_POST['dob'] ?? '');
    $addr   = trim($_POST['address'] ?? '');
    $newpw  = trim($_POST['new_password'] ?? '');

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE students SET name=?, email=?, phone=?, department=?, semester=?, dob=?, address=? WHERE id=?');
        $stmt->execute([$name, $email, $phone, $dept, $sem, $dob ?: null, $addr, $sid]);
        // Keep users table in sync
        $pdo->prepare('UPDATE users SET name=?, email=? WHERE linked_student_id=?')->execute([$name, $email, $sid]);
        if ($newpw) {
            $hash = password_hash($newpw, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE students SET password=? WHERE id=?')->execute([$hash, $sid]);
            $pdo->prepare('UPDATE users SET password=? WHERE linked_student_id=?')->execute([$hash, $sid]);
        }
        $pdo->commit();
        $msg = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = 'error';
    }
}

$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$sid]);
$student = $stmt->fetch();
if (!$student) { header('Location: students.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita Admin – Edit Student</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .save-btn {
            background: linear-gradient(135deg, #a4123f, #d4264f);
            color: #fff; border: none; padding: 10px 28px; border-radius: 8px;
            font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .save-btn:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(164,18,63,0.3); }
        .pw-note { font-size: 11px; color: #999; margin-top: 3px; }
        .section-head { font-size: 13px; font-weight: 700; color: #a4123f; text-transform: uppercase;
            letter-spacing: 0.5px; margin: 22px 0 12px; border-bottom: 1px solid #f0d0d8; padding-bottom: 6px; }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Admin Panel (Beta)</span>
        <div class="nav-links">
            <span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>
    <div class="breadcrumb-bar">
        <a href="home.php">Admin Home</a> <span class="sep">/</span>
        <a href="students.php">Students</a> <span class="sep">/</span>
        Edit – <?php echo htmlspecialchars($student['name']); ?>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-edit"></i> Edit Student</h1>
            <a href="students.php" class="back-btn"><i class="fa fa-arrow-left"></i> All Students</a>
        </div>

        <?php if ($msg === 'success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Student details updated successfully!</div>
        <?php elseif ($msg === 'error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Update failed. Please try again.</div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title"><i class="fa fa-user"></i> <?php echo htmlspecialchars($student['name']); ?>
                <small style="font-size:12px; color:#888; font-weight:400;"> – <?php echo htmlspecialchars($student['enrollment_no']); ?></small>
            </h2>
            <form method="POST">
                <div class="section-head"><i class="fa fa-credit-card"></i> Basic Information</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Enrollment No. <span style="color:#bbb; font-size:11px;">(read-only)</span></label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['enrollment_no']); ?>" disabled>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($student['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-control" name="department">
                            <?php
                            $depts = ['Computer Science & Engineering','Electronics & Communication','Mechanical Engineering','Civil Engineering','Electrical Engineering'];
                            foreach ($depts as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo $student['department'] === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <select class="form-control" name="semester">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $student['semester'] == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" class="form-control" name="dob" value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($student['address'] ?? ''); ?>">
                    </div>
                </div>

                <div class="section-head"><i class="fa fa-lock"></i> Change Password</div>
                <div class="form-row">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" class="form-control" name="new_password" placeholder="Leave blank to keep current password">
                        <div class="pw-note">Leave blank to keep the existing password unchanged.</div>
                    </div>
                </div>

                <div style="margin-top:18px; display:flex; gap:12px; align-items:center;">
                    <button type="submit" class="save-btn"><i class="fa fa-save"></i> Save Changes</button>
                    <a href="view_student.php?id=<?php echo $sid; ?>" style="color:#a4123f; font-size:13px; font-weight:600; text-decoration:none;">
                        <i class="fa fa-eye"></i> View Full Profile
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
