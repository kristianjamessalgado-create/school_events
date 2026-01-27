<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Organizer Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboardorganizer.css">

    <style>
        /* Google Calendar styling is in dashboardorganizer.css */
    </style>
</head>
<body>

<header class="bg-dark text-white p-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <h1 class="h4 mb-0">Welcome, <?= htmlspecialchars($user_name) ?></h1>
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
    </div>
</header>

<main class="container">

    <div class="row">
        <!-- Main content: calendar + events table -->
        <div class="col-lg-8 mb-4">
            <!-- Calendar -->
            <div class="mb-4 calendar-container">
                <div id="calendar"></div>
            </div>

            <!-- Events Table -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Your Events</h2>
                <a href="<?= BASE_URL ?>/backend/auth/createevent.php" class="btn btn-success btn-sm">Create New Event</a>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Department</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($events)): ?>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?= htmlspecialchars($event['id']) ?></td>
                                    <td><?= htmlspecialchars($event['title']) ?></td>
                                    <td><?= htmlspecialchars($event['description']) ?></td>
                                    <td><?= htmlspecialchars($event['date']) ?></td>
                                    <td><?= htmlspecialchars($event['location']) ?></td>
                                    <td><?= htmlspecialchars($event['department'] ?? 'ALL') ?></td>
                                    <td><?= htmlspecialchars($event['created_at']) ?></td>
                                    <td>
                                        <a href="edit_event.php?id=<?= $event['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="delete_event.php?id=<?= $event['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this event?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No events found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar: personal info -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Personal Information</h5>
                </div>
                <div class="card-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text"
                                   class="form-control"
                                   value="<?= htmlspecialchars($user_name) ?>"
                                   placeholder="Enter your full name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select">
                                <option value="">Select department</option>
                                <option value="BSIT">BSIT</option>
                                <option value="BSHM">BSHM</option>
                                <option value="CONAHS">CONAHS</option>
                                <option value="Senior High">Senior High</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text"
                                   class="form-control"
                                   placeholder="e.g. 09XXXXXXXXX">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Additional Info</label>
                            <textarea class="form-control"
                                      rows="3"
                                      placeholder="Any other personal info you want to note here"></textarea>
                        </div>

                        <!-- For now this is just UI; no backend save yet -->
                        <button type="button" class="btn btn-outline-primary w-100" disabled>
                            Save (to be implemented)
                        </button>
                        <small class="text-muted d-block mt-2">
                            This panel is for your personal details. Saving to the database can be added later.
                        </small>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Are you sure you want to logout?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="<?= BASE_URL ?>/backend/auth/logout.php" class="btn btn-danger">Logout</a>
      </div>
    </div>
  </div>
</div>

<!-- Pass PHP events to JS -->
<script>
window.eventsData = <?= json_encode(array_map(function($e) use ($user_name) {
    return [
        'id'    => $e['id'],
        'title' => $e['title'],
        'start' => $e['date'],
        // FullCalendar will put this under event.extendedProps
        'extendedProps' => [
            'description' => $e['description'],
            'location'    => $e['location'],
            'created_at'  => $e['created_at'],
            'status'      => $e['status'],
            'editUrl'     => 'edit_event.php?id=' . $e['id'],
            'organizer'   => $user_name,
            'department'  => $e['department'] ?? 'ALL',
        ],
    ];
}, $events), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
</script>

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-labelledby="eventDetailsLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="eventDetailsLabel">Event Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h5 id="eventTitle" class="mb-2"></h5>
        <p class="mb-1"><strong>Date:</strong> <span id="eventDate"></span></p>
        <p class="mb-1"><strong>Location:</strong> <span id="eventLocation"></span></p>
        <p class="mb-1"><strong>Status:</strong> <span id="eventStatus" class="badge bg-success"></span></p>
        <p class="mb-1"><strong>Target Department:</strong> <span id="eventDepartment"></span></p>
        <p class="mb-1"><strong>Created by:</strong> <span id="eventOrganizer"></span></p>
        <p class="mt-3 mb-1"><strong>Description:</strong></p>
        <p id="eventDescription" class="mb-2 text-muted"></p>
        <p class="mb-0"><small><strong>Created at:</strong> <span id="eventCreatedAt"></span></small></p>
      </div>
      <div class="modal-footer">
        <a href="#" id="eventEditLink" class="btn btn-primary">Edit Event</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- FullCalendar JS (global bundle) -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Initialize FullCalendar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        selectMirror: true,
        dayMaxEvents: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: window.eventsData || [],
        eventDisplay: 'block',
        eventColor: '#1a73e8',
        eventTextColor: '#fff',
        eventBorderColor: 'transparent',
        height: 'auto',

        // Click empty date -> create event for selected date
        dateClick: function(info) {
            window.location.href = "<?= BASE_URL ?>/backend/auth/createevent.php?date=" + info.dateStr;
        },

        // Click existing event -> show details modal
        eventClick: function(info) {
            var event = info.event;
            var props = event.extendedProps || {};

            // Fill modal content
            document.getElementById('eventTitle').textContent = event.title || 'Untitled event';

            // Format date nicely
            var dateStr = '';
            if (event.start) {
                var opts = { year: 'numeric', month: 'short', day: 'numeric' };
                dateStr = event.start.toLocaleDateString(undefined, opts);
            }
            document.getElementById('eventDate').textContent = dateStr || (event.startStr || '');

            document.getElementById('eventLocation').textContent = props.location || 'N/A';
            document.getElementById('eventDescription').textContent = props.description || 'No description provided.';

            // Department / target audience
            var dept = (props.department || 'ALL');
            document.getElementById('eventDepartment').textContent = (dept === 'ALL')
                ? 'All Departments'
                : dept;

            // Organizer
            document.getElementById('eventOrganizer').textContent = props.organizer || 'N/A';

            // Status badge
            var statusEl = document.getElementById('eventStatus');
            var status = (props.status || 'active').toLowerCase();
            statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            statusEl.className = 'badge ' + (status === 'active' ? 'bg-success' : 'bg-secondary');

            // Created at
            document.getElementById('eventCreatedAt').textContent = props.created_at || 'N/A';

            // Edit link
            var editLink = document.getElementById('eventEditLink');
            if (props.editUrl) {
                editLink.href = props.editUrl;
                editLink.style.display = 'inline-block';
            } else {
                editLink.style.display = 'none';
            }

            // Show Bootstrap modal
            var modalEl = document.getElementById('eventDetailsModal');
            var eventModal = new bootstrap.Modal(modalEl);
            eventModal.show();

            // Prevent default navigation
            info.jsEvent.preventDefault();
        },

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
</script>

</body>
</html>
