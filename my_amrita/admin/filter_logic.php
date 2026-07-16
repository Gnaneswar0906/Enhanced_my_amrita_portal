<?php
// Include after determining $table_alias (e.g. 's' for students, 'd' for documents JOIN students s)
$filter_batch = !empty($_GET['batch']) ? $_GET['batch'] : 'all';
$filter_branch = !empty($_GET['branch']) ? $_GET['branch'] : 'all';
$filter_section = !empty($_GET['section']) ? $_GET['section'] : 'all';
$filter_semester = !empty($_GET['semester']) ? $_GET['semester'] : 'all';

$filter_sql = '';
$filter_params = [];
$t_alias = (!empty($table_alias)) ? $table_alias . '.' : '';

if ($filter_batch !== 'all') {
    $filter_sql .= " AND {$t_alias}batch = ?";
    $filter_params[] = $filter_batch;
}
if ($filter_branch !== 'all') {
    $filter_sql .= " AND {$t_alias}department = ?";
    $filter_params[] = $filter_branch;
}
if ($filter_section !== 'all') {
    $filter_sql .= " AND {$t_alias}section = ?";
    $filter_params[] = $filter_section;
}
if ($filter_semester !== 'all') {
    $filter_sql .= " AND {$t_alias}semester = ?";
    $filter_params[] = intval($filter_semester);
}
?>
