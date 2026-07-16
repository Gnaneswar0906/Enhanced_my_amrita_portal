<?php
require_once '../api/auth.php';
require_once '../api/db.php';

$stmt = $pdo->prepare('SELECT * FROM events ORDER BY event_date ASC');
$stmt->execute();
$events = $stmt->fetchAll();

$today = date('Y-m-d');
$upcoming = array_filter($events, function($e) use ($today) { return $e['event_date'] >= $today; });
$past = array_filter($events, function($e) use ($today) { return $e['event_date'] < $today; });
?>
<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title>My Amrita - Events</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/pages.css">
    <link rel="shortcut icon" href="../images/am.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        <a href="../home.php">Home</a> <span class="sep">/</span> Events
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fa fa-calendar-o"></i> Events Schedule</h1>
            <a href="../home.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <button class="filter-btn active" onclick="filterEv('all', this)">All</button>
            <button class="filter-btn" onclick="filterEv('cultural', this)">Cultural</button>
            <button class="filter-btn" onclick="filterEv('technical', this)">Technical</button>
            <button class="filter-btn" onclick="filterEv('sports', this)">Sports</button>
            <button class="filter-btn" onclick="filterEv('workshop', this)">Workshop</button>
            <button class="filter-btn" onclick="filterEv('seminar', this)">Seminar</button>
        </div>

        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('upcoming')"><i class="fa fa-clock-o"></i> Upcoming (<?php echo count($upcoming); ?>)</button>
            <button class="tab-btn" onclick="switchTab('past')"><i class="fa fa-history"></i> Past Events (<?php echo count($past); ?>)</button>
        </div>

        <!-- Upcoming Events -->
        <div class="tab-content active" id="tab-upcoming">
            <?php if (empty($upcoming)): ?>
                <div class="card">
                    <div class="empty-state"><i class="fa fa-calendar-o"></i><p>No upcoming events.</p></div>
                </div>
            <?php else: ?>
                <div class="events-grid">
                    <?php foreach ($upcoming as $ev):
                        $etype = strtolower($ev['event_type']);
                    ?>
                    <div class="event-card" data-type="<?php echo $etype; ?>">
                        <div class="event-type-bar <?php echo $etype; ?>"></div>
                        <div class="event-body">
                            <div class="event-name"><?php echo htmlspecialchars($ev['event_name']); ?></div>
                            <div class="event-meta">
                                <div class="event-meta-item">
                                    <i class="fa fa-calendar"></i>
                                    <span><?php echo date('d M Y (l)', strtotime($ev['event_date'])); ?></span>
                                </div>
                                <div class="event-meta-item">
                                    <i class="fa fa-clock-o"></i>
                                    <span><?php echo date('h:i A', strtotime($ev['start_time'])); ?> – <?php echo date('h:i A', strtotime($ev['end_time'])); ?></span>
                                </div>
                                <div class="event-meta-item">
                                    <i class="fa fa-map-marker"></i>
                                    <span><?php echo htmlspecialchars($ev['venue']); ?></span>
                                </div>
                            </div>
                            <div class="event-desc"><?php echo htmlspecialchars($ev['description']); ?></div>
                            <div class="event-footer">
                                <span class="event-organizer"><?php echo htmlspecialchars($ev['organizer']); ?></span>
                                <?php if ($ev['registration_link']): ?>
                                    <a href="<?php echo htmlspecialchars($ev['registration_link']); ?>" class="event-register-btn" target="_blank">
                                        <i class="fa fa-external-link"></i> Register
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($ev['google_form_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($ev['google_form_link']); ?>" target="_blank" style="display:inline-block; padding:6px 14px; background:linear-gradient(135deg,#34a853,#0f9d58); color:#fff; border-radius:6px; font-size:12px; font-weight:600; text-decoration:none;">
                                        <i class="fa fa-file-text-o"></i> Google Form
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Past Events -->
        <div class="tab-content" id="tab-past">
            <?php if (empty($past)): ?>
                <div class="card">
                    <div class="empty-state"><i class="fa fa-history"></i><p>No past events.</p></div>
                </div>
            <?php else: ?>
                <div class="events-grid" style="opacity:0.7;">
                    <?php foreach ($past as $ev):
                        $etype = strtolower($ev['event_type']);
                    ?>
                    <div class="event-card" data-type="<?php echo $etype; ?>">
                        <div class="event-type-bar <?php echo $etype; ?>"></div>
                        <div class="event-body">
                            <div class="event-name"><?php echo htmlspecialchars($ev['event_name']); ?></div>
                            <div class="event-meta">
                                <div class="event-meta-item">
                                    <i class="fa fa-calendar"></i>
                                    <span><?php echo date('d M Y', strtotime($ev['event_date'])); ?></span>
                                </div>
                                <div class="event-meta-item">
                                    <i class="fa fa-map-marker"></i>
                                    <span><?php echo htmlspecialchars($ev['venue']); ?></span>
                                </div>
                            </div>
                            <div class="event-desc"><?php echo htmlspecialchars($ev['description']); ?></div>
                            <div class="event-footer">
                                <span class="event-organizer"><?php echo htmlspecialchars($ev['organizer']); ?></span>
                                <span class="badge badge-closed">Completed</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.closest('.tab-btn').classList.add('active');
    }
    function filterEv(type, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.event-card').forEach(card => {
            if (type === 'all' || card.getAttribute('data-type') === type) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
    </script>

</body>
</html>
