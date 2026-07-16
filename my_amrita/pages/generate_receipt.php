<?php
require_once '../api/auth.php';
require_once '../api/db.php';

if (!isset($_GET['id'])) {
    die("Payment ID missing.");
}
$payment_id = intval($_GET['id']);

$stmt = $pdo->prepare('SELECT p.*, s.name, s.enrollment_no, s.batch FROM payments p JOIN students s ON p.student_id = s.id WHERE p.id = ? AND p.student_id = ?');
$stmt->execute([$payment_id, $student_id]);
$payment = $stmt->fetch();

if (!$payment || $payment['status'] !== 'Paid') {
    die("Invalid or pending payment.");
}

function getIndianCurrency(float $number) {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(0 => '', 1 => 'One', 2 => 'Two',
        3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
        7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
        13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
        70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety');
    $digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    return ($Rupees ? $Rupees . 'Rupees' : '');
}

$amt_words = getIndianCurrency($payment['amount']);
// Clean up extra spaces
$amt_words = trim(preg_replace('/\s+/', ' ', $amt_words));

$receipt_no = "2024-25/ODD/STP/" . str_pad($payment['id'], 5, '0', STR_PAD_LEFT);
$receipt_date = date('d/m/Y', strtotime($payment['payment_date']));
$stu_name = htmlspecialchars($payment['name']);
$stu_enroll = htmlspecialchars($payment['enrollment_no']);
$amt = number_format($payment['amount']);
$particulars = htmlspecialchars($payment['payment_type'] ?? 'Fee');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?php echo $receipt_no; ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #e0e0e0; margin: 0; padding: 40px; display: flex; justify-content: center; }
        .receipt-container { background: #fff; width: 210mm; min-height: 297mm; padding: 40mm 20mm; box-sizing: border-box; box-shadow: 0 0 10px rgba(0,0,0,0.1); border: 2px solid #333; position: relative; }
        .logo-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #a4123f; padding-bottom: 20px; }
        .logo-header img { max-width: 400px; }
        .campus-title { text-align: center; font-size: 20px; font-weight: bold; margin: 20px 0; }
        .receipt-title { text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 40px; letter-spacing: 2px; }
        
        .receipt-meta { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 40px; font-size: 16px; }
        
        .receipt-body { font-size: 16px; line-height: 1.8; margin-bottom: 40px; }
        .receipt-body strong { font-weight: bold; }
        
        .particulars-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .particulars-table th, .particulars-table td { border: 2px solid #333; padding: 12px; text-align: left; font-size: 16px; }
        .particulars-table th { font-weight: normal; }
        
        .totals-section { display: grid; grid-template-columns: 250px 1fr; gap: 10px; margin-bottom: 10px; font-size: 16px; }
        
        .signature-section { margin-top: 100px; text-align: right; font-size: 16px; margin-bottom: 60px; }
        .footer-note { font-size: 12px; text-align: left; border-top: 1px solid #ccc; padding-top: 10px; color: #333; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .receipt-container { box-shadow: none; border: 1px solid #333; width: 100%; min-height: auto; padding: 20mm; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="logo-header">
            <!-- Using a fallback text/style if image is not present, but using the official logo from existing site structure if possible -->
            <h1 style="color:#a4123f; margin:0; font-size:42px;">AMRITA</h1>
            <div style="color:#a4123f; font-size:16px; letter-spacing:2px; margin-top:5px;">VISHWA VIDYAPEETHAM</div>
        </div>
        
        <div class="campus-title">Bengaluru Campus</div>
        
        <div class="receipt-title">RECEIPT</div>
        
        <div class="receipt-meta">
            <div>No : <?php echo $receipt_no; ?></div>
            <div>Date : <?php echo $receipt_date; ?></div>
        </div>
        
        <div class="receipt-body">
            Received with thanks from <strong><?php echo $stu_name; ?></strong> Enrollment No.<br>
            <strong><?php echo $stu_enroll; ?></strong> a sum of RS <strong><?php echo $payment['amount']; ?></strong> by Online payment.
        </div>
        
        <table class="particulars-table">
            <tr>
                <th style="width:70%;">Particulars</th>
                <th>Amount</th>
            </tr>
            <tr>
                <td><?php echo $particulars; ?></td>
                <td><?php echo $payment['amount']; ?></td>
            </tr>
        </table>
        
        <div class="totals-section">
            <div>Total Amount</div>
            <div><?php echo $payment['amount']; ?></div>
        </div>
        
        <div class="totals-section">
            <div style="font-weight:bold;">Amount in words :</div>
            <div><?php echo $amt_words; ?></div>
        </div>
        
        <div class="signature-section">
            Cash Officer/ Cashier
        </div>
        
        <div class="footer-note">
            This is a computer-generated document. No signature is required.
        </div>
    </div>
    
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
