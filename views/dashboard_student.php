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
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<div id="mySidebar" class="sidebar">
    <a href="javascript:void(0)" class="closebtn" onclick="closeSidebar()">&times;</a>

    <div class="user-info">
        <p><strong>Name:</strong> <?= htmlspecialchars($user['name']) ?></p>
        <p><strong>Student ID:</strong> <?= htmlspecialchars($user['user_id']) ?></p>
    </div>

    <a href="javascript:void(0)" onclick="openProfileModal()">Profile</a>
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
        <h2>My Events Calendar</h2>
        <p style="margin-bottom:10px; font-size:14px; color:#555;">
            You only see events that are open to your department.
        </p>
        <div id="student-calendar"></div>
    </div>

    <div class="card">
        <h2>Upcoming Events</h2>
        <?php if (!empty($events)): ?>
            <ul>
                <?php foreach ($events as $event): ?>
                    <li>
                        <strong><?= htmlspecialchars($event['title'] ?? 'Untitled') ?></strong>
                        - <?= htmlspecialchars($event['date'] ?? 'TBA') ?>
                        <?php if (!empty($event['location'])): ?>
                            (<?= htmlspecialchars($event['location']) ?>)
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No upcoming events for your department.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Pass events to JS for calendar -->
<script>
window.studentEvents = <?= json_encode(array_map(function($e) {
    return [
        'title' => $e['title'] ?? 'Untitled',
        'start' => $e['date'] ?? null,
        'extendedProps' => [
            'location'    => $e['location'] ?? '',
            'description' => $e['description'] ?? '',
        ],
    ];
}, $events), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
</script>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="/school_events/assets/js/dashboard_student.js"></script>

<!-- Profile Modal (simple custom modal) -->
<div id="profileModal" class="profile-modal">
    <div class="profile-modal-content">
        <span class="profile-close" onclick="closeProfileModal()">&times;</span>
        <h2>My Information</h2>
        <form action="<?= BASE_URL ?>/backend/auth/update_student_profile.php" method="POST">
            <div class="form-group">
                <label for="fullNameModal">Full Name</label>
                <input
                    type="text"
                    id="fullNameModal"
                    name="name"
                    value="<?= htmlspecialchars($user['name'] ?? $user_name) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Student ID</label>
                <input
                    type="text"
                    value="<?= htmlspecialchars($user['user_id'] ?? 'N/A') ?>"
                    readonly
                >
            </div>

            <div class="form-group">
                <label for="departmentModal">Department</label>
                <?php $currentDept = $user['department'] ?? ''; ?>
                <select id="departmentModal" name="department" required>
                    <option value="">Select Department</option>
                    <option value="BSIT"        <?= $currentDept === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                    <option value="BSHM"        <?= $currentDept === 'BSHM' ? 'selected' : '' ?>>BSHM</option>
                    <option value="CONAHS"      <?= $currentDept === 'CONAHS' ? 'selected' : '' ?>>CONAHS</option>
                    <option value="Senior High" <?= $currentDept === 'Senior High' ? 'selected' : '' ?>>Senior High</option>
                </select>
            </div>

            <button type="submit" class="btn">Save Info</button>
            <p style="font-size: 12px; color:#555; margin-top:5px;">
                Your department is used to filter which events you can see.
            </p>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('student-calendar');
    if (!calendarEl) return;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: window.studentEvents || [],
        eventDisplay: 'block',
        eventColor: '#28a745',
        eventTextColor: '#fff',
        eventBorderColor: 'transparent',
        height: 'auto',
        dayHeaderFormat: { weekday: 'short' },
        firstDay: 0,
        weekends: true,
        weekNumbers: false,
        nowIndicator: true,
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            omitZeroMinute: false,
            meridiem: 'short'
        }
    });

    calendar.render();
});

function openProfileModal() {
    var modal = document.getElementById('profileModal');
    if (modal) modal.style.display = 'flex';
}

function closeProfileModal() {
    var modal = document.getElementById('profileModal');
    if (modal) modal.style.display = 'none';
}
</script>

</body>
</html>
