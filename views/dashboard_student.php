<?php
// Fallbacks in case the controller didn't pass data
$user_name = $user_name ?? 'Student';
$user      = $user ?? ['name' => 'Student', 'user_id' => 'N/A'];
$events    = $events ?? []; // always an array
$msg       = $msg ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/school_events/assets/css/dashboard_student.css">

</head>
<body>

<!-- Sidebar -->
<div id="mySidebar" class="sidebar">
    <a href="javascript:void(0)" class="closebtn" onclick="closeSidebar()">&times;</a>

    <div class="user-info">
        <p><strong>Name:</strong> <?= htmlspecialchars($user['name']) ?></p>
        <p><strong>Student ID:</strong> <?= htmlspecialchars($user['user_id']) ?></p>
    </div>

    <a href="#">Profile</a>
    <a href="#">Settings</a>
    <a href="../auth/logout.php">Logout</a>
</div>

<!-- Header -->
<div class="header">
    <span class="hamburger" onclick="openSidebar()">&#9776;</span>
    <h1>Welcome, <?= htmlspecialchars($user_name) ?>!</h1>
</div>

<!-- Main content -->
<div class="container" id="main">
    <?php if ($msg): ?>
        <div class="card"><strong><?= htmlspecialchars($msg) ?></strong></div>
    <?php endif; ?>

    <div class="card">
        <h2>Upcoming Events</h2>
        <?php if (!empty($events)): ?>
            <ul>
                <?php foreach ($events as $event): ?>
                    <li><?= htmlspecialchars($event['title'] ?? 'Untitled') ?> - <?= htmlspecialchars($event['date'] ?? 'TBA') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No upcoming events.</p>
        <?php endif; ?>
    </div>
</div>

<script src="/school_events/assets/js/dashboard_student.js"></script>

</body>
</html>
