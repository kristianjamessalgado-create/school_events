<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Organizer Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/dashboardorganizer.css">
</head>
<body>

<!-- Header -->
<header class="bg-dark text-white p-3 mb-4 position-relative" style="z-index: 1000;">
    <div class="container d-flex justify-content-between align-items-center">
        <h1 class="h4 mb-0">Welcome, <?= htmlspecialchars($user_name) ?></h1>

        <!-- Logout Button -->
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#logoutModal">
            Logout
        </button>
    </div>
</header>

<!-- Main Content -->
<main class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5">Your Events</h2>
        <a href="create_event.php" class="btn btn-success btn-sm">Create New Event</a>
    </div>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Description</th>
                <th>Date</th>
                <th>Location</th>
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
                    <td><?= htmlspecialchars($event['created_at']) ?></td>
                    <td>
                        <a href="edit_event.php?id=<?= $event['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="delete_event.php?id=<?= $event['id'] ?>" class="btn btn-danger btn-sm"
                           onclick="return confirm('Delete this event?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="text-center">No events found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</main>

<!-- Bootstrap Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        Are you sure you want to logout?
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="<?= BASE_URL ?>/backend/auth/logout.php" class="btn btn-danger">Logout</a>
      </div>

    </div>
  </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
