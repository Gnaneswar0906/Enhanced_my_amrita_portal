<?php
/**
 * My Amrita – Auth Guard
 * Include at the top of every protected page.
 * Redirects to login.php if not authenticated.
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    if (isset($_SESSION['student_id'])) {
        session_destroy();
    }
    header('Location: ../login.php');
    exit();
}

$user_id      = $_SESSION['user_id'];
$user_role     = $_SESSION['role'] ?? 'student';
$user_name     = $_SESSION['user_name'] ?? '';
$student_id    = $_SESSION['student_id'] ?? null;
$student_name  = $_SESSION['student_name'] ?? $user_name;
$enrollment    = $_SESSION['enrollment_no'] ?? '';

/**
 * Require a specific role to access a page.
 */
function require_role($roles) {
    $current = $_SESSION['role'] ?? 'student';
    if (is_string($roles)) $roles = [$roles];
    if (!in_array($current, $roles)) {
        $base = match($current) {
            'admin'        => '../admin/home.php',
            'teacher'      => '../teacher/home.php',
            'warden'       => '../warden/home.php',
            'chief_warden' => '../chief_warden/home.php',
            default        => '../home.php',
        };
        header('Location: ' . $base);
        exit();
    }
}
