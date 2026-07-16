<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'submit_request') {
        $type        = trim($_POST['request_type'] ?? '');
        $category    = trim($_POST['category'] ?? 'Service');
        $desc        = trim($_POST['description'] ?? '');
        $custom_type = trim($_POST['custom_request_type'] ?? '');
        $routed_to   = trim($_POST['routed_to'] ?? 'admin');
        if ($type && $desc) {
            $final_type = ($type === 'Other' && $custom_type) ? $custom_type : $type;
            $stmt = $pdo->prepare('INSERT INTO services (student_id, service_type, category, description, routed_to, custom_request_type) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$student_id, $final_type, $category, $desc, $routed_to, $custom_type ?: null]);
            $msg = 'success';
        } else {
            $msg = 'error';
        }
    }
}

// Fetch all services & complaints for this student
$stmt = $pdo->prepare('SELECT * FROM services WHERE student_id = ? ORDER BY created_at DESC');
$stmt->execute([$student_id]);
$services = $stmt->fetchAll();

$oldComplaints = [];
try {
    $stmt2 = $pdo->prepare('SELECT id, subject as service_type, description, status, created_at, "Complaint" as category FROM complaints WHERE student_id = ? ORDER BY created_at DESC');
    $stmt2->execute([$student_id]);
    $oldComplaints = $stmt2->fetchAll();
} catch(Exception $e) {}

$all_items = array_merge($services, $oldComplaints);
usort($all_items, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita - Services & Complaints</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .route-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; }
        .route-badge.teacher { background:#e8f5e9; color:#2e7d32; }
        .route-badge.admin { background:#e3f2fd; color:#1565c0; }
        .route-badge.warden { background:#fff3e0; color:#e65100; }
        #customTypeField { display:none; margin-top:8px; }
        #customRouteField { display:none; margin-top:8px; }
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
        <a href="../home.php">Home</a> <span class="sep">/</span> Services & Complaints
    </div>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-cogs"></i> Services & Complaints</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <?php if ($msg === 'success'): ?>
            <div class="msg-success"><i class="fa fa-check-circle"></i> Request submitted successfully! It has been routed to the appropriate authority.</div>
        <?php elseif ($msg === 'error'): ?>
            <div class="msg-error"><i class="fa fa-times-circle"></i> Please fill all required fields.</div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="filter-bar">
            <button class="filter-btn active" onclick="filterItems('all', this)">All</button>
            <button class="filter-btn" onclick="filterItems('Service', this)">Services</button>
            <button class="filter-btn" onclick="filterItems('Complaint', this)">Complaints</button>
        </div>

        <!-- Existing Requests -->
        <div class="card">
            <h2 class="card-title">Request History (<?php echo count($all_items); ?>)</h2>
            <?php if (empty($all_items)): ?>
                <div class="empty-state"><i class="fa fa-cogs"></i><p>No requests found.</p></div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>#</th><th>Type</th><th>Category</th><th>Routed To</th><th>Description</th><th>Response</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_items as $i => $item): ?>
                        <tr class="filterable-row" data-category="<?php echo htmlspecialchars($item['category'] ?? 'Service'); ?>">
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($item['service_type'] ?? $item['subject'] ?? ''); ?></strong></td>
                            <td>
                                <?php
                                $cat = $item['category'] ?? 'Service';
                                $catClass = $cat === 'Complaint' ? 'badge-failed' : 'badge-approved';
                                echo "<span class='badge $catClass'>$cat</span>";
                                ?>
                            </td>
                            <td>
                                <?php
                                $rt = $item['routed_to'] ?? '';
                                if ($rt) {
                                    $rtClass = match($rt) { 'teacher' => 'teacher', 'warden','chief_warden' => 'warden', default => 'admin' };
                                    echo "<span class='route-badge $rtClass'><i class='fa fa-arrow-right'></i> " . ucfirst(str_replace('_',' ',$rt)) . "</span>";
                                } else {
                                    echo "<span style='color:#999;'>—</span>";
                                }
                                ?>
                            </td>
                            <td style="max-width:200px;"><?php echo htmlspecialchars($item['description']); ?></td>
                            <td style="max-width:180px;">
                                <?php if (!empty($item['response'])): ?>
                                    <div style="font-size:12px; color:#2e7d32; background:#e8f5e9; padding:4px 8px; border-radius:6px;"><i class="fa fa-reply"></i> <?php echo htmlspecialchars($item['response']); ?></div>
                                <?php else: ?>
                                    <span style="color:#999;">Awaiting</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?php echo strtolower(str_replace(' ','-',$item['status'])); ?>"><?php echo $item['status']; ?></span></td>
                            <td><?php echo date('d M Y', strtotime($item['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Submit New Request -->
        <div class="card form-section">
            <h3><i class="fa fa-plus-circle"></i> Submit New Request</h3>
            <form method="POST">
                <input type="hidden" name="action" value="submit_request">
                <input type="hidden" name="routed_to" id="routed_to_field" value="admin">
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category" id="req_category" onchange="toggleTypeField()">
                            <option value="Service">Service Request</option>
                            <option value="Complaint">Complaint</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Request Type</label>
                        <select class="form-control" name="request_type" id="req_type" onchange="handleTypeChange()">
                            <option value="">Select type...</option>
                            <option value="Transcript Request" data-route="admin">Transcript Request</option>
                            <option value="ID Card Replacement" data-route="admin">ID Card Replacement</option>
                            <option value="Fee Receipt" data-route="admin">Fee Receipt</option>
                            <option value="Bonafide Certificate" data-route="teacher">Bonafide Certificate</option>
                            <option value="Academic" data-route="teacher">Academic</option>
                            <option value="Faculty Related" data-route="teacher">Faculty Related</option>
                            <option value="Wi-Fi Access" data-route="admin">Wi-Fi Access Issue</option>
                            <option value="Hostel Maintenance" data-route="warden">Hostel Maintenance</option>
                            <option value="Hostel Issue" data-route="warden">Hostel Issue</option>
                            <option value="Library Access" data-route="admin">Library Access Issue</option>
                            <option value="Other" data-route="admin">Other</option>
                        </select>
                        <div id="customTypeField">
                            <input type="text" class="form-control" name="custom_request_type" placeholder="Specify your request type...">
                        </div>
                    </div>
                </div>
                <!-- Routing selector for "Other" -->
                <div id="customRouteField" style="margin-bottom:14px;">
                    <label>Send this request to:</label>
                    <select class="form-control" id="custom_route_select" onchange="updateRoute()">
                        <option value="teacher">Teacher (Bonafide Certificate, Academic, Faculty Related)</option>
                        <option value="admin" selected>Admin (General)</option>
                        <option value="warden">Warden / Chief Warden (Hostel Maintenance, Hostel Issue)</option>
                    </select>
                </div>
                <!-- Routing indicator -->
                <div id="routeIndicator" style="display:none; margin-bottom:14px; padding:10px 14px; background:#f0f4ff; border-radius:8px; font-size:12px; color:#333;">
                    <i class="fa fa-arrow-right" style="color:#a4123f;"></i> This request will be sent to: <strong id="routeLabel">Admin</strong>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Description</label>
                    <textarea class="form-control" name="description" placeholder="Describe your request or complaint in detail..." required></textarea>
                </div>
                <button type="submit" class="submit-btn"><i class="fa fa-paper-plane"></i> Submit Request</button>
            </form>
        </div>
    </div>

    <script>
    function filterItems(cat, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.filterable-row').forEach(row => {
            row.style.display = (cat === 'all' || row.getAttribute('data-category') === cat) ? '' : 'none';
        });
    }
    function toggleTypeField() {
        var cat = document.getElementById('req_category').value;
        var typeSelect = document.getElementById('req_type');
        if (cat === 'Complaint') {
            typeSelect.innerHTML = '<option value="">Select type...</option><option value="Hostel Issue" data-route="warden">Hostel Issue</option><option value="Hostel Maintenance" data-route="warden">Hostel Maintenance</option><option value="Infrastructure" data-route="admin">Infrastructure</option><option value="Faculty Related" data-route="teacher">Faculty Related</option><option value="Academic" data-route="teacher">Academic</option><option value="Other" data-route="admin">Other</option>';
        } else {
            typeSelect.innerHTML = '<option value="">Select type...</option><option value="Transcript Request" data-route="admin">Transcript Request</option><option value="ID Card Replacement" data-route="admin">ID Card Replacement</option><option value="Fee Receipt" data-route="admin">Fee Receipt</option><option value="Bonafide Certificate" data-route="teacher">Bonafide Certificate</option><option value="Academic" data-route="teacher">Academic</option><option value="Faculty Related" data-route="teacher">Faculty Related</option><option value="Wi-Fi Access" data-route="admin">Wi-Fi Access Issue</option><option value="Hostel Maintenance" data-route="warden">Hostel Maintenance</option><option value="Hostel Issue" data-route="warden">Hostel Issue</option><option value="Library Access" data-route="admin">Library Access Issue</option><option value="Other" data-route="admin">Other</option>';
        }
        handleTypeChange();
    }
    function handleTypeChange() {
        var sel = document.getElementById('req_type');
        var opt = sel.options[sel.selectedIndex];
        var customField = document.getElementById('customTypeField');
        var customRouteField = document.getElementById('customRouteField');
        var routeInd = document.getElementById('routeIndicator');
        var routeLabel = document.getElementById('routeLabel');
        var routeField = document.getElementById('routed_to_field');
        
        if (sel.value === 'Other') {
            customField.style.display = 'block';
            customRouteField.style.display = 'block';
            routeInd.style.display = 'block';
            // Use the custom route selector
            var customRoute = document.getElementById('custom_route_select').value;
            routeField.value = customRoute;
            var labels = { 'admin': 'Admin', 'teacher': 'Faculty/Teacher', 'warden': 'Warden/Chief Warden' };
            routeLabel.textContent = labels[customRoute] || 'Admin';
        } else {
            customField.style.display = 'none';
            customRouteField.style.display = 'none';
            if (sel.value) {
                var route = opt.getAttribute('data-route') || 'admin';
                routeField.value = route;
                var labels = { 'admin': 'Admin', 'teacher': 'Faculty/Teacher', 'warden': 'Warden/Chief Warden' };
                routeLabel.textContent = labels[route] || 'Admin';
                routeInd.style.display = 'block';
            } else {
                routeInd.style.display = 'none';
            }
        }
    }
    function updateRoute() {
        var customRoute = document.getElementById('custom_route_select').value;
        document.getElementById('routed_to_field').value = customRoute;
        var labels = { 'admin': 'Admin', 'teacher': 'Faculty/Teacher', 'warden': 'Warden/Chief Warden' };
        document.getElementById('routeLabel').textContent = labels[customRoute] || 'Admin';
    }
    </script>
</body>
</html>
