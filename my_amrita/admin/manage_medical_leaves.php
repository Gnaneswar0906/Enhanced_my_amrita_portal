<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $mlid = intval($_POST['leave_id'] ?? 0);
    $row = $pdo->prepare('SELECT ml.*, s.name FROM medical_leaves ml JOIN students s ON ml.student_id = s.id WHERE ml.id = ?');
    $row->execute([$mlid]);
    $row = $row->fetch();
    if ($row) {
        if ($_POST['action'] === 'approve') {
            $pdo->prepare('UPDATE medical_leaves SET status="Approved", reviewed_by=? WHERE id=?')->execute([$admin_name, $mlid]);
            $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?, ?, ?, "medical_leave")')->execute([
                $row['student_id'],
                'Medical Leave Approved',
                'Your medical leave (' . $row['from_date'] . ' to ' . $row['to_date'] . ') has been approved by Admin.'
            ]);
            $msg = 'approved';
        } elseif ($_POST['action'] === 'reject') {
            $pdo->prepare('UPDATE medical_leaves SET status="Rejected", reviewed_by=? WHERE id=?')->execute([$admin_name, $mlid]);
            $pdo->prepare('INSERT INTO notifications (student_id, title, message, type) VALUES (?, ?, ?, "medical_leave")')->execute([
                $row['student_id'],
                'Medical Leave Rejected',
                'Your medical leave (' . $row['from_date'] . ' to ' . $row['to_date'] . ') has been rejected by Admin.'
            ]);
            $msg = 'rejected';
        }
    }
}

$table_alias = 's';
require_once 'filter_logic.php';

$leaves = $pdo->query('SELECT ml.*, s.name as student_name, s.enrollment_no, s.batch, s.department as branch, s.section, s.semester as current_sem, 
                       (SELECT GROUP_CONCAT(fa.file_path SEPARATOR "|") FROM file_attachments fa WHERE fa.ref_type="medical_leave" AND fa.ref_id=ml.id) as files 
                       FROM medical_leaves ml 
                       JOIN students s ON ml.student_id = s.id 
                       WHERE 1=1 ' . $filter_sql . ' 
                       ORDER BY FIELD(ml.status,"Submitted","Under Review","Verified","Approved","Rejected"), ml.created_at DESC');
$leaves->execute($filter_params);
$leaves = $leaves->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">

<head>
    <meta charset="utf-8">
    <title>My Amrita Admin - Medical Leaves</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .approve-btn {
            background: #27ae60;
            color: #fff;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }

        .reject-btn {
            background: #c0392b;
            color: #fff;
            border: none;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <nav class="top-navbar"><span class="brand">Admin Panel (Beta)</span>
        <div class="nav-links"><span
                style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($admin_name); ?></span><a
                href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div>
    </nav>
    <div class="breadcrumb-bar"><a href="home.php">Admin Home</a> <span class="sep">/</span> Medical Leaves</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-medkit"></i> Medical Leaves</h1><a href="home.php" class="back-btn"><i
                    class="fa fa-arrow-left"></i> Admin Home</a>
        </div>
        <?php if ($msg): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Medical leave <?php echo $msg; ?> & student
                notified.</div><?php endif; ?>

        <?php
        $filter_count = count($leaves);
        include 'filter_ui.php';
        ?>

        <div class="card">
            <h2 class="card-title">All Medical Leaves (<?php echo $filter_count; ?>)</h2>
            <?php if (empty($leaves)): ?>
                <div class="empty-state"><i class="fa fa-medkit"></i>
                    <p>No medical leaves.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Dates</th>
                            <th>Condition</th>
                            <th>Doctor</th>
                            <th>Hospital</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaves as $l): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($l['student_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($l['enrollment_no']); ?></small><br>
                                    <span style="font-size:10px; color:#888;">
                                        <?php echo htmlspecialchars($l['batch'] . ' | ' . $l['branch'] . ' | Sec ' . $l['section'] . ' | Sem ' . $l['current_sem']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M', strtotime($l['from_date'])); ?> –
                                    <?php echo date('d M Y', strtotime($l['to_date'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($l['condition_desc']); ?>
                                    <?php if (!empty($l['files'])): ?>
                                        <div style="margin-top:6px; font-size:11px;">
                                            <?php foreach (explode('|', $l['files']) as $idx => $fpath): ?>
                                                <a href="../<?php echo ltrim($fpath, '/'); ?>" target="_blank" style="display:inline-block; margin-right:6px; color:#1a73e8; text-decoration:none;"><i class="fa fa-paperclip"></i> File <?php echo $idx+1; ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($l['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($l['hospital']); ?></td>
                                <td><span
                                        class="badge badge-<?php echo strtolower(str_replace(' ', '', $l['status'])); ?>"><?php echo $l['status']; ?></span>
                                </td>
                                <td><?php if (in_array($l['status'], ['Submitted', 'Under Review', 'Verified'])): ?>
                                        <form method="POST" style="display:inline;"><input type="hidden" name="leave_id"
                                                value="<?php echo $l['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="approve-btn"><i
                                                    class="fa fa-check"></i></button>
                                            <button type="submit" name="action" value="reject" class="reject-btn"><i
                                                    class="fa fa-times"></i></button>
                                        </form>
                                    <?php else: ?><span style="color:#999;">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>