<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
requireRole('admin');
include '../includes/header.php';

$patient_id = intval($_GET['patient_id'] ?? 0);

// Get patient info
$stmt = $pdo->prepare("
    SELECT * FROM users WHERE id = ? AND role = 'patient'
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    echo "<div class='alert alert-danger'>Patient not found.</div>";
    include '../includes/footer.php'; exit;
}

// Get all tokens for this patient
$stmt = $pdo->prepare("
    SELECT t.id, t.token_no, t.status, t.booked_at,
           t.payment_status, t.payment_amount,
           d.name AS doctor_name, dep.name AS dept_name
    FROM tokens t
    JOIN doctors d ON t.doctor_id = d.id
    JOIN departments dep ON t.dept_id = dep.id
    WHERE t.user_id = ?
    ORDER BY t.booked_at DESC
");
$stmt->execute([$patient_id]);
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$total    = count($tokens);
$done     = count(array_filter($tokens, fn($t) => $t['status'] === 'done'));
$waiting  = count(array_filter($tokens, fn($t) => $t['status'] === 'waiting'));
$paid     = count(array_filter($tokens, fn($t) => $t['payment_status'] === 'paid'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="text-primary mb-0">Patient Details</h4>
        <small class="text-muted">Viewing tokens for <?= htmlspecialchars($patient['username']) ?></small>
    </div>
    <a href="admin_patients.php" class="btn btn-outline-primary btn-sm">
        ← Back to Patients
    </a>
</div>

<!-- Patient Info Card -->
<div class="card p-4 mb-4">
    <div class="row align-items-center">
        <div class="col-auto">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center
                        justify-content-center fw-bold"
                 style="width:56px;height:56px;font-size:22px">
                <?= strtoupper(substr($patient['username'], 0, 1)) ?>
            </div>
        </div>
        <div class="col">
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($patient['username']) ?></h5>
            <div class="text-muted" style="font-size:13px">
                📧 <?= htmlspecialchars($patient['email']) ?>
                <?php if ($patient['phone']): ?>
                    &nbsp;&nbsp; 📱 +91 <?= htmlspecialchars($patient['phone']) ?>
                <?php endif; ?>
            </div>
            <div class="text-muted" style="font-size:12px;margin-top:4px">
                Joined: <?= date('d M Y', strtotime($patient['created_at'])) ?>
            </div>
        </div>
        <div class="col-auto">
            <form method="POST" action="admin_patients.php">
                <input type="hidden" name="action"     value="delete_patient">
                <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">
                <button class="btn btn-danger btn-sm"
                        onclick="return confirm('Permanently remove this patient?')">
                    🗑️ Remove Patient
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Mini Stats -->
<div class="row g-3 mb-4">
    <?php
    $mini = [
        ['label'=>'Total Visits',  'val'=>$total,   'cls'=>'blue'],
        ['label'=>'Completed',     'val'=>$done,    'cls'=>'green'],
        ['label'=>'Waiting',       'val'=>$waiting, 'cls'=>'amber'],
        ['label'=>'Payments Done', 'val'=>$paid,    'cls'=>'green'],
    ];
    foreach ($mini as $m): ?>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div>
                <div class="stat-val"><?= $m['val'] ?></div>
                <div class="stat-lbl"><?= $m['label'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Token History -->
<div class="section-title">Token History</div>
<div class="card">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-primary">
        <tr>
            <th>Token #</th>
            <th>Department</th>
            <th>Doctor</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($tokens)): ?>
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    No tokens found for this patient.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($tokens as $t):
                $badges = [
                    'waiting'   => 'warning',
                    'called'    => 'info',
                    'done'      => 'success',
                    'cancelled' => 'danger'
                ];
                $b = $badges[$t['status']] ?? 'secondary';
            ?>
            <tr>
                <td><span class="token-num">#<?= str_pad($t['token_no'],3,'0',STR_PAD_LEFT) ?></span></td>
                <td><?= htmlspecialchars($t['dept_name']) ?></td>
                <td><?= htmlspecialchars($t['doctor_name']) ?></td>
                <td>
                    <span class="badge bg-<?= $b ?>">
                        <?= ucfirst($t['status']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($t['payment_status'] === 'paid'): ?>
                        <span class="badge bg-success">
                            Paid ₹<?= $t['payment_amount'] ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Pending</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#6c757d">
                    <?= date('d M Y, h:i A', strtotime($t['booked_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
</div>

<?php include '../includes/footer.php'; ?>