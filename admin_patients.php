<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
requireRole('admin');
include '../includes/header.php';

$success = '';
$error   = '';

// Handle delete patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'delete_patient') {
        $patient_id = intval($_POST['patient_id']);

        try {
            // First cancel all their active tokens
            $stmt = $pdo->prepare("
                UPDATE tokens SET status = 'cancelled'
                WHERE user_id = ? AND status IN ('waiting','called')
            ");
            $stmt->execute([$patient_id]);

            // Delete all their tokens
            $stmt = $pdo->prepare("DELETE FROM tokens WHERE user_id = ?");
            $stmt->execute([$patient_id]);

            // Delete the patient user
            $stmt = $pdo->prepare("
                DELETE FROM users WHERE id = ? AND role = 'patient'
            ");
            $stmt->execute([$patient_id]);

            $success = "Patient removed successfully.";
        } catch (PDOException $e) {
            $error = "Could not remove patient. Try again.";
        }
    }

    if ($_POST['action'] === 'cancel_tokens') {
        $patient_id = intval($_POST['patient_id']);
        $stmt = $pdo->prepare("
            UPDATE tokens SET status = 'cancelled'
            WHERE user_id = ? AND status IN ('waiting','called')
        ");
        $stmt->execute([$patient_id]);
        $success = "All active tokens cancelled for this patient.";
    }
}

// Search
$search = trim($_GET['search'] ?? '');

// Pagination
$per_page = 10;
$page     = max(1, intval($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where  = "WHERE u.role = 'patient'";
$params = [];

if ($search !== '') {
    $where   .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like     = "%$search%";
    $params   = [$like, $like, $like];
}

// Total count
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM users u $where
");
$count_stmt->execute($params);
$total       = $count_stmt->fetchColumn();
$total_pages = ceil($total / $per_page);

// Fetch patients with token stats
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.phone, u.created_at,
           COUNT(t.id)                    AS total_tokens,
           SUM(t.status = 'waiting')      AS waiting,
           SUM(t.status = 'done')         AS done,
           SUM(t.status = 'cancelled')    AS cancelled,
           MAX(t.booked_at)               AS last_visit
    FROM users u
    LEFT JOIN tokens t ON t.user_id = u.id
    $where
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="text-primary mb-0">Patient Management</h4>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-primary"><?= $total ?> Total Patients</span>
        <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm">Admin Panel</a>
        <a href="admin_reports.php"   class="btn btn-outline-secondary btn-sm">Reports</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- Search -->
<form method="GET" class="mb-4">
    <div class="input-group">
        <input type="text" name="search" class="form-control"
               placeholder="Search by name, email or phone..."
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-primary" type="submit">Search</button>
        <?php if ($search): ?>
            <a href="admin_patients.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Patients Table -->
<div class="card">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-primary">
        <tr>
            <th>#</th>
            <th>Patient Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Total Visits</th>
            <th>Waiting</th>
            <th>Done</th>
            <th>Last Visit</th>
            <th>Joined</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($patients)): ?>
            <tr>
                <td colspan="10" class="text-center text-muted py-5">
                    No patients found.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($patients as $i => $p): ?>
            <tr>
                <td class="text-muted small"><?= $offset + $i + 1 ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center
                                    justify-content-center fw-bold"
                             style="width:34px;height:34px;font-size:13px;flex-shrink:0">
                            <?= strtoupper(substr($p['username'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:13px">
                                <?= htmlspecialchars($p['username']) ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td style="font-size:13px"><?= htmlspecialchars($p['email']) ?></td>
                <td style="font-size:13px">
                    <?= $p['phone'] ? '+91 ' . htmlspecialchars($p['phone']) : '<span class="text-muted">—</span>' ?>
                </td>
                <td class="text-center">
                    <span class="badge bg-info"><?= $p['total_tokens'] ?></span>
                </td>
                <td class="text-center">
                    <?php if ($p['waiting'] > 0): ?>
                        <span class="badge bg-warning"><?= $p['waiting'] ?></span>
                    <?php else: ?>
                        <span class="text-muted">0</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="badge bg-success"><?= $p['done'] ?? 0 ?></span>
                </td>
                <td style="font-size:12px;color:#6c757d">
                    <?= $p['last_visit']
                        ? date('d M Y', strtotime($p['last_visit']))
                        : '—' ?>
                </td>
                <td style="font-size:12px;color:#6c757d">
                    <?= date('d M Y', strtotime($p['created_at'])) ?>
                </td>
                <td>
                    <div class="d-flex gap-1">

                        <!-- View Tokens -->
                        <a href="admin_patient_tokens.php?patient_id=<?= $p['id'] ?>"
                           class="btn btn-sm btn-outline-primary"
                           title="View Tokens">
                           👁️
                        </a>

                        <!-- Cancel Active Tokens -->
                        <?php if ($p['waiting'] > 0): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action"     value="cancel_tokens">
                            <input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-warning"
                                    title="Cancel Active Tokens"
                                    onclick="return confirm('Cancel all active tokens for <?= htmlspecialchars($p['username']) ?>?')">
                                🚫
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- Remove Patient -->
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action"     value="delete_patient">
                            <input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-danger"
                                    title="Remove Patient"
                                    onclick="return confirm('Permanently remove <?= htmlspecialchars($p['username']) ?> from the system? This cannot be undone!')">
                                🗑️
                            </button>
                        </form>

                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">
               Previous
            </a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link"
                   href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                   <?= $i ?>
                </a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link"
               href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">
               Next
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>