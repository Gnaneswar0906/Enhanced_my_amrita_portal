<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_student') {
        $enroll = trim($_POST['enrollment_no'] ?? '');
        $name   = trim($_POST['name'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $dept   = trim($_POST['department'] ?? '');
        $sem    = intval($_POST['semester'] ?? 1);
        $uname  = trim($_POST['username'] ?? '');
        $upass  = $_POST['password'] ?? 'student';

        if ($enroll && $name && $uname) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO students (enrollment_no, username, password, name, email, phone, department, semester) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $hash = password_hash($upass, PASSWORD_DEFAULT);
                $stmt->execute([$enroll, $uname, $hash, $name, $email, $phone, $dept, $sem]);
                $sid = $pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO users (username, password, role, name, email, linked_student_id) VALUES (?, ?, "student", ?, ?, ?)');
                $stmt->execute([$uname, $hash, $name, $email, $sid]);
                $pdo->commit();
                $msg = 'add_success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'add_error';
            }
        } else { $msg = 'add_error'; }

    } elseif ($action === 'delete_student') {
        $sid = intval($_POST['student_id'] ?? 0);
        if ($sid) {
            try {
                $pdo->beginTransaction();
                // Delete from users
                $pdo->prepare('DELETE FROM users WHERE linked_student_id = ?')->execute([$sid]);
                // Delete related data
                $tables = ['attendance','marks','attendance_issues','marks_issues','notes','internal_marks',
                    'gate_passes','leaves','medical_leaves','awards','grace_marks','timetable','timetable_changes',
                    'seating_arrangements','attendance_alerts','fee_notifications','payments','services',
                    'complaints','documents','incidents','refunds','admit_cards','course_feedback',
                    'tlp_feedback','supplementary','faculty_advisors'];
                foreach ($tables as $t) {
                    try { $pdo->prepare("DELETE FROM $t WHERE student_id = ?")->execute([$sid]); } catch(Exception $e) {}
                }
                // Delete gatepass cancellations
                try { $pdo->prepare('DELETE FROM gatepass_cancellations WHERE student_id = ?')->execute([$sid]); } catch(Exception $e) {}
                // Delete student
                $pdo->prepare('DELETE FROM students WHERE id = ?')->execute([$sid]);
                $pdo->commit();
                $msg = 'delete_success';
            } catch(Exception $e) {
                $pdo->rollBack();
                $msg = 'delete_error';
            }
        }
    }
}

// Filters
$table_alias = '';
require_once 'filter_logic.php';

$sql = 'SELECT * FROM students WHERE 1=1' . $filter_sql . ' ORDER BY enrollment_no ASC';
$stmt = $pdo->prepare($sql); 
$stmt->execute($filter_params);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita Admin - Manage Students</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .delete-btn {
            background:linear-gradient(135deg,#c0392b,#e74c3c); color:#fff; border:none;
            padding:6px 14px; border-radius:6px; font-size:11px; font-weight:600;
            cursor:pointer; transition:all 0.2s; font-family:'Inter',sans-serif;
        }
        .delete-btn:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(192,57,43,0.3); }
        .edit-link {
            color:#e67e22; font-size:13px; font-weight:600; text-decoration:none;
            transition:all 0.2s;
        }
        .edit-link:hover { color:#ca6f1e; }
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
        <a href="home.php">Admin Home</a> <span class="sep">/</span> Manage Students
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-users"></i> Manage Students</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a>
        </div>

        <?php if ($msg === 'add_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Student added successfully!</div>
        <?php elseif ($msg === 'add_error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Failed to add student. Check all fields and ensure username/enrollment is unique.</div>
        <?php elseif ($msg === 'delete_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Student removed successfully.</div>
        <?php elseif ($msg === 'delete_error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Failed to remove student.</div>
        <?php endif; ?>

        <!-- Filters -->
        <?php 
        $filter_count = count($students);
        include 'filter_ui.php'; 
        ?>

        <!-- Students Table -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h2 class="card-title" style="margin:0;">Students (<?php echo count($students); ?>)</h2>
                <a href="download_report.php?batch=<?php echo urlencode($filter_batch); ?>&branch=<?php echo urlencode($filter_branch); ?>&section=<?php echo urlencode($filter_section); ?>&semester=<?php echo urlencode($filter_semester); ?>" class="submit-btn" style="text-decoration:none; display:inline-block; margin:0;"><i class="fa fa-download"></i> Download Report</a>
            </div>
            <?php if (empty($students)): ?>
                <div class="empty-state"><i class="fa fa-users"></i><p>No students found.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>Enrollment No.</th><th>Name</th><th>Batch</th><th>Section</th><th>Sem</th><th>Dept</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($s['enrollment_no']); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><span style="font-size:11px; padding:2px 8px; background:#e3f2fd; border-radius:4px; color:#1565c0; font-weight:600;"><?php echo htmlspecialchars($s['batch'] ?? '2023-2027'); ?></span></td>
                            <td><?php echo htmlspecialchars($s['section'] ?? 'B'); ?></td>
                            <td><?php echo $s['semester']; ?></td>
                            <td style="font-size:12px;"><?php echo htmlspecialchars($s['department']); ?></td>
                            <td style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <a href="view_student.php?id=<?php echo $s['id']; ?>" style="color:#1a73e8; font-size:13px; font-weight:600; text-decoration:none;"><i class="fa fa-eye"></i> View</a>
                                <a href="edit_student.php?id=<?php echo $s['id']; ?>" class="edit-link"><i class="fa fa-edit"></i> Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to DELETE this student and ALL their data? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_student">
                                    <input type="hidden" name="student_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="delete-btn"><i class="fa fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Add New Student Form -->
        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Add New Student</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_student">
                <div class="form-row">
                    <div class="form-group">
                        <label>Enrollment No.</label>
                        <input type="text" class="form-control" name="enrollment_no" placeholder="BL.EN.U4CSE23XXX" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" class="form-control" name="name" placeholder="Student full name" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Username (for login)</label>
                        <input type="text" class="form-control" name="username" placeholder="Login username" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="text" class="form-control" name="password" value="student" placeholder="Login password">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email" placeholder="student@am.amrita.edu">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" class="form-control" name="phone" placeholder="Phone number">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-control" name="department">
                            <option value="Computer Science & Engineering">Computer Science & Engineering</option>
                            <option value="Electronics & Communication">Electronics & Communication</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                            <option value="Civil Engineering">Civil Engineering</option>
                            <option value="Electrical Engineering">Electrical Engineering</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <select class="form-control" name="semester">
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i === 4 ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-plus"></i> Add Student</button>
            </form>
        </div>
    </div>
    <script>
    // JS handled by form submit now
    </script>
</body>
</html>
