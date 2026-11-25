<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';

$errors  = [];
$success = '';
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $email    = sanitize($_POST['email']    ?? '');
    $password = $_POST['password']          ?? '';
    $confirm  = $_POST['confirm']           ?? '';
    $phone    = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
    $old      = compact('username', 'email', 'phone');

    // Server-side validation
    if (empty($username))
        $errors[] = "Username is required.";
    elseif (strlen($username) < 3)
        $errors[] = "Username must be at least 3 characters.";
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username))
        $errors[] = "Username can only contain letters, numbers and underscores.";

    if (empty($email))
        $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Enter a valid email address.";

    if (empty($password))
        $errors[] = "Password is required.";
    elseif (strlen($password) < 6)
        $errors[] = "Password must be at least 6 characters.";

    if ($password !== $confirm)
        $errors[] = "Passwords do not match.";

    if (!empty($phone) && strlen($phone) !== 10)
        $errors[] = "Phone number must be exactly 10 digits.";

    if (empty($errors)) {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $pdo->prepare(
                "INSERT INTO users (username, email, password, phone, role)
                 VALUES (?, ?, ?, ?, 'patient')"
            );
            $stmt->execute([$username, $email, $hashed, $phone]);
            $success = "Registered successfully! <a href='login.php'>Login here</a>";
            $old     = [];

            // SMS — Welcome message
            if (!empty($phone) && file_exists(__DIR__ . '/../vendor/autoload.php')) {
                require_once __DIR__ . '/../config/sms.php';
                $msg = "Welcome to Hospital OPD System, {$username}!\n"
                     . "Your account has been created successfully.\n"
                     . "You can now book OPD tokens online.\n"
                     . "- Hospital OPD System";
                sendSMS($phone, $msg);
            }

        } catch (PDOException $e) {
            $errors[] = "Username or email already exists.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Hospital OPD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f8fb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #fd0d25, #ca0a2a);
            padding: 0 32px;
            height: 64px;
            box-shadow: 0 2px 16px rgba(253, 13, 13, 0.2);
        }
        .navbar-brand {
            font-size: 18px;
            font-weight: 700;
            color: white !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Page layout */
        .page-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        /* Card */
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 40px 36px;
            width: 100%;
            max-width: 480px;
        }
        .register-card h4 {
            font-size: 22px;
            font-weight: 700;
            color: #fd0d15;
            margin-bottom: 6px;
        }
        .register-card .subtitle {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 28px;
        }

        /* Form controls — ALL consistent */
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 6px;
        }
        .form-control {
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            color: #1a1a2e;
            background: #fff;
            box-shadow: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.10);
            outline: none;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: none;
        }

        /* Input group for phone */
        .input-group-text {
            border: 1.5px solid #e2e8f0;
            border-right: none;
            border-radius: 10px 0 0 10px;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
            padding: 11px 14px;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        .input-group .form-control:focus {
            border-color: #fd0d29;
            box-shadow: none;
        }
        .input-group:focus-within .input-group-text {
            border-color: #fd0d21;
        }

        /* Submit button */
        .btn-register {
            background: #fd0d39;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 15px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-register:hover {
            background: #ca0a27;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13,110,253,0.3);
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: 10px;
            font-size: 13px;
            padding: 12px 16px;
        }
        .alert-danger  { background: #fde8e8; color: #842029; }
        .alert-success { background: #d1f2e5; color: #0a3622; }

        .invalid-feedback { font-size: 12px; }

        .divider {
            text-align: center;
            color: #adb5bd;
            font-size: 13px;
            margin: 20px 0;
            position: relative;
        }
        .divider::before, .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 42%;
            height: 1px;
            background: #e2e8f0;
        }
        .divider::before { left: 0; }
        .divider::after  { right: 0; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar d-flex justify-content-between align-items-center">
    <a class="navbar-brand" href="/opd-system/index.php">
        🏥 Hospital OPD System
    </a>
    <div class="d-flex gap-2">
        <a href="/opd-system/pages/login.php"
           style="color:rgba(255,255,255,0.8);font-size:13px;
                  text-decoration:none;padding:8px 16px;
                  border:1.5px solid rgba(255,255,255,0.4);
                  border-radius:10px;font-weight:500">
            Login
        </a>
    </div>
</nav>

<!-- Page -->
<div class="page-wrapper">
    <div class="register-card">

        <h4>Create Account</h4>
        <p class="subtitle">Register as a patient to book OPD tokens online</p>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                    <li><?= $e ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Success -->
        <?php if ($success): ?>
        <div class="alert alert-success mb-3">
            <?= $success ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" id="registerForm" novalidate>

            <!-- Username -->
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" id="username"
                       class="form-control" required minlength="3"
                       placeholder="your name"
                       value="<?= htmlspecialchars($old['username'] ?? '') ?>">
                <div class="invalid-feedback">
                    At least 3 characters, letters/numbers/underscore only.
                </div>
            </div>

            <!-- Phone -->
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <div class="input-group">
                    <span class="input-group-text">+91</span>
                    <input type="text" name="phone" id="phone"
                           class="form-control"
                           placeholder="10-digit mobile number"
                           maxlength="10"
                           pattern="[0-9]{10}"
                           value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
                </div>
                <div class="form-text" style="font-size:12px;color:#6c757d;margin-top:5px">
                    
                </div>
            </div>

            <!-- Email -->
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" id="email"
                       class="form-control" required
                       placeholder="you@example.com"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                <div class="invalid-feedback">Enter a valid email.</div>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="password"
                       class="form-control" required minlength="6"
                       placeholder="Minimum 6 characters">
                <div class="invalid-feedback">Minimum 6 characters.</div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm" id="confirm"
                       class="form-control" required
                       placeholder="Re-enter your password">
                <div class="invalid-feedback">Passwords must match.</div>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-register">
                Create Account
            </button>

            <div class="divider mt-4">or</div>

            <p class="text-center mb-0" style="font-size:13px;color:#6c757d">
                Already have an account?
                <a href="/opd-system/pages/login.php"
                   style="color:#0d6efd;font-weight:600;text-decoration:none">
                    Login here
                </a>
            </p>

        </form>
    </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', function(e) {
    let valid = true;

    const username = document.getElementById('username');
    const email    = document.getElementById('email');
    const password = document.getElementById('password');
    const confirm  = document.getElementById('confirm');
    const phone    = document.getElementById('phone');

    // Reset all
    [username, email, password, confirm, phone].forEach(f => {
        f.classList.remove('is-invalid');
    });

    if (username.value.trim().length < 3 ||
        !/^[a-zA-Z0-9_]+$/.test(username.value)) {
        username.classList.add('is-invalid'); valid = false;
    }
    if (!email.value.includes('@') || !email.value.includes('.')) {
        email.classList.add('is-invalid'); valid = false;
    }
    if (password.value.length < 6) {
        password.classList.add('is-invalid'); valid = false;
    }
    if (confirm.value !== password.value) {
        confirm.classList.add('is-invalid'); valid = false;
    }
    if (phone.value !== '' && !/^[0-9]{10}$/.test(phone.value)) {
        phone.classList.add('is-invalid'); valid = false;
    }

    if (!valid) e.preventDefault();
});
</script>

</body>
</html>