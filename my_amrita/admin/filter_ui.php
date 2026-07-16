<?php
$filter_batch = !empty($_GET['batch']) ? $_GET['batch'] : 'all';
$filter_branch = !empty($_GET['branch']) ? $_GET['branch'] : 'all';
$filter_section = !empty($_GET['section']) ? $_GET['section'] : 'all';
$filter_semester = !empty($_GET['semester']) ? $_GET['semester'] : 'all';

$batches = ['2022-2026', '2023-2027', '2024-2028', '2025-2029'];
$branches = [
    'Computer Science & Engineering' => 'Computer Science & Engineering',
    'Artificial Intelligence & Data Science' => 'Artificial Intelligence & Data Science',
    'Robotics & Artificial Intelligence' => 'Robotics & Artificial Intelligence',
    'Electronics & Communication' => 'Electronics & Communication',
    'Electrical & Electronics' => 'Electrical & Electronics',
    'Electronics & Computer' => 'Electronics & Computer',
    'Mechanical Engineering' => 'Mechanical Engineering'
];
$sections = ['A', 'B', 'C', 'D'];
?>
<?php
// Collect all unique GET parameters to pass them as hidden inputs so filters don't overwrite them
$hidden_inputs = '';
foreach ($_GET as $key => $val) {
    if (!in_array($key, ['batch', 'branch', 'section', 'semester', 'course', 'student_id'])) {
        $hidden_inputs .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($val) . '">';
    }
}
?>
<div class="card form-section" style="background:#fff; border-radius:10px; padding:20px; border:1px solid #e8e8e8; margin-bottom:20px;">
    <h3 style="margin-top:0; color:#a4123f; font-size:15px; margin-bottom:15px;"><i class="fa fa-filter"></i> Cohort Filter</h3>
    <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%; margin:0;">
        <?php echo $hidden_inputs; ?>
        <label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Batch:</label>
        <select name="batch" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
            <option value="all" <?php echo $filter_batch==='all'?'selected':''; ?>>All Batches</option>
            <?php foreach ($batches as $b): ?>
                <option value="<?php echo $b; ?>" <?php echo $filter_batch===$b?'selected':''; ?>><?php echo $b; ?></option>
            <?php endforeach; ?>
        </select>

        <label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Branch:</label>
        <select name="branch" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
            <option value="all" <?php echo $filter_branch==='all'?'selected':''; ?>>All Branches</option>
            <?php foreach ($branches as $full => $short): ?>
                <option value="<?php echo htmlspecialchars($full); ?>" <?php echo $filter_branch===$full?'selected':''; ?>><?php echo $short; ?> (<?php echo htmlspecialchars($full); ?>)</option>
            <?php endforeach; ?>
        </select>

        <label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Section:</label>
        <select name="section" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
            <option value="all" <?php echo $filter_section==='all'?'selected':''; ?>>All Sections</option>
            <?php foreach ($sections as $sec): ?>
                <option value="<?php echo $sec; ?>" <?php echo $filter_section===$sec?'selected':''; ?>><?php echo $sec; ?></option>
            <?php endforeach; ?>
        </select>

        <label style="font-size:11px; font-weight:600; color:#888; text-transform:uppercase;">Semester:</label>
        <select name="semester" style="padding:6px 12px; border:1px solid #e0e0e0; border-radius:6px; font-size:13px; font-family:'Inter',sans-serif;">
            <option value="all" <?php echo $filter_semester==='all'?'selected':''; ?>>All Semesters</option>
            <?php for ($i=1; $i<=8; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo $filter_semester==strval($i)?'selected':''; ?>>Semester <?php echo $i; ?></option>
            <?php endfor; ?>
        </select>

        <?php if (isset($extra_filters)) echo $extra_filters; ?>

        <button type="submit" style="background:#1a1a2e; color:#fff; border:none; padding:7px 16px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;"><i class="fa fa-filter"></i> Filter</button>
        
        <?php if (isset($filter_count)): ?>
            <span style="margin-left:auto; padding:5px 14px; background:linear-gradient(135deg,#a4123f,#d4264f); color:#fff; border-radius:8px; font-size:12px; font-weight:600;"><?php echo $filter_count; ?> Records</span>
        <?php endif; ?>
    </form>
</div>
