<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require_once '../api/db.php';

$table_alias = 's';
require_once 'filter_logic.php';

$sql = "SELECT s.id, s.enrollment_no, s.name, s.batch, s.department as branch, s.section, s.semester,
        c.course_code, c.course_name,
        m.internal, m.external, m.total, m.grade,
        a.total_classes, a.classes_attended
        FROM students s
        CROSS JOIN courses c
        LEFT JOIN marks m ON m.student_id = s.id AND m.course_code = c.course_code
        LEFT JOIN attendance a ON a.student_id = s.id AND a.course_code = c.course_code
        WHERE 1=1 " . $filter_sql . "
        ORDER BY s.enrollment_no, c.course_code";

$stmt = $pdo->prepare($sql);
$stmt->execute($filter_params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=academic_report.csv');
$output = fopen('php://output', 'w');

fputcsv($output, ['Enrollment No', 'Student Name', 'Batch', 'Branch', 'Section', 'Semester', 'Course Code', 'Course Name', 'Internal Marks', 'External Marks', 'Total Marks', 'Grade', 'Total Classes', 'Classes Attended', 'Attendance %']);

foreach ($results as $row) {
    if ($row['internal'] === null && $row['total_classes'] === null) continue; // Skip if no marks and no attendance for this course
    
    $att_perc = ($row['total_classes'] > 0) ? round(($row['classes_attended'] / $row['total_classes']) * 100, 1) . '%' : '—';
    fputcsv($output, [
        $row['enrollment_no'],
        $row['name'],
        $row['batch'],
        $row['branch'],
        $row['section'],
        $row['semester'],
        $row['course_code'],
        $row['course_name'],
        $row['internal'] !== null ? $row['internal'] : '—',
        $row['external'] !== null ? $row['external'] : '—',
        $row['total'] !== null ? $row['total'] : '—',
        $row['grade'] !== null ? $row['grade'] : '—',
        $row['total_classes'] !== null ? $row['total_classes'] : '—',
        $row['classes_attended'] !== null ? $row['classes_attended'] : '—',
        $att_perc
    ]);
}
fclose($output);
exit();
