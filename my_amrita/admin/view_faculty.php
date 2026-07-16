<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];

// Fetch all teachers with their courses and advisee counts
$teachers = $pdo->query(
    "SELECT u.id, u.name, u.username, u.email,
            (SELECT GROUP_CONCAT(tc.course_name SEPARATOR ', ') FROM teacher_courses tc WHERE tc.user_id = u.id) AS subjects,
            (SELECT GROUP_CONCAT(tc.course_code SEPARATOR ', ') FROM teacher_courses tc WHERE tc.user_id = u.id) AS course_codes,
            (SELECT COUNT(*) FROM teacher_advisees ta WHERE ta.user_id = u.id) AS advisee_count
     FROM users u WHERE u.role = 'teacher' ORDER BY u.name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita Admin – View Faculty</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .faculty-avatar {
            width: 48px; height: 48px; border-radius: 50%;
            background: linear-gradient(135deg, #a4123f, #d4264f);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 20px; font-weight: 700; flex-shrink: 0;
        }
        .faculty-card {
            display: flex; align-items: flex-start; gap: 16px;
            background: #fff; border: 1px solid #e8e8e8; border-radius: 10px;
            padding: 18px; margin-bottom: 14px; transition: all 0.2s;
        }
        .faculty-card:hover { box-shadow: 0 4px 18px rgba(164,18,63,0.10); border-color: #d4264f; }
        .faculty-info { flex: 1; }
        .faculty-name { font-size: 16px; font-weight: 700; color: #222; margin: 0 0 2px; }
        .faculty-username { font-size: 12px; color: #999; margin: 0 0 8px; }
        .faculty-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .tag {
            display: inline-flex; align-items: center; gap: 5px;
            background: #fdf5f7; border: 1px solid #f0d0d8; color: #a4123f;
            border-radius: 20px; padding: 3px 12px; font-size: 12px; font-weight: 500;
        }
        .tag.blue { background: #f0f4ff; border-color: #c5d5ff; color: #3355cc; }
        .tag.green { background: #f0fff6; border-color: #b0e8c8; color: #1a7a3f; }
        .advisee-toggle {
            background: linear-gradient(135deg, #a4123f, #d4264f); color: #fff; border: none;
            padding: 5px 14px; border-radius: 6px; font-size: 11px; font-weight: 600;
            cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s;
        }
        .advisee-toggle:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(164,18,63,0.3); }
        .advisee-list {
            display: none; margin-top: 14px; border-top: 1px solid #f0d0d8; padding-top: 14px;
        }
        .advisee-list table { width: 100%; }
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
        <a href="home.php">Admin Home</a> <span class="sep">/</span> View Faculty
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-graduation-cap"></i> Faculty Directory (<?php echo count($teachers); ?>)</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Admin Home</a>
        </div>

        <?php if (empty($teachers)): ?>
            <div class="card"><div class="empty-state"><i class="fa fa-users"></i><p>No faculty members found.</p></div></div>
        <?php else: ?>
            <?php foreach ($teachers as $t): ?>
            <?php
                $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_filter(explode(' ', $t['name']), 'strlen')));
                $initials = substr($initials, 0, 2);
                $advisees = $pdo->prepare('SELECT s.name, s.enrollment_no, s.semester, s.email FROM students s JOIN teacher_advisees ta ON s.id = ta.student_id WHERE ta.user_id = ? ORDER BY s.name');
                $advisees->execute([$t['id']]);
                $advisees = $advisees->fetchAll();
            ?>
            <div class="faculty-card">
                <div class="faculty-avatar"><?php echo $initials; ?></div>
                <div class="faculty-info">
                    <div class="faculty-name"><?php echo htmlspecialchars($t['name']); ?></div>
                    <div class="faculty-username"><i class="fa fa-user" style="font-size:10px;"></i> <?php echo htmlspecialchars($t['username']); ?>
                        &nbsp;·&nbsp;
                        <i class="fa fa-envelope" style="font-size:10px;"></i> <?php echo htmlspecialchars($t['email']); ?>
                    </div>
                    <div class="faculty-meta">
                        <?php if ($t['subjects']): ?>
                            <?php foreach (explode(', ', $t['subjects']) as $subj): ?>
                                <span class="tag"><i class="fa fa-book"></i> <?php echo htmlspecialchars($subj); ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="tag" style="color:#999; border-color:#ddd; background:#fafafa;">No subjects assigned</span>
                        <?php endif; ?>
                        <span class="tag green"><i class="fa fa-users"></i> <?php echo $t['advisee_count']; ?> Advisee<?php echo $t['advisee_count'] != 1 ? 's' : ''; ?></span>
                        <?php if ($t['course_codes']): ?>
                            <?php foreach (explode(', ', $t['course_codes']) as $code): ?>
                                <span class="tag blue"><i class="fa fa-code"></i> <?php echo htmlspecialchars(trim($code)); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($advisees)): ?>
                    <div style="margin-top:12px;">
                        <button class="advisee-toggle" onclick="toggle(this)">
                            <i class="fa fa-users"></i> View Advisees (<?php echo count($advisees); ?>)
                        </button>
                        <div class="advisee-list">
                            <table class="data-table">
                                <thead><tr><th>#</th><th>Name</th><th>Enrollment No.</th><th>Sem</th><th>Email</th></tr></thead>
                                <tbody>
                                <?php foreach ($advisees as $i => $a): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($a['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($a['enrollment_no']); ?></td>
                                    <td><?php echo $a['semester']; ?></td>
                                    <td><?php echo htmlspecialchars($a['email']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function toggle(btn) {
        var list = btn.nextElementSibling;
        if (list.style.display === 'block') {
            list.style.display = 'none';
            btn.innerHTML = '<i class="fa fa-users"></i> ' + btn.innerHTML.replace(/Hide/, 'View');
        } else {
            list.style.display = 'block';
        }
    }
    </script>
</body>
</html>
