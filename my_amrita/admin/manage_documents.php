<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit();
}
require_once '../api/db.php';
$admin_name = $_SESSION['user_name'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        try {
            $stmt = $pdo->prepare("UPDATE `documents` SET verification_status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        } catch (Exception $e) {}
    } elseif ($_POST['action'] === 'broadcast') {
        $doc_name = trim($_POST['doc_name']);
        $doc_type = trim($_POST['doc_type']);
        $b_batch = $_POST['b_batch'];
        $b_branch = $_POST['b_branch'];
        $b_section = $_POST['b_section'];
        $b_sem = $_POST['b_sem'];

        // Get matching students
        $s_sql = "SELECT id FROM students WHERE 1=1";
        $s_params = [];
        if ($b_batch !== 'all') { $s_sql .= " AND batch = ?"; $s_params[] = $b_batch; }
        if ($b_branch !== 'all') { $s_sql .= " AND department = ?"; $s_params[] = $b_branch; }
        if ($b_section !== 'all') { $s_sql .= " AND section = ?"; $s_params[] = $b_section; }
        if ($b_sem !== 'all') { $s_sql .= " AND semester = ?"; $s_params[] = intval($b_sem); }
        
        $s_stmt = $pdo->prepare($s_sql);
        $s_stmt->execute($s_params);
        $students = $s_stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($students && $doc_name && isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $fname = basename($_FILES['doc_file']['name']);
            $ext = pathinfo($fname, PATHINFO_EXTENSION);
            $newName = 'doc_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $dest = '../uploads/documents/' . $newName;
            @mkdir('../uploads/documents', 0777, true);
            
            if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $dest)) {
                $doc_path = '/uploads/documents/' . $newName;
                
                $pdo->beginTransaction();
                try {
                    // Using REPLACE or just INSERT? Assuming documents table has a file_path column.
                    $ins = $pdo->prepare("INSERT INTO documents (student_id, file_name, doc_type, file_path, verification_status, uploaded_by) VALUES (?, ?, ?, ?, 'Verified', 'Admin')");
                    foreach ($students as $sid) {
                        $ins->execute([$sid, $doc_name, $doc_type, $doc_path]);
                    }
                    $pdo->commit();
                    $msg = count($students) . " documents broadcasted successfully.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg = "Error broadcasting document: " . $e->getMessage();
                }
            } else {
                $msg = "File upload failed.";
            }
        } elseif (!$students) {
            $msg = "No students found for this cohort.";
        } else {
            $msg = "Please provide a valid file and document name.";
        }
    }
}

$table_alias = 's';
require_once 'filter_logic.php';

// Fetch all records with student details
$records = [];
try {
    $sql = "SELECT m.*, s.name as student_name, s.enrollment_no as reg_no, s.batch, s.department as branch, s.section, s.semester as current_sem 
            FROM `documents` m 
            JOIN students s ON m.student_id = s.id 
            WHERE 1=1 " . $filter_sql . " 
            ORDER BY m.id DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filter_params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Manage Documents</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .page-header { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e8e8e8; }
        .page-header h1 { margin: 0; font-size: 20px; color: #a4123f; font-weight: 700; }
        .back-btn { background: #f8f9fa; border: 1px solid #ddd; padding: 8px 16px; border-radius: 6px; color: #333; text-decoration: none; font-size: 13px; font-weight: 600; }
        .data-table th { background: #1a1a2e; color: #fff; padding: 12px; font-size: 12px; text-transform: uppercase; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .status-select { padding: 6px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <nav class="top-navbar">
        <span class="brand">Admin Panel</span>
        <div class="nav-links">
            <span><?php echo htmlspecialchars($admin_name); ?></span>
            <a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>
    <div class="breadcrumb-bar"><a href="home.php">Home</a> <span class="sep">/</span> Manage Documents</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-file-text-o"></i> Manage Documents</h1>
            <a href="home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back</a>
        </div>
        
        <?php 
        $filter_count = count($records);
        include 'filter_ui.php'; 
        ?>
        
        <?php if (!empty($msg)): ?>
            <div class="msg-success" style="background:#d4edda; color:#155724; padding:10px; border-radius:6px; margin-bottom:16px;"><?php echo $msg; ?></div>
        <?php endif; ?>

        <div class="card form-section" style="background:#fff; border-radius:10px; padding:20px; border:1px solid #e8e8e8; margin-bottom:20px;">
            <h3 style="margin-top:0; color:#a4123f; font-size:15px;"><i class="fa fa-bullhorn"></i> Broadcast Document to Cohort</h3>
            <p style="font-size:12px; color:#666;">Upload a document and send it to all students matching the criteria.</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="broadcast">
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                    <select name="b_batch" class="status-select" required>
                        <option value="all">All Batches</option>
                        <?php foreach (['2022-2026', '2023-2027', '2024-2028', '2025-2029'] as $b): ?><option value="<?php echo $b; ?>"><?php echo $b; ?></option><?php endforeach; ?>
                    </select>
                    <select name="b_branch" class="status-select" required>
                        <option value="all">All Branches</option>
                        <option value="Computer Science & Engineering">CSE</option>
                        <option value="Artificial Intelligence & Data Science">AIDS</option>
                        <option value="Robotics & Artificial Intelligence">RAI</option>
                        <option value="Electronics & Communication">ECE</option>
                        <option value="Electrical & Electronics">EEE</option>
                        <option value="Electronics & Computer">EAC</option>
                        <option value="Mechanical Engineering">MECHANICAL</option>
                    </select>
                    <select name="b_section" class="status-select" required>
                        <option value="all">All Sections</option>
                        <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                    </select>
                    <select name="b_sem" class="status-select" required>
                        <option value="all">All Semesters</option>
                        <?php for ($i=1; $i<=8; $i++): ?><option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option><?php endfor; ?>
                    </select>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                    <input type="text" name="doc_name" placeholder="Document Name (e.g. Hosteller Guidelines)" class="status-select" style="flex:1;" required>
                    <select name="doc_type" class="status-select" required>
                        <option value="PDF">PDF</option>
                        <option value="Image">Image</option>
                        <option value="Word">Word</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div style="margin-bottom:14px;">
                    <input type="file" name="doc_file" class="status-select" style="width:100%; border:2px dashed #ddd; padding:10px;" required>
                </div>
                <button type="submit" style="background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border:none; padding:8px 16px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;"><i class="fa fa-upload"></i> Broadcast Document</button>
            </form>
        </div>
        
        <div class="card" style="background:#fff; border-radius:10px; padding:20px; border:1px solid #e8e8e8;">
            <?php if (empty($records)): ?>
                <div style="text-align:center; padding:40px; color:#888;">
                    <i class="fa fa-file-text-o" style="font-size:40px; margin-bottom:10px; color:#ccc;"></i>
                    <p>No records found in this module for the selected cohort.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th>Name & Reg No</th>
                                <th>Cohort</th>
                                <?php 
                                $keys = array_keys($records[0]);
                                $hide = ['id', 'student_id', 'student_name', 'reg_no', 'batch', 'branch', 'section', 'current_sem'];
                                foreach ($keys as $k) {
                                    if (!in_array($k, $hide) && !is_numeric($k)) echo "<th>".htmlspecialchars($k)."</th>";
                                }
                                ?>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $r): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($r['student_name']); ?></strong><br><span style="font-size:11px; color:#888;"><?php echo htmlspecialchars($r['reg_no']); ?></span></td>
                                <td style="font-size:11px; color:#666;">
                                    <?php echo htmlspecialchars($r['batch'] . ' | ' . $r['branch']); ?><br>
                                    Sec <?php echo htmlspecialchars($r['section']); ?> | Sem <?php echo htmlspecialchars($r['current_sem']); ?>
                                </td>
                                <?php 
                                foreach ($r as $k => $v) {
                                    if (!in_array($k, $hide) && !is_numeric($k)) echo "<td>".htmlspecialchars(substr((string)$v, 0, 50))."</td>";
                                }
                                ?>
                                <td>
                                    <form method="POST" style="display:inline-flex; gap:6px;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id" value="<?php echo $r['id'] ?? ''; ?>">
                                        <select name="status" class="status-select">
                                            <option value="Pending" <?php echo (isset($r['verification_status']) && $r['verification_status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Verified" <?php echo (isset($r['verification_status']) && $r['verification_status'] == 'Verified') ? 'selected' : ''; ?>>Verified</option>
                                            <option value="Rejected" <?php echo (isset($r['verification_status']) && $r['verification_status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                        <button type="submit" style="background:#27ae60; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">Update</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<script src="../js/upload_validator.js"></script>
</body>
</html>
