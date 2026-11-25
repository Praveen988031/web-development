<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
requireRole('admin');
include '../includes/header.php';

$errors  = [];
$success = '';

$doctor_id = intval($_GET['id'] ?? 0);

// Get doctor + linked user info
$stmt = $pdo->prepare("
    SELECT d.id AS doctor_id, d.name AS doctor_name,
           d.dept_id, d.available,
           u.id AS user_id, u.email, u.username,
           dep.name AS dept_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN departments dep ON d.dept_id = dep.id
    WHERE d.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    echo "<div class='alert alert-danger'>Doctor not found.</div>";
    include '../includes/footer.php'; exit;
}

// Fetch all departments for dropdown
$depts = $pdo->query("SELECT * FROM departments ORDER BY name ASC")
             ->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_email    = sanitize($_POST['email']    ?? '');
    $new_name     = sanitize($_POST['name']     ?? '');
    $new_dept     = intval($_POST['dept_id']    ?? 0);
    $new_password = $_POST['password']          ?? '';
    $confirm      = $_POST['confirm']           ?? '';

    // Validation
    if (empty($new_name))
        $errors[] = "Doctor name is required.";

    if (empty($new_email))
        $errors[] = "Email is required.";
    elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Enter a valid email address.";

    if ($new_dept === 0)
        $errors[] = "Please select a department.";

    if (!empty($new_password)) {
        if (strlen($new_password) < 6)
            $errors[] = "Password must be at least 6 characters.";
        if ($new_password !== $confirm)
            $errors[] = "Passwords do not match.";
    }

    // Check email not taken by another user
    if (empty($errors)) {
        $check = $pdo->prepare("
            SELECT id FROM users
            WHERE email = ? AND id != ?
        ");
        $check->execute([$new_email, $doctor['user_id']]);
        if ($check->fetch()) {
            $errors[] = "This email is already used by another account.";
        }
    }

    if (empty($errors)) {
        try {
            // Update users table — email and optionally password
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET email = ?, username = ?, password = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $new_email,
                    $new_name,
                    $hashed,
                    $doctor['user_id']
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET email = ?, username = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $new_email,
                    $new_name,
                    $doctor['user_id']
                ]);
            }

            // Update doctors table — name and department
            $stmt2 = $pdo->prepare("
                UPDATE doctors
                SET name = ?, dept_id = ?
                WHERE id = ?
            ");
            $stmt2->execute([$new_name, $new_dept, $doctor_id]);

            $success = "Doctor details updated successfully!";

            // Refresh doctor data
            $stmt3 = $pdo->prepare("
                SELECT d.id AS doctor_id, d.name AS doctor_name,
                       d.dept_id, d.available,
                       u.id AS user_id, u.email, u.username,
                       dep.name AS dept_name
                FROM doctors d
                JOIN users u ON d.user_id = u.id
                JOIN departments dep ON d.dept_id = dep.id
                WHERE d.id = ?
            ");
            $stmt3->execute([$doctor_id]);
            $doctor = $stmt3->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $errors[] = "Update failed. Please try again.";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="text-primary mb-0">Edit Doctor</h4>
        <small class="text-muted">
            Updating details for
            <strong><?= htmlspecialchars($doctor['doctor_name']) ?></strong>
        </small>
    </div>
    <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm">
        ← Back to Admin
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-md-7">

        <!-- Doctor Info Banner -->
        <div class="card p-3 mb-4 d-flex flex-row align-items-center gap-3"
             style="background:#e8f0fe;border:1px solid #b8d0fb">
            <div class="rounded-circle bg-primary text-white d-flex
                        align-items-center justify-content-center fw-bold"
                 style="width:50px;height:50px;font-size:20px;flex-shrink:0">
                <?= strtoupper(substr($doctor['doctor_name'], 0, 1)) ?>
            </div>
            <div>
                <div class="fw-bold" style="font-size:15px">
                    <?= htmlspecialchars($doctor['doctor_name']) ?>
                </div>
                <div class="text-muted" style="font-size:13px">
                    <?= htmlspecialchars($doctor['dept_name']) ?>
                    &nbsp;·&nbsp;
                    <?= htmlspecialchars($doctor['email']) ?>
                </div>
            </div>
            <div class="ms-auto">
                <span class="badge bg-<?= $doctor['available'] ? 'success' : 'secondary' ?>">
                    <?= $doctor['available'] ? 'Available' : 'Unavailable' ?>
                </span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                    <li><?= $e ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success mb-3">
            ✅ <?= $success ?>
        </div>
        <?php endif; ?>

        <!-- Edit Form -->
        <div class="card p-4">

            <form method="POST" id="editForm" novalidate>

                <!-- Doctor Name -->
                <div class="mb-3">
                    <label class="form-label">Doctor Name</label>
                    <input type="text" name="name" id="name"
                           class="form-control" required
                           placeholder="e.g. Dr. Ramesh Kumar"
                           value="<?= htmlspecialchars($doctor['doctor_name']) ?>">
                    <div class="invalid-feedback">Name is required.</div>
                </div>

                <!-- Department -->
                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <select name="dept_id" class="form-select" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($depts as $d): ?>
                            <option value="<?= $d['id'] ?>"
                                <?= $d['id'] == $doctor['dept_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a department.</div>
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label class="form-label">Login Email</label>
                    <input type="email" name="email" id="email"
                           class="form-control" required
                           placeholder="doctor@hospital.com"
                           value="<?= htmlspecialchars($doctor['email']) ?>">
                    <div class="invalid-feedback">Enter a valid email.</div>
                    <div class="form-text" style="font-size:12px;color:#6c757d">
                        This is the email the doctor uses to login.
                    </div>
                </div>

                <hr class="my-4">

                <div class="mb-1">
                    <span class="fw-bold" style="font-size:14px">Change Password</span>
                    <span class="text-muted ms-2" style="font-size:12px">
                        Leave blank to keep current password
                    </span>
                </div>

                <!-- New Password -->
                <div class="mb-3 mt-3">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password"
                               class="form-control"
                               placeholder="Minimum 6 characters">
                        <button class="btn btn-outline-secondary" type="button"
                                onclick="togglePass('password', this)"
                                style="border-radius:0 10px 10px 0">
                            👁️
                        </button>
                    </div>
                    <div class="invalid-feedback">Minimum 6 characters.</div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm" id="confirm"
                               class="form-control"
                               placeholder="Re-enter new password">
                        <button class="btn btn-outline-secondary" type="button"
                                onclick="togglePass('confirm', this)"
                                style="border-radius:0 10px 10px 0">
                            👁️
                        </button>
                    </div>
                    <div class="invalid-feedback">Passwords must match.</div>
                </div>

                <!-- Buttons -->
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 py-2">
                        💾 Save Changes
                    </button>
                    <a href="admin_dashboard.php"
                       class="btn btn-outline-secondary px-4">
                        Cancel
                    </a>
                </div>

            </form>
        </div>

    </div>
</div>

<script>
// Toggle password visibility
function togglePass(fieldId, btn) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
        btn.textContent = '🙈';
    } else {
        field.type = 'password';
        btn.textContent = '👁️';
    }
}

// Client-side validation
document.getElementById('editForm').addEventListener('submit', function(e) {
    let valid = true;

    const name     = document.getElementById('name');
    const email    = document.getElementById('email');
    const password = document.getElementById('password');
    const confirm  = document.getElementById('confirm');

    [name, email, password, confirm].forEach(f => f.classList.remove('is-invalid'));

    if (name.value.trim().length < 2) {
        name.classList.add('is-invalid'); valid = false;
    }
    if (!email.value.includes('@') || !email.value.includes('.')) {
        email.classList.add('is-invalid'); valid = false;
    }
    if (password.value !== '' && password.value.length < 6) {
        password.classList.add('is-invalid'); valid = false;
    }
    if (password.value !== '' && confirm.value !== password.value) {
        confirm.classList.add('is-invalid'); valid = false;
    }

    if (!valid) e.preventDefault();
});
</script>

<?php include '../includes/footer.php'; ?>