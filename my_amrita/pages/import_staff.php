<?php
/**
 * Import Staff (Teachers, Wardens, Chief Wardens) into the database
 * Also updates all student usernames to email format:
 *   bl.en.u4cse23XXX@bl.students.amrita.edu
 */

require_once __DIR__ . '/../api/db.php';

echo "<h2>MyAmrita Staff Import & Student Username Update</h2>\n";
echo "<pre>\n";

// ============================================================
// 1. Teachers
// ============================================================
$teachers = [
    ['Dr. Peeta Basa Pati',         'bp_peeta@blr.amrita.edu',               'peeta'],
    ['Dr. Gopalakrishnan E. A',     'ea_gopalakrishnan@blr.amrita.edu',      'gopalkrishnan'],
    ['Dr. Sreevidya B',             'jain_vineetha@blr.amrita.edu',          'sreevidya'],
    ['Dr. Vineetha Jain K. V',      'b_sreevidya@blr.amrita.edu',           'vineetha'],
    ['Dr. Tripty Singh',            'tripty_singh@blr.amrita.edu',           'tripty'],
    ['Dr. Meena Belwal',            'b_meena@blr.amrita.edu',               'meena'],
    ['Dr. K Dinesh Kumar',          'kk_dinesh@blr.amrita.edu',             'dinesh'],
    ['Dr. Gurupriya M',             'm_gurupriya@blr.amrita.edu',           'gurupriya'],
    ['Vishwas H. N',                'hn_vishwas@blr.amrita.edu',            'vineetha'],
    ['Sreebha Bhaskaran',           'b_sreebha@blr.amrita.edu',             'sreebha'],
    ['Dr. Rajesh M.',               'm_rajesh@blr.amrita.edu',              'rajesh'],
    ['Dr. Nandu C. Nair',           'c_nandu@blr.amrita.edu',               'nandu'],
    ['Dr. Nidhin Prabhakar T. V',   'tv_nidhin@blr.amrita.edu',             'nidhin'],
    ['Dr. Sajitha Krishnan',        'k_sajitha@blr.amrita.edu',             'sajitha'],
    ['Dr. Sanghamitra Mishra',      'm_sanghamitra@blr.amrita.edu',         'sanghamitra'],
    ['Pooja Gowda',                 'g_pooja@blr.amrita.edu',               'pooja'],
    ['B Saranya Devi',              'b_saranya@blr.amrita.edu',             'saranya'],
    ['Dr. Nizampatnam Neelima',     'n_neelima@blr.amrita.edu',             'neelima'],
    ['Dr. Bhavana V',               'v_bhavana@blr.amrita.edu',             'bhavana'],
    ['V. Sailaja',                  'v_sailaja@blr.amrita.edu',             'sailaja'],
    ['K. Sireesha',                 'k_sireesha@blr.amrita.edu',            'sireesha'],
    ['Sudha Yadav',                 'sy_sudha@blr.amrita.edu',              'sudha'],
    ['Dr. Sarada Jayan',            'j_sarada@blr.amrita.edu',              'sarada'],
    ['Dr. K. Murali',               'k_murali@blr.amrita.edu',              'murali'],
    ['Mamatha T. M',                'tm_mamatha@blr.amrita.edu',            'mamatha'],
    ['H. Manjunatha',               'h_manjunath@blr.amrita.edu',           'manjunatha'],
    ['Sriram Devanathan',           'sriram@blr.amrita.edu',                'sriram'],
    ['Dr. Pramod R',                'r_pramod@blr.amrita.edu',              'pramod'],
    ['Dr. Prashanth B N',           'bn_prashanth@blr.amrita.edu',          'prashanth'],
    ['Manoj P',                     'manoj@amrita.edu',                     'manoj'],
];

echo "=== ADDING TEACHERS ===\n";
$added_t = 0; $skipped_t = 0; $errors_t = 0;

foreach ($teachers as $t) {
    $name     = $t[0];
    $username = $t[1];
    $password = $t[2];
    $hashed   = password_hash($password, PASSWORD_BCRYPT);

    try {
        // Check if already exists
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            echo "SKIP (exists): $username ($name)\n";
            $skipped_t++;
            continue;
        }

        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, email) VALUES (?, ?, 'teacher', ?, ?)");
        $stmt->execute([$username, $hashed, $name, $username]);
        echo "ADDED teacher: $username ($name) — password: $password\n";
        $added_t++;
    } catch (PDOException $e) {
        echo "ERROR: $username — " . $e->getMessage() . "\n";
        $errors_t++;
    }
}
echo "Teachers: Added=$added_t, Skipped=$skipped_t, Errors=$errors_t\n\n";

// ============================================================
// 2. Wardens (Level-2 approval)
// ============================================================
$wardens = [
    // Girls hostel wardens
    ['Gauri A Klukarni',        'a_gouri@blr.amrita.edu',           'gauri',        'warden',        '1ST FLOOR - Girls'],
    ['Priyanka S',              's_priyanka@blr.amrita.edu',        'priyanka',     'warden',        '2ND FLOOR - Girls'],
    // Boys hostel wardens
    ['Somaiah A B',             'ab_somaiah@blr.amrita.edu',        'somaiah',      'warden',        '7TH FLOOR - Boys'],
    ['Sanju BM',                'bm_sanju@blr.amrita.edu',         'sanju',        'warden',        '8TH FLOOR - Boys'],
    ['Sanjeevappa pujar',       'p_sanjeevappa@blr.amrita.edu',     'sanjeevappa',  'warden',        '6TH FLOOR - Boys'],
    ['Mitesh T Acharya',        't_mitesh@blr.amrita.edu',          'mitesh',       'warden',        '3rd Floor - Boys'],
];

echo "=== ADDING WARDENS ===\n";
$added_w = 0; $skipped_w = 0; $errors_w = 0;

foreach ($wardens as $w) {
    $name     = $w[0];
    $username = $w[1];
    $password = $w[2];
    $role     = $w[3];
    $dept     = $w[4];
    $hashed   = password_hash($password, PASSWORD_BCRYPT);

    try {
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            echo "SKIP (exists): $username ($name)\n";
            $skipped_w++;
            continue;
        }

        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, email, department) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hashed, $role, $name, $username, $dept]);
        echo "ADDED warden: $username ($name) — floor: $dept\n";
        $added_w++;
    } catch (PDOException $e) {
        echo "ERROR: $username — " . $e->getMessage() . "\n";
        $errors_w++;
    }
}
echo "Wardens: Added=$added_w, Skipped=$skipped_w, Errors=$errors_w\n\n";

// ============================================================
// 3. Chief Wardens / Assistant Chief Wardens (Level-3 approval)
// ============================================================
$chief_wardens = [
    ['Ramki',                   'rama_krishan@blr.amrita.edu',              'ramki',                'chief_warden', 'Chief Warden - Boys & Girls'],
    ['Latha g Rao',             'g_latha@blr.amrita.edu',                   'latha',                'chief_warden', 'Assistant Chief Warden - Girls'],
    ['Venkatachalapathi V',     'v_venkatachalapathi@blr.amrita.edu',       'Venkatachalapathi',    'chief_warden', 'Assistant Chief Warden - Boys'],
];

echo "=== ADDING CHIEF WARDENS ===\n";
$added_cw = 0; $skipped_cw = 0; $errors_cw = 0;

foreach ($chief_wardens as $cw) {
    $name     = $cw[0];
    $username = $cw[1];
    $password = $cw[2];
    $role     = $cw[3];
    $dept     = $cw[4];
    $hashed   = password_hash($password, PASSWORD_BCRYPT);

    try {
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            echo "SKIP (exists): $username ($name)\n";
            $skipped_cw++;
            continue;
        }

        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, email, department) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $hashed, $role, $name, $username, $dept]);
        echo "ADDED chief_warden: $username ($name) — $dept\n";
        $added_cw++;
    } catch (PDOException $e) {
        echo "ERROR: $username — " . $e->getMessage() . "\n";
        $errors_cw++;
    }
}
echo "Chief Wardens: Added=$added_cw, Skipped=$skipped_cw, Errors=$errors_cw\n\n";

// ============================================================
// 4. Update ALL student usernames to email format
//    BL.EN.U4CSE23146 → bl.en.u4cse23146@bl.students.amrita.edu
// ============================================================
echo "=== UPDATING STUDENT USERNAMES ===\n";

try {
    // Update students table
    $result1 = $pdo->exec("UPDATE students SET username = CONCAT(LOWER(enrollment_no), '@bl.students.amrita.edu') WHERE enrollment_no IS NOT NULL");
    echo "Updated $result1 rows in students table.\n";

    // Update users table for student role
    $result2 = $pdo->exec("
        UPDATE users u
        JOIN students s ON u.linked_student_id = s.id
        SET u.username = CONCAT(LOWER(s.enrollment_no), '@bl.students.amrita.edu')
        WHERE u.role = 'student' AND s.enrollment_no IS NOT NULL
    ");
    echo "Updated $result2 rows in users table.\n";

    echo "Student usernames updated successfully!\n";
    echo "New format: bl.en.u4cse23XXX@bl.students.amrita.edu\n";
    echo "Passwords remain the same (last 5 digits of enrollment number).\n";
} catch (PDOException $e) {
    echo "ERROR updating student usernames: " . $e->getMessage() . "\n";
}

// ============================================================
// 5. Delete old seed warden/chief_warden users
// ============================================================
echo "\n=== REMOVING OLD SEED WARDEN/CHIEF_WARDEN ===\n";
try {
    $del = $pdo->exec("DELETE FROM users WHERE username IN ('warden', 'chiefwarden')");
    echo "Removed $del old seed entries (warden, chiefwarden).\n";
} catch (PDOException $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "IMPORT COMPLETE!\n";
echo "========================================\n";
echo "\nSAMPLE LOGIN CREDENTIALS:\n";
echo "  Teacher:       bp_peeta@blr.amrita.edu / peeta\n";
echo "  Warden:        a_gouri@blr.amrita.edu / gauri\n";
echo "  Chief Warden:  g_latha@blr.amrita.edu / latha\n";
echo "  Student:       bl.en.u4cse23146@bl.students.amrita.edu / 23146\n";
echo "</pre>\n";
?>
