<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $bank   = trim($_POST['bank_account'] ?? '');
    $txnid  = trim($_POST['transaction_id'] ?? '');
    $mode   = trim($_POST['mode_of_payment'] ?? '');
    if ($amount > 0 && $reason) {
        $stmt = $pdo->prepare('INSERT INTO refunds (student_id, amount, reason, bank_account, transaction_id, mode_of_payment) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$student_id, $amount, $reason, $bank ?: null, $txnid ?: null, $mode ?: null]);
        $refund_id = $pdo->lastInsertId();
        // Handle proof uploads (multiple)
        if (!empty($_FILES['proof_files']['name'][0])) {
            foreach ($_FILES['proof_files']['name'] as $key => $fname) {
                if ($_FILES['proof_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($fname, PATHINFO_EXTENSION);
                    $newName = 'refund_proof_' . $refund_id . '_' . ($key+1) . '.' . $ext;
                    $dest = '../uploads/proofs/' . $newName;
                    @mkdir('../uploads/proofs', 0777, true);
                    move_uploaded_file($_FILES['proof_files']['tmp_name'][$key], $dest);
                }
            }
        }      $msg = 'success';
    } else { $msg = 'error'; }
}

$stmt = $pdo->prepare('SELECT * FROM refunds WHERE student_id = ? ORDER BY created_at DESC');
$stmt->execute([$student_id]);
$refunds = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Refund</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Refund</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-undo"></i> Refund Requests</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'success'): ?><div class="msg-success"><i class="fa fa-check-circle"></i> Refund request submitted!</div>
        <?php elseif ($msg === 'error'): ?><div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all fields correctly.</div><?php endif; ?>

        <div class="card">
            <h2 class="card-title">Refund History</h2>
            <?php if (empty($refunds)): ?>
                <div class="empty-state"><i class="fa fa-undo"></i><p>No refund requests.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>#</th><th>Amount (₹)</th><th>Reason</th><th>Mode</th><th>Bank Acc</th><th>Txn ID</th><th>Proof</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($refunds as $i => $r): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><strong>₹<?php echo number_format($r['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['reason']); ?></td>
                            <td><?php echo htmlspecialchars($r['mode_of_payment'] ?? '—'); ?></td>
                            <td><small><?php echo htmlspecialchars($r['bank_account'] ?? '—'); ?></small></td>
                            <td><small><?php echo htmlspecialchars($r['transaction_id'] ?? '—'); ?></small></td>
                            <td><?php if ($r['proof_file']): ?><a href="<?php echo htmlspecialchars($r['proof_file']); ?>" style="color:#a4123f;"><i class="fa fa-file-pdf-o"></i> View</a><?php else: ?>—<?php endif; ?></td>
                            <td><span class="badge badge-<?php echo strtolower(str_replace(' ','-',$r['status'])); ?>"><?php echo $r['status']; ?></span></td>
                            <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Request a Refund</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group"><label>Amount (₹)</label><input type="number" step="0.01" min="1" class="form-control" name="amount" placeholder="Enter amount" required></div>
                    <div class="form-group"><label>Mode of Payment</label><select class="form-control" name="mode_of_payment"><option value="">Select...</option><option value="NEFT">NEFT</option><option value="UPI">UPI</option><option value="Bank Transfer">Bank Transfer</option><option value="Cash">Cash</option><option value="DD">Demand Draft</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Bank Account No.</label><input type="text" class="form-control" name="bank_account" placeholder="Refund to this account"></div>
                    <div class="form-group"><label>Transaction ID</label><input type="text" class="form-control" name="transaction_id" placeholder="Original payment transaction ID"></div>
                </div>
                <div class="form-group" style="margin-bottom:14px;"><label>Reason</label><textarea class="form-control" name="reason" placeholder="Reason for refund..." required></textarea></div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Proof Files (Screenshots/Receipts - Multiple allowed)</label>
                    <input type="file" class="form-control" name="proof_files[]" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" multiple style="padding:10px; border:2px dashed #d0d0d0; border-radius:10px; background:#fafafa; cursor:pointer;" onchange="this.style.borderColor='#27ae60'; this.style.background='#f0fff4';">
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Request</button>
            </form>
        </div>
    </div>
<script src="../js/upload_validator.js"></script>
</body>
</html>

