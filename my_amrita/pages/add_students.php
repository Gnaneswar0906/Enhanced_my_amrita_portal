<?php
/**
 * Add all B.Tech CSE 2023 students to the database
 * Username: Registration number (e.g., BL.EN.U4CSE23146)
 * Password: Last 5 digits of registration number (e.g., 23146)
 */

require_once __DIR__ . '/../api/db.php';

// All students extracted from the class list images
$students = [
    ['BL.EN.U4CSE23101', 'Aditya Sanjay Pavale', 'M', 'H'],
    ['BL.EN.U4CSE23102', 'AILURI RAHUL REDDY', 'M', 'H'],
    ['BL.EN.U4CSE23103', 'ANANTHAJITH A', 'M', 'H'],
    ['BL.EN.U4CSE23104', 'ANNAMRAJU PRANAV SATHWIK', 'M', 'H'],
    ['BL.EN.U4CSE23106', 'Akshat Govil', 'M', 'H'],
    ['BL.EN.U4CSE23107', 'Thammana Akshay', 'M', 'D'],
    ['BL.EN.U4CSE23108', 'Amogh Babi', 'M', 'H'],
    ['BL.EN.U4CSE23109', 'Amogh Misra', 'M', 'D'],
    ['BL.EN.U4CSE23110', 'BANDA VANDHANA', 'F', 'H'],
    ['BL.EN.U4CSE23111', 'BURUVU SEVANAND SAKETH SAI', 'M', 'H'],
    ['BL.EN.U4CSE23112', 'Bandari Amulya', 'F', 'H'],
    ['BL.EN.U4CSE23113', 'CHIRRA RAVINDRA REDDY', 'M', 'H'],
    ['BL.EN.U4CSE23114', 'Challa Vishwa Chaitanya Reddy', 'M', 'D'],
    ['BL.EN.U4CSE23115', 'DEBAPRIYA KUNDU', 'F', 'D'],
    ['BL.EN.U4CSE23116', 'DHRITHI JUVVA', 'F', 'H'],
    ['BL.EN.U4CSE23117', 'DISHITA DASHORA', 'F', 'H'],
    ['BL.EN.U4CSE23118', 'DUDYALA HARIKEERTHAN REDDY', 'M', 'D'],
    ['BL.EN.U4CSE23120', 'Dhanush P', 'M', 'D'],
    ['BL.EN.U4CSE23121', 'Dhruvika Koka', 'F', 'H'],
    ['BL.EN.U4CSE23122', 'GANGAM SAI SAMARTH', 'M', 'D'],
    ['BL.EN.U4CSE23123', 'GEETHIKA YARABOLU', 'F', 'D'],
    ['BL.EN.U4CSE23124', 'Ganavi S P', 'F', 'H'],
    ['BL.EN.U4CSE23125', 'Haren A', 'M', 'H'],
    ['BL.EN.U4CSE23126', 'JAMMULA VEERA VENKATA SAI AKSHAY', 'M', 'D'],
    ['BL.EN.U4CSE23127', 'Janhavi Harish Kulkarni', 'F', 'D'],
    ['BL.EN.U4CSE23128', 'KADALI NIKSHITA', 'F', 'H'],
    ['BL.EN.U4CSE23129', 'KANGATI SHASHIDHAR REDDY', 'M', 'H'],
    ['BL.EN.U4CSE23130', 'KANUKOLLU LAKSHMI ANVITHA', 'F', 'H'],
    ['BL.EN.U4CSE23131', 'KOLLURU SAHITHI', 'F', 'H'],
    ['BL.EN.U4CSE23132', 'M.SAI MEGHANA', 'F', 'H'],
    ['BL.EN.U4CSE23134', 'MAYTRAI SHARMA', 'F', 'H'],
    ['BL.EN.U4CSE23135', 'MONEESH KUMAR PASUMARTHI', 'M', 'D'],
    ['BL.EN.U4CSE23136', 'MUKKARA SREEJA', 'F', 'H'],
    ['BL.EN.U4CSE23137', 'NAGAM SUDHA SRAVANTHI', 'F', 'H'],
    ['BL.EN.U4CSE23138', 'NAMBURI KRISHNA AKSHATH VARMA', 'M', 'H'],
    ['BL.EN.U4CSE23139', 'NITESH N', 'M', 'D'],
    ['BL.EN.U4CSE23140', 'PATCHALA HANEESH', 'M', 'H'],
    ['BL.EN.U4CSE23141', 'POLA PAVITRA KUMARI', 'F', 'H'],
    ['BL.EN.U4CSE23142', 'PORIPIREDDI KARTHIK', 'M', 'H'],
    ['BL.EN.U4CSE23143', 'PRANAV SHARMA K L', 'M', 'H'],
    ['BL.EN.U4CSE23144', 'Pranav Parimi', 'M', 'D'],
    ['BL.EN.U4CSE23145', 'RANGA VEERA MANIKANTA DEEKSHITH MUNUGANTI', 'M', 'H'],
    ['BL.EN.U4CSE23146', 'REPALLE GNANESWAR', 'M', 'H'],
    ['BL.EN.U4CSE23147', 'Reddi Tejeesh Sai', 'M', 'H'],
    ['BL.EN.U4CSE23148', 'Rochit Madamanchi', 'M', 'D'],
    ['BL.EN.U4CSE23149', 'S RUSHIL', 'M', 'D'],
    ['BL.EN.U4CSE23150', 'S Udhaya Sankari', 'F', 'D'],
    ['BL.EN.U4CSE23151', 'SAI RITHVIK AMBATI', 'M', 'H'],
    ['BL.EN.U4CSE23152', 'SASWAT SUBHANKAR', 'M', 'H'],
    ['BL.EN.U4CSE23153', 'SUHAAG ARAVIND PAMALI', 'M', 'D'],
    ['BL.EN.U4CSE23154', 'Saathwika B Parvathy', 'F', 'D'],
    ['BL.EN.U4CSE23155', 'Sudhan Shankar', 'M', 'D'],
    ['BL.EN.U4CSE23157', 'TADURI ABHICHAKRA', 'M', 'H'],
    ['BL.EN.U4CSE23158', 'TRIPURARI SAMEER KUMAR NARASIMHA', 'M', 'H'],
    ['BL.EN.U4CSE23159', 'UPPALA KRISHNA KUSHAL', 'M', 'H'],
    ['BL.EN.U4CSE23160', 'VELURU VENKATA VARSHITH', 'M', 'H'],
    ['BL.EN.U4CSE23161', 'SANTOSH KUMAR', 'M', 'H'],
    ['BL.EN.U4CSE23162', 'REBBA VENKATA SKANDA VYVASWATH', 'M', 'H'],
    ['BL.EN.U4CSE23163', 'KANUSU MANIKANTA SAI', 'M', 'H'],
    ['BL.EN.U4CSE23164', 'SAKKANI SUSHANTH', 'M', 'H'],
    ['BL.EN.U4CSE23165', 'RITU SOLANKI', 'F', 'H'],
    ['BL.EN.U4CSE23166', 'LANGATI NIKHIL REDDY', 'M', 'H'],
    ['BL.EN.U4CSE23167', 'Shaman K Vidyananda', 'M', 'D'],
];

echo "<h2>Adding Students to MyAmrita Database</h2>\n";
echo "<pre>\n";

$added = 0;
$skipped = 0;
$errors = 0;

foreach ($students as $s) {
    $enrollment = $s[0];  // e.g., BL.EN.U4CSE23146
    $name = $s[1];
    $gender = $s[2];
    $hostelStatus = $s[3]; // H = Hostler, D = Day Scholar

    // Username = registration number (e.g., BL.EN.U4CSE23146)
    $username = $enrollment;

    // Password = last 5 digits of registration number (e.g., 23146)
    $password = substr($enrollment, -5);
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Generate email from enrollment number
    $rollNum = substr($enrollment, -5);
    $emailPrefix = strtolower(str_replace(' ', '.', $name));
    $email = strtolower(str_replace([' ', '.', "'"], ['', '', ''], $name)) . '@am.amrita.edu';

    // Hostel details
    $hostelRoom = null;
    $hostelBlock = null;
    if ($hostelStatus === 'H') {
        // Assign hostel rooms based on gender
        $blockLetter = ($gender === 'M') ? 'A' : 'B';
        $roomNum = rand(101, 410);
        $hostelRoom = $blockLetter . '-' . $roomNum;
        $hostelBlock = 'Block ' . $blockLetter;
    }

    try {
        // Check if student already exists
        $checkStmt = $pdo->prepare("SELECT id FROM students WHERE enrollment_no = ?");
        $checkStmt->execute([$enrollment]);
        
        if ($checkStmt->fetch()) {
            // Update existing student's username and password
            $updateStmt = $pdo->prepare("UPDATE students SET username = ?, password = ? WHERE enrollment_no = ?");
            $updateStmt->execute([$username, $hashedPassword, $enrollment]);
            
            // Also update/insert in users table
            $checkUserStmt = $pdo->prepare("SELECT id FROM users WHERE linked_student_id = (SELECT id FROM students WHERE enrollment_no = ?)");
            $checkUserStmt->execute([$enrollment]);
            $existingUser = $checkUserStmt->fetch();
            
            if ($existingUser) {
                $updateUserStmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                $updateUserStmt->execute([$username, $hashedPassword, $existingUser['id']]);
            }
            
            echo "UPDATED: $enrollment ($name) - username: $username, password: $password\n";
            $skipped++;
            continue;
        }

        // Insert into students table
        $stmt = $pdo->prepare("INSERT INTO students (enrollment_no, username, password, name, email, phone, department, semester, dob, address, hostel_room, hostel_block) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $enrollment,
            $username,
            $hashedPassword,
            $name,
            $email,
            '90000' . $rollNum, // placeholder phone
            'Computer Science & Engineering',
            6, // Semester 6 (2023 batch, currently in 6th sem)
            '2005-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
            'Amritapuri Campus, Kerala',
            $hostelRoom,
            $hostelBlock
        ]);

        $studentId = $pdo->lastInsertId();

        // Insert into users table
        $userStmt = $pdo->prepare("INSERT INTO users (username, password, role, name, email, linked_student_id) VALUES (?, ?, 'student', ?, ?, ?)");
        $userStmt->execute([
            $username,
            $hashedPassword,
            $name,
            $email,
            $studentId
        ]);

        echo "ADDED: $enrollment ($name) - username: $username, password: $password\n";
        $added++;

    } catch (PDOException $e) {
        echo "ERROR: $enrollment ($name) - " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n-----------------------------\n";
echo "SUMMARY:\n";
echo "  Added:   $added\n";
echo "  Updated: $skipped\n";
echo "  Errors:  $errors\n";
echo "  Total:   " . count($students) . "\n";
echo "\n-----------------------------\n";
echo "LOGIN CREDENTIALS:\n";
echo "  Username: Registration Number (e.g., BL.EN.U4CSE23146)\n";
echo "  Password: Last 5 digits (e.g., 23146)\n";
echo "</pre>\n";
?>
