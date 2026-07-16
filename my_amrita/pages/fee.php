<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'pay_fee') {
        $fee_id = intval($_POST['fee_notification_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $mode = trim($_POST['payment_mode'] ?? '');
        $bank_acct = trim($_POST['bank_account'] ?? '');
        $txn_id = trim($_POST['transaction_id'] ?? '');
        $upi_id = trim($_POST['upi_id'] ?? '');
        if ($fee_id && $amount > 0 && $mode && $txn_id) {
            $stmt = $pdo->prepare('INSERT INTO fee_payments (student_id, fee_notification_id, amount, payment_mode, bank_account, transaction_id, upi_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$student_id, $fee_id, $amount, $mode, $bank_acct ?: null, $txn_id, $upi_id ?: null]);
            $fp_id = $pdo->lastInsertId();
            // Handle proof uploads (multiple)
            if (!empty($_FILES['proof_files']['name'][0])) {
                foreach ($_FILES['proof_files']['name'] as $key => $fname) {
                    if ($_FILES['proof_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($fname, PATHINFO_EXTENSION);
                        $newName = 'fee_proof_' . $fp_id . '_' . ($key+1) . '.' . $ext;
                        $dest = '../uploads/fee_proofs/' . $newName;
                        @mkdir('../uploads/fee_proofs', 0777, true);
                        move_uploaded_file($_FILES['proof_files']['tmp_name'][$key], $dest);
                    }
                }
            }
            // Also record in payments table
            $pdo->prepare('INSERT INTO payments (student_id, amount, payment_type, mode_of_payment, bank_account_used, status, transaction_id, payment_date, description) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)')
                ->execute([$student_id, $amount, 'Fee Payment', $mode, $bank_acct ?: null, 'Paid', $txn_id, 'Fee Payment via Portal']);
            // Update fee notification status
            $pdo->prepare("UPDATE fee_notifications SET status = 'Completed' WHERE id = ?")->execute([$fee_id]);
            $msg = 'payment_success';
        } else { $msg = 'error'; }
    }
}

$stmt = $pdo->prepare('SELECT * FROM fee_notifications WHERE student_id = ? ORDER BY FIELD(status, "Overdue","Active","Upcoming","Completed"), deadline ASC');
$stmt->execute([$student_id]);
$fees = $stmt->fetchAll();

// Get recent fee payments
$fee_payments = [];
try {
    $stmt2 = $pdo->prepare('SELECT fp.*, fn.title as fee_title FROM fee_payments fp LEFT JOIN fee_notifications fn ON fp.fee_notification_id = fn.id WHERE fp.student_id = ? ORDER BY fp.created_at DESC');
    $stmt2->execute([$student_id]);
    $fee_payments = $stmt2->fetchAll();
} catch(Exception $e) {}

$today = date('Y-m-d');
$overdue = array_filter($fees, fn($f) => $f['status'] === 'Overdue');
$active = array_filter($fees, fn($f) => in_array($f['status'], ['Overdue','Active','Upcoming']));
$completed = array_filter($fees, fn($f) => $f['status'] === 'Completed');
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8"><title>My Amrita - Fee</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .pay-btn { background:linear-gradient(135deg,#27ae60,#2ecc71); color:#fff; border:none; padding:10px 20px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; transition:all 0.2s; }
        .pay-btn:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(39,174,96,0.3); }
        .payment-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; }
        .payment-modal-body { background:#fff; border-radius:16px; padding:32px; max-width:560px; width:95%; box-shadow:0 20px 60px rgba(0,0,0,0.3); max-height:90vh; overflow-y:auto; }
        .mode-selector { display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; margin-bottom:20px; }
        .mode-option { border:2px solid #e8e8e8; border-radius:10px; padding:14px; text-align:center; cursor:pointer; transition:all 0.2s; font-size:12px; font-weight:600; }
        .mode-option:hover { border-color:#a4123f; }
        .mode-option.active { border-color:#a4123f; background:#fde8e8; color:#a4123f; }
        .mode-option i { display:block; font-size:24px; margin-bottom:6px; color:#a4123f; }
        .qr-container { text-align:center; padding:20px; background:#f8f9fa; border-radius:12px; margin:16px 0; }
        .qr-container canvas { border:3px solid #e8e8e8; border-radius:8px; }
        .mode-content { display:none; }
        .mode-content.active { display:block; }
    </style>
</head>
<body>
    <nav class="top-navbar"><span class="brand">Student Portal (Beta)</span><div class="nav-links"><span style="font-size:13px; opacity:0.9;"><?php echo htmlspecialchars($student_name); ?></span><a href="../logout.php" class="logout-btn"><i class="fa fa-sign-out"></i> Logout</a></div></nav>
    <div class="breadcrumb-bar"><a href="../home.php">Home</a> <span class="sep">/</span> Fee</div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-money"></i> Fee & Payments</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'payment_success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Payment submitted successfully! It will appear in your payment history.</div>
        <?php elseif ($msg === 'error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all required fields.</div>
        <?php endif; ?>

        <?php if (!empty($overdue)): ?>
        <div class="alert-banner danger">
            <i class="fa fa-exclamation-circle"></i>
            <span class="alert-text">You have <strong><?php echo count($overdue); ?></strong> overdue fee payment(s). Please clear them immediately.</span>
        </div>
        <?php endif; ?>

        <!-- Summary -->
        <div class="sgpa-display">
            <div class="sgpa-card"><div class="sgpa-label">Pending Fees</div><div class="sgpa-value"><?php echo count($active); ?></div><div class="sgpa-sub">Needs payment</div></div>
            <div class="sgpa-card secondary"><div class="sgpa-label">Completed</div><div class="sgpa-value"><?php echo count($completed); ?></div><div class="sgpa-sub">Paid fees</div></div>
        </div>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('pending')"><i class="fa fa-clock-o"></i> Pending Fees</button>
            <button class="tab-btn" onclick="switchTab('completed')"><i class="fa fa-check-circle"></i> Completed</button>
            <button class="tab-btn" onclick="switchTab('receipts')"><i class="fa fa-receipt"></i> Payment Receipts</button>
        </div>

        <!-- TAB 1: Pending Fees -->
        <div class="tab-content active" id="tab-pending">
            <?php $pending_fees = array_filter($fees, fn($f) => $f['status'] !== 'Completed'); ?>
            <?php if (empty($pending_fees)): ?>
                <div class="card"><div class="empty-state"><i class="fa fa-check-circle"></i><p>No pending fees! All fees are paid.</p></div></div>
            <?php else: ?>
                <?php foreach ($pending_fees as $f):
                    $status_colors = ['Overdue' => '#e74c3c', 'Active' => '#f5a623', 'Upcoming' => '#3498db'];
                    $sc = $status_colors[$f['status']] ?? '#888';
                    $days_left = (strtotime($f['deadline']) - strtotime($today)) / 86400;
                ?>
                <div class="card" style="border-left:4px solid <?php echo $sc; ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                        <div style="flex:1;">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                                <strong style="font-size:16px; color:#333;"><?php echo htmlspecialchars($f['title']); ?></strong>
                                <span class="badge badge-<?php echo strtolower($f['status']); ?>"><?php echo $f['status']; ?></span>
                            </div>
                            <div style="font-size:13px; color:#666; margin-bottom:8px;"><?php echo htmlspecialchars($f['fee_type']); ?></div>
                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; font-size:13px; margin-bottom:10px;">
                                <div><strong style="font-size:10px; color:#888; text-transform:uppercase;">Amount</strong><br><span style="font-size:18px; font-weight:700; color:#333;">₹<?php echo number_format($f['new_amount'] ?? 0); ?></span></div>
                                <div><strong style="font-size:10px; color:#888; text-transform:uppercase;">Deadline</strong><br><?php echo date('d M Y', strtotime($f['deadline'])); ?></div>
                                <div><strong style="font-size:10px; color:#888; text-transform:uppercase;">Days Left</strong><br>
                                    <?php if ($days_left > 0): ?>
                                        <span style="color:#27ae60; font-weight:600;"><?php echo (int)$days_left; ?> days</span>
                                    <?php else: ?>
                                        <span style="color:#e74c3c; font-weight:600;">Overdue</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($f['description']): ?>
                                <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars($f['description']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="pay-btn" onclick="openPaymentModal(<?php echo $f['id']; ?>, '<?php echo htmlspecialchars($f['title']); ?>', <?php echo $f['new_amount'] ?? 0; ?>)">
                                <i class="fa fa-credit-card"></i> Pay Now
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- TAB 2: Completed -->
        <div class="tab-content" id="tab-completed">
            <?php $done_fees = array_filter($fees, fn($f) => $f['status'] === 'Completed'); ?>
            <?php if (empty($done_fees)): ?>
                <div class="card"><div class="empty-state"><i class="fa fa-clock-o"></i><p>No completed fee payments yet.</p></div></div>
            <?php else: ?>
                <?php foreach ($done_fees as $f): ?>
                <div class="card" style="border-left:4px solid #27ae60;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong style="font-size:15px;"><?php echo htmlspecialchars($f['title']); ?></strong>
                            <div style="font-size:13px; color:#666;"><?php echo htmlspecialchars($f['fee_type']); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:18px; font-weight:700; color:#27ae60;">₹<?php echo number_format($f['new_amount'] ?? 0); ?></div>
                            <span class="badge badge-approved">Paid</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- TAB 3: Payment Receipts -->
        <div class="tab-content" id="tab-receipts">
            <?php if (empty($fee_payments)): ?>
                <div class="card"><div class="empty-state"><i class="fa fa-receipt"></i><p>No payment receipts.</p></div></div>
            <?php else: ?>
                <div class="card">
                    <h2 class="card-title">Payment Receipts</h2>
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Fee</th><th>Amount</th><th>Mode</th><th>Txn ID</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($fee_payments as $i => $fp): ?>
                            <tr>
                                <td><?php echo $i+1; ?></td>
                                <td><?php echo htmlspecialchars($fp['fee_title'] ?? '—'); ?></td>
                                <td style="font-weight:600;">₹<?php echo number_format($fp['amount']); ?></td>
                                <td><span class="badge badge-in-progress"><?php echo htmlspecialchars($fp['payment_mode']); ?></span></td>
                                <td><small><?php echo htmlspecialchars($fp['transaction_id'] ?? '—'); ?></small></td>
                                <td><span class="badge badge-<?php echo strtolower($fp['status']); ?>"><?php echo $fp['status']; ?></span></td>
                                <td><?php echo date('d M Y', strtotime($fp['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Modal -->
        <div class="payment-modal" id="paymentModal">
            <div class="payment-modal-body">
                <h3 style="margin:0 0 6px; color:#a4123f;"><i class="fa fa-credit-card"></i> Make Payment</h3>
                <p style="font-size:14px; color:#666; margin-bottom:16px;" id="payFeeTitle"></p>
                <div style="text-align:center; margin-bottom:20px;">
                    <div style="font-size:32px; font-weight:700; color:#a4123f;" id="payAmount"></div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="paymentForm">
                    <input type="hidden" name="action" value="pay_fee">
                    <input type="hidden" name="fee_notification_id" id="payFeeId">
                    <input type="hidden" name="amount" id="payAmountHidden">

                    <!-- Payment Mode Selector -->
                    <div style="font-size:12px; font-weight:600; color:#888; text-transform:uppercase; margin-bottom:8px;">Select Payment Method</div>
                    <div class="mode-selector">
                        <div class="mode-option active" onclick="selectMode('UPI', this)"><i class="fa fa-mobile"></i>UPI</div>
                        <div class="mode-option" onclick="selectMode('Net Banking', this)"><i class="fa fa-bank"></i>Net Banking</div>
                        <div class="mode-option" onclick="selectMode('Card', this)"><i class="fa fa-credit-card"></i>Card</div>
                        <div class="mode-option" onclick="selectMode('NEFT', this)"><i class="fa fa-exchange"></i>NEFT/RTGS</div>
                        <div class="mode-option" onclick="selectMode('DD', this)"><i class="fa fa-file-text-o"></i>Demand Draft</div>
                        <div class="mode-option" onclick="selectMode('Cheque', this)"><i class="fa fa-pencil-square-o"></i>Cheque</div>
                    </div>
                    <input type="hidden" name="payment_mode" id="payModeHidden" value="UPI">

                    <!-- UPI Mode -->
                    <div class="mode-content active" id="mode-UPI">
                        <div class="qr-container">
                            <div style="font-size:13px; font-weight:600; color:#333; margin-bottom:10px;">Scan QR Code to Pay</div>
                            <canvas id="qrCanvas" width="200" height="200"></canvas>
                            <div style="margin-top:8px; font-size:12px; color:#666;">UPI ID: <strong>amrita.fees@sbi</strong></div>
                        </div>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label>Your UPI ID</label>
                            <input type="text" class="form-control" name="upi_id" placeholder="yourname@upi">
                        </div>
                    </div>

                    <!-- Net Banking Mode -->
                    <div class="mode-content" id="mode-Net Banking">
                        <div style="background:#f0f4ff; border-radius:10px; padding:16px; margin-bottom:14px; font-size:13px;">
                            <strong>Bank Transfer Details:</strong><br>
                            Account Name: Amrita Vishwa Vidyapeetham<br>
                            Account No: 38291020001234<br>
                            IFSC: SBIN0001234<br>
                            Bank: State Bank of India
                        </div>
                    </div>

                    <!-- Card Mode -->
                    <div class="mode-content" id="mode-Card">
                        <div style="background:#fff3cd; border-radius:10px; padding:14px; margin-bottom:14px; font-size:12px; color:#856404;">
                            <i class="fa fa-info-circle"></i> Card payments are processed through the university payment gateway. Enter your transaction details after payment.
                        </div>
                    </div>

                    <!-- NEFT Mode -->
                    <div class="mode-content" id="mode-NEFT">
                        <div style="background:#f0f4ff; border-radius:10px; padding:16px; margin-bottom:14px; font-size:13px;">
                            <strong>NEFT/RTGS Details:</strong><br>
                            Account Name: Amrita Vishwa Vidyapeetham<br>
                            Account No: 38291020001234<br>
                            IFSC: SBIN0001234<br>
                            Bank: State Bank of India, Amritapuri Branch
                        </div>
                    </div>

                    <!-- DD Mode -->
                    <div class="mode-content" id="mode-DD">
                        <div style="background:#f0f4ff; border-radius:10px; padding:16px; margin-bottom:14px; font-size:13px;">
                            <i class="fa fa-info-circle"></i> Draw DD in favor of <strong>"Amrita Vishwa Vidyapeetham"</strong> payable at Amritapuri. Submit DD at the accounts office.
                        </div>
                    </div>

                    <!-- Cheque Mode -->
                    <div class="mode-content" id="mode-Cheque">
                        <div style="background:#f0f4ff; border-radius:10px; padding:16px; margin-bottom:14px; font-size:13px;">
                            <i class="fa fa-info-circle"></i> Write cheque in favor of <strong>"Amrita Vishwa Vidyapeetham"</strong>. Submit at the accounts office.
                        </div>
                    </div>

                    <!-- Common Fields -->
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Bank Account Number (used for payment) <span style="color:#e74c3c;">*</span></label>
                        <input type="text" class="form-control" name="bank_account" placeholder="Enter your bank account number" required>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Transaction ID / Reference No. <span style="color:#e74c3c;">*</span></label>
                        <input type="text" class="form-control" name="transaction_id" placeholder="Enter transaction/reference ID" required>
                    </div>
                    <div class="form-group" style="margin-bottom:18px;">
                        <label>Payment Proof (Screenshots/PDF - Multiple allowed)</label>
                        <input type="file" class="form-control" name="proof_files[]" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" multiple style="padding:10px; border:2px dashed #d0d0d0; border-radius:10px; background:#fafafa; cursor:pointer;" onchange="this.style.borderColor='#27ae60'; this.style.background='#f0fff4';">
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="submit" class="pay-btn" style="flex:1;"><i class="fa fa-check-circle"></i> Confirm Payment</button>
                        <button type="button" onclick="closePaymentModal()" style="padding:10px 20px; background:#eee; border:none; border-radius:8px; font-size:13px; cursor:pointer;">Cancel</button>
                    </div>
                </form>
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
    function openPaymentModal(feeId, title, amount) {
        document.getElementById('payFeeId').value = feeId;
        document.getElementById('payFeeTitle').textContent = title;
        document.getElementById('payAmount').textContent = '₹' + amount.toLocaleString();
        document.getElementById('payAmountHidden').value = amount;
        document.getElementById('paymentModal').style.display = 'flex';
        generateQR('upi://pay?pa=amrita.fees@sbi&pn=Amrita&am=' + amount + '&cu=INR');
    }
    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }
    function selectMode(mode, el) {
        document.querySelectorAll('.mode-option').forEach(o => o.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('payModeHidden').value = mode;
        document.querySelectorAll('.mode-content').forEach(c => c.classList.remove('active'));
        var content = document.getElementById('mode-' + mode);
        if (content) content.classList.add('active');
    }
    function generateQR(data) {
        var canvas = document.getElementById('qrCanvas');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var size = 200;
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, size, size);
        // Generate a simple visual QR pattern (decorative)
        ctx.fillStyle = '#000';
        var cellSize = 8;
        var margin = 20;
        // Corner markers
        function drawCorner(x, y) {
            ctx.fillRect(x, y, 7*cellSize, cellSize);
            ctx.fillRect(x, y, cellSize, 7*cellSize);
            ctx.fillRect(x+6*cellSize, y, cellSize, 7*cellSize);
            ctx.fillRect(x, y+6*cellSize, 7*cellSize, cellSize);
            ctx.fillRect(x+2*cellSize, y+2*cellSize, 3*cellSize, 3*cellSize);
        }
        drawCorner(margin, margin);
        drawCorner(size-margin-7*cellSize, margin);
        drawCorner(margin, size-margin-7*cellSize);
        // Data pattern (pseudo-random based on data string)
        var seed = 0;
        for (var i = 0; i < data.length; i++) seed += data.charCodeAt(i);
        for (var r = 0; r < 20; r++) {
            for (var c = 0; c < 20; c++) {
                seed = (seed * 1103515245 + 12345) & 0x7fffffff;
                if (seed % 3 === 0) {
                    var px = margin + c * cellSize;
                    var py = margin + r * cellSize;
                    // Skip corner areas
                    if ((px < margin+8*cellSize && py < margin+8*cellSize) ||
                        (px > size-margin-8*cellSize && py < margin+8*cellSize) ||
                        (px < margin+8*cellSize && py > size-margin-8*cellSize)) continue;
                    ctx.fillRect(px, py, cellSize, cellSize);
                }
            }
        }
        // Center text
        ctx.fillStyle = '#a4123f';
        ctx.font = 'bold 10px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('SCAN TO PAY', size/2, size-6);
    }
    </script>
<script src="../js/upload_validator.js"></script>
</body>
</html>

