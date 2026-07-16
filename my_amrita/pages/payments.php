<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'report_issue') {
        $payment_id = intval($_POST['payment_id'] ?? 0);
        $issue_type = trim($_POST['issue_type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $bank_acct = trim($_POST['bank_account_paid_from'] ?? '');
        $txn_id = trim($_POST['transaction_id_ref'] ?? '');
        if ($payment_id && $issue_type && $description) {
            $stmt = $pdo->prepare('INSERT INTO payment_issues (student_id, payment_id, issue_type, description, bank_account_paid_from, transaction_id_ref) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$student_id, $payment_id, $issue_type, $description, $bank_acct ?: null, $txn_id ?: null]);
            $issue_id = $pdo->lastInsertId();
            // Handle proof file uploads
            if (!empty($_FILES['proof_files']['name'][0])) {
                foreach ($_FILES['proof_files']['name'] as $key => $fname) {
                    if ($_FILES['proof_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($fname, PATHINFO_EXTENSION);
                        $newName = 'payment_proof_' . $issue_id . '_' . ($key+1) . '.' . $ext;
                        $dest = '../uploads/payment_proofs/' . $newName;
                        @mkdir('../uploads/payment_proofs', 0777, true);
                        move_uploaded_file($_FILES['proof_files']['tmp_name'][$key], $dest);
                    }
                }
            }
            $msg = 'issue_reported';
        } else { $msg = 'error'; }
    }
}

$stmt = $pdo->prepare('SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC');
$stmt->execute([$student_id]);
$payments = $stmt->fetchAll();

$stmt2 = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt2->execute([$student_id]);
$student = $stmt2->fetch();

$issues = [];
try {
    $stmt3 = $pdo->prepare('SELECT * FROM payment_issues WHERE student_id = ? ORDER BY created_at DESC');
    $stmt3->execute([$student_id]);
    $issues = $stmt3->fetchAll();
} catch(Exception $e) {}

// Extract Year/Term heuristically from description if possible, or fallback
function getFeeTerm($desc) {
    if (stripos($desc, 'Odd') !== false) return 'Odd Sem';
    if (stripos($desc, 'Even') !== false) return 'Even Sem';
    return 'Annual';
}
function getYearFromDesc($desc) {
    preg_match('/\d{4}-\d{2}/', $desc, $matches);
    return $matches[0] ?? date('Y') . '-' . substr(date('Y')+1, 2);
}

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
    <meta charset="utf-8"><title>My Amrita - Payments</title>
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
        
        .fees-table-container { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow-x: auto; margin-bottom: 24px; }
        .fees-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .fees-table th { background: #f8f9fa; color: #333; font-weight: 600; font-size: 13px; padding: 14px 16px; text-align: left; border-bottom: 2px solid #eaeaea; }
        .fees-table td { padding: 14px 16px; font-size: 13px; color: #555; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .fees-table tr:hover td { background: #fcfcfc; }
        
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .status-completed { background: #1abc9c; color: #fff; }
        .status-pending { background: #f39c12; color: #fff; }
        
        .btn-download { background: transparent; color: #7f8c8d; border: none; font-size: 13px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: 0.2s; }
        .btn-download:hover { color: #2c3e50; }
        .btn-report { background: transparent; color: #e74c3c; border: none; font-size: 12px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration: underline; margin-top: 6px; }
    </style>
</head>
<body class="page-bg">
    <nav class="top-navbar"><span class="brand">Student Portal</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar" style="margin-bottom:20px;"><a href="../home.php">Home</a> <span class="sep">/</span> Payments</div>
    
    <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">
        
        <?php if ($msg === 'issue_reported'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Payment issue reported successfully!</div>
        <?php elseif ($msg === 'error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all required fields.</div>
        <?php endif; ?>

        <!-- Payment Details Profile Card -->
        <div class="payment-header">Payment Details</div>
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

        <!-- Fees Table -->
        <div class="fees-table-container">
            <table class="fees-table">
                <thead>
                    <tr>
                        <th>Fee Term</th>
                        <th>Category</th>
                        <th>Title</th>
                        <th>Year</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Payment Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" style="text-align:center; padding:30px;">No payment records found.</td></tr>
                    <?php else: ?>
                        <?php 
                        $is_day_scholar = (($student['residence_type'] ?? '') === 'Day Scholar');
                        $visible_payments = 0;
                        foreach ($payments as $p): 
                            if ($is_day_scholar && (stripos($p['payment_type'] ?? '', 'Hostel') !== false || stripos($p['description'] ?? '', 'Hostel') !== false)) {
                                continue;
                            }
                            $visible_payments++;
                            $year = getYearFromDesc($p['description'] ?? '');
                            $term = $year . ' ' . getFeeTerm($p['description'] ?? '');
                            $due_date = $p['payment_date'] ? date('Y-m-d', strtotime($p['payment_date'])) : date('Y-m-d', strtotime('+30 days'));
                            $statusClass = $p['status'] === 'Paid' ? 'status-completed' : 'status-pending';
                            $statusText = $p['status'] === 'Paid' ? 'Completed' : 'Pending';
                        ?>
                        <tr>
                            <td><?php echo $term; ?></td>
                            <td><?php echo htmlspecialchars($p['payment_type'] ?? 'Fee'); ?></td>
                            <td style="max-width:250px;"><?php echo htmlspecialchars($p['description'] ?? ''); ?></td>
                            <td></td> <!-- Deliberately empty based on screenshot -->
                            <td><?php echo number_format($p['amount']); ?></td>
                            <td><?php echo $due_date; ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                            <td>
                                <?php if ($p['status'] === 'Paid'): ?>
                                    <a href="generate_receipt.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn-download"><i class="fa fa-download"></i> Download Receipt</a>
                                <?php else: ?>
                                    <span style="color:#999; font-size:12px;">Payment Pending</span>
                                <?php endif; ?>
                                <br>
                                <button class="btn-report" onclick="showIssueForm(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['description'])); ?>')"><i class="fa fa-flag"></i> Report Issue</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($visible_payments === 0): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px;">No payment records found.</td></tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Tab for reported issues -->
        <?php if (!empty($issues)): ?>
        <div style="margin-top:40px;">
            <h3 style="font-size:16px; color:#333; margin-bottom:16px;"><i class="fa fa-flag"></i> Your Reported Issues</h3>
            <div class="fees-table-container">
                <table class="fees-table">
                    <thead><tr><th>#</th><th>Type</th><th>Description</th><th>Bank A/C Used</th><th>Txn ID</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($issues as $i => $issue): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><strong><?php echo htmlspecialchars($issue['issue_type']); ?></strong></td>
                            <td style="max-width:200px;"><?php echo htmlspecialchars($issue['description']); ?></td>
                            <td><?php echo htmlspecialchars($issue['bank_account_paid_from'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($issue['transaction_id_ref'] ?? '—'); ?></td>
                            <td><span class="status-badge" style="background:#888;"><?php echo $issue['status']; ?></span></td>
                            <td><?php echo date('d M Y', strtotime($issue['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Issue Report Modal (Kept from original) -->
        <div id="issueModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:16px; padding:32px; max-width:520px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.3); max-height:90vh; overflow-y:auto;">
                <h3 style="margin:0 0 16px; color:#a4123f;"><i class="fa fa-flag"></i> Report Payment Issue</h3>
                <p style="font-size:13px; color:#888;" id="issuePaymentDesc"></p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="report_issue">
                    <input type="hidden" name="payment_id" id="issuePaymentId">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Issue Type</label>
                        <select class="form-control" name="issue_type" required>
                            <option value="Incorrect Amount">Incorrect Amount</option>
                            <option value="Double Payment">Double Payment</option>
                            <option value="Payment Not Reflected">Payment Not Reflected</option>
                            <option value="Wrong Fee Category">Wrong Fee Category</option>
                            <option value="Refund Request">Refund Request</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Bank Account Number (from which you paid) <span style="color:#e74c3c;">*</span></label>
                        <input type="text" class="form-control" name="bank_account_paid_from" placeholder="Enter bank account number used for payment" required>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Transaction ID <span style="color:#e74c3c;">*</span></label>
                        <input type="text" class="form-control" name="transaction_id_ref" placeholder="Enter transaction/reference ID" required>
                    </div>
                    <div class="form-group" style="margin-bottom:14px;">
                        <label>Description</label>
                        <textarea class="form-control" name="description" placeholder="Describe the issue in detail..." required></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom:18px;">
                        <label>Payment Proof (Screenshots/PDF - Multiple allowed)</label>
                        <input type="file" class="form-control" name="proof_files[]" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" multiple style="padding:10px; border:2px dashed #d0d0d0; border-radius:10px; background:#fafafa; cursor:pointer;" onchange="this.style.borderColor='#27ae60'; this.style.background='#f0fff4';">
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="submit" class="submit-btn" style="padding:10px 20px; font-size:13px;"><i class="fa fa-paper-plane"></i> Submit</button>
                        <button type="button" onclick="document.getElementById('issueModal').style.display='none'" style="padding:10px 20px; background:#eee; border:none; border-radius:8px; font-size:13px; cursor:pointer;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function showIssueForm(pid, desc) {
        document.getElementById('issuePaymentId').value = pid;
        document.getElementById('issuePaymentDesc').textContent = 'Payment: ' + desc;
        document.getElementById('issueModal').style.display = 'flex';
    }
    </script>
<script src="../js/upload_validator.js"></script>
</body>
</html>

