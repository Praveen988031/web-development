<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: /opd-system/pages/login.php');
    exit;
}

require_once '../config/db.php';

$patient_id   = $_SESSION['user_id'];
$message      = '';
$token_result = null;

// Fetch departments
$deptStmt    = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doctor_id'])) {
    $doctor_id = intval($_POST['doctor_id']);

    // Get doctor + department info
    $stmt = $pdo->prepare("
        SELECT d.id AS doctor_id, d.name AS doctor_name,
               dept.name AS dept_name, dept.id AS dept_id
        FROM doctors d
        JOIN departments dept ON d.dept_id = dept.id
        WHERE d.id = ?
    ");
    $stmt->execute([$doctor_id]);
    $doctorInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctorInfo) {
        $message = "Selected doctor not found.";
    } else {
        // Get next token number for this doctor today
        $stmt2 = $pdo->prepare("
            SELECT COALESCE(MAX(token_no), 0) + 1 AS next_token
            FROM tokens
            WHERE doctor_id = ? AND DATE(booked_at) = CURDATE()
        ");
        $stmt2->execute([$doctor_id]);
        $next_token = $stmt2->fetch(PDO::FETCH_ASSOC)['next_token'];

        // Insert token using correct column names
        $stmt3 = $pdo->prepare("
            INSERT INTO tokens (user_id, doctor_id, dept_id, token_no, status, booked_at)
            VALUES (?, ?, ?, ?, 'waiting', NOW())
        ");
        $inserted = $stmt3->execute([
            $patient_id,
            $doctor_id,
            $doctorInfo['dept_id'],
            $next_token
        ]);

        if ($inserted) {
            $token_result = [
                'token_no'    => $next_token,
                'doctor_id'   => $doctorInfo['doctor_id'],
                'doctor_name' => $doctorInfo['doctor_name'],
                'dept_name'   => $doctorInfo['dept_name'],
                'date'        => date('d M Y'),
            ];

            // SMS notification if patient has phone
            if (file_exists('../vendor/autoload.php') && file_exists('../config/sms.php')) {
                require_once '../config/sms.php';
                $phone_stmt = $pdo->prepare("SELECT phone, username FROM users WHERE id = ?");
                $phone_stmt->execute([$patient_id]);
                $patient_info = $phone_stmt->fetch(PDO::FETCH_ASSOC);

                if ($patient_info && $patient_info['phone']) {
                    $msg = "Hello {$patient_info['username']}! Your OPD token #{$next_token} is confirmed.\n"
                         . "Doctor: {$doctorInfo['doctor_name']}\n"
                         . "Dept: {$doctorInfo['dept_name']}\n"
                         . "Date: " . date('d M Y') . "\n"
                         . "Hospital OPD System";
                    sendSMS($patient_info['phone'], $msg);
                }
            }
        } else {
            $message = "Something went wrong while booking. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Token - Hospital OPD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/opd-system/assets/css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #c93c45; }
        .navbar { background: linear-gradient(90deg, #fd0d19, #ca0a0a); }
        .step-badge {
            width: 34px; height: 34px; border-radius: 50%;
            background: #fd0d1d; color: #fff; font-weight: 600;
            display: inline-flex; align-items: center; justify-content: center;
            margin-right: 10px;
        }
        .dept-card, .doctor-card {
            border: 2px solid transparent;
            border-radius: 16px;
            background: #fff;
            padding: 20px;
            cursor: pointer;
            transition: 0.15s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: 100%;
        }
        .dept-card:hover, .doctor-card:hover {
            box-shadow: 0 6px 18px rgba(13,110,253,0.15);
            transform: translateY(-2px);
        }
        .dept-card.selected, .doctor-card.selected {
            border-color: #fd0d19;
            background: #eaf2ff;
        }
        .dept-icon {
            width: 54px; height: 54px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 12px;
            background: #e8f6f3; color: #fd0d0d;
        }
        .doctor-avatar {
            width: 60px; height: 60px; border-radius: 50%;
            background: #dbeafe; color: #fd0d75;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 20px; flex-shrink: 0;
        }
        .section-title {
            font-weight: 700; font-size: 1.15rem; margin-bottom: 16px;
            display: flex; align-items: center;
        }
        .booking-card {
            background: #fff; border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            padding: 30px;
        }
        .ticket {
            background: linear-gradient(135deg, #fd0d45, #ca0a24);
            color: #fff; border-radius: 20px; padding: 30px;
            position: relative; overflow: hidden;
        }
        .ticket::after {
            content: "";
            position: absolute; right: -40px; top: -40px;
            width: 160px; height: 160px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .ticket .token-number {
            font-size: 3rem; font-weight: 800; letter-spacing: 1px;
        }
        .ticket-divider {
            border-top: 2px dashed rgba(255,255,255,0.4);
            margin: 18px 0;
        }
        .hidden { display: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark px-4 d-flex justify-content-between">
    <a class="navbar-brand fw-bold text-white text-decoration-none" href="/opd-system/index.php">
        <i class="bi bi-hospital"></i> Hospital OPD System
    </a>
    <div class="d-flex align-items-center gap-2">
        <span class="text-white me-2" style="font-size:13px;opacity:0.85">
            Hi, <?= htmlspecialchars($_SESSION['username']) ?>
        </span>
        <a href="/opd-system/pages/book_token.php"  class="btn btn-light btn-sm">Book Token</a>
        <a href="/opd-system/pages/my_tokens.php"   class="btn btn-light btn-sm">My Tokens</a>
        <a href="/opd-system/pages/view_queue.php"  class="btn btn-outline-light btn-sm">Live Queue</a>
        <a href="/opd-system/pages/logout.php"      class="btn btn-outline-light btn-sm">Logout</a>
    </div>
</nav>

<div class="container mt-4 mb-5">

<?php if ($token_result): ?>

    <!-- TOKEN CONFIRMATION TICKET -->
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="ticket text-center">
                <i class="bi bi-check-circle-fill" style="font-size: 2rem;"></i>
                <h4 class="mt-2 mb-0">Token Booked Successfully!</h4>
                <div class="token-number mt-3">
                    #<?= str_pad($token_result['token_no'], 3, '0', STR_PAD_LEFT) ?>
                </div>
                <div class="ticket-divider"></div>
                <div class="row text-start">
                    <div class="col-6">
                        <small style="opacity:0.8">Doctor</small>
                        <div class="fw-semibold">
                            <?= htmlspecialchars($token_result['doctor_name']) ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <small style="opacity:0.8">Department</small>
                        <div class="fw-semibold">
                            <?= htmlspecialchars($token_result['dept_name']) ?>
                        </div>
                    </div>
                    <div class="col-6 mt-3">
                        <small style="opacity:0.8">Date</small>
                        <div class="fw-semibold"><?= $token_result['date'] ?></div>
                    </div>
                    <div class="col-6 mt-3">
                        <small style="opacity:0.8">Status</small>
                        <div class="fw-semibold">⏳ Waiting</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 justify-content-center flex-wrap">
                <a href="/opd-system/pages/my_tokens.php"
                   class="btn btn-primary">View My Tokens</a>
                <a href="/opd-system/pages/view_queue.php"
                   class="btn btn-outline-primary">Track Live Queue</a>
                <a href="/opd-system/pages/book_token.php"
                   class="btn btn-outline-secondary">Book Another</a>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- BOOKING FLOW -->
    <h3 class="fw-bold mb-1">Book an Appointment</h3>
    <p class="text-muted mb-4">
        Choose a department, pick a doctor, and confirm your slot — it only takes a minute.
    </p>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="booking-card">
        <form method="POST" id="bookingForm">
            <input type="hidden" name="doctor_id" id="selectedDoctorId">

            <!-- Step 1: Department -->
            <div class="section-title">
                <span class="step-badge">1</span> Select Department
            </div>
            <div class="row g-3 mb-4" id="deptRow">
                <?php foreach ($departments as $dept): ?>
                <div class="col-6 col-md-3">
                    <div class="dept-card"
                         data-dept-id="<?= $dept['id'] ?>"
                         onclick="selectDept(<?= $dept['id'] ?>, this)">
                        <div class="dept-icon">
                            <i class="bi bi-heart-pulse"></i>
                        </div>
                        <div class="fw-semibold">
                            <?= htmlspecialchars($dept['name']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Step 2: Doctor -->
            <div class="section-title hidden" id="doctorSection">
                <span class="step-badge">2</span> Choose a Doctor
            </div>
            <div class="row g-3 mb-4" id="doctorRow"></div>

            <!-- Step 3: Confirm -->
            <button type="submit"
                    class="btn btn-primary btn-lg px-4 hidden"
                    id="confirmBtn" disabled>
                <i class="bi bi-ticket-perforated"></i> Confirm Booking
            </button>
        </form>
    </div>

<?php endif; ?>
</div>

<script>
function selectDept(deptId, el) {
    document.querySelectorAll('.dept-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');

    // Reset doctor section
    document.getElementById('doctorRow').innerHTML =
        '<div class="col-12 text-muted"><i class="bi bi-hourglass-split"></i> Loading doctors...</div>';
    document.getElementById('doctorSection').classList.remove('hidden');
    document.getElementById('confirmBtn').classList.add('hidden');
    document.getElementById('confirmBtn').disabled = true;
    document.getElementById('selectedDoctorId').value = '';

    fetch('/opd-system/pages/ajax_get_doctors.php?department_id=' + deptId)
        .then(res => res.text())
        .then(rawText => {
            let doctors;
            try {
                doctors = JSON.parse(rawText);
            } catch (e) {
                document.getElementById('doctorRow').innerHTML =
                    '<div class="col-12 text-danger">Error loading doctors. Please try again.</div>';
                console.error('Raw response:', rawText);
                return;
            }

            const row = document.getElementById('doctorRow');
            if (!doctors.length) {
                row.innerHTML =
                    '<div class="col-12 text-muted">No doctors available in this department.</div>';
                return;
            }

            row.innerHTML = doctors.map(doc => `
                <div class="col-md-4">
                    <div class="doctor-card d-flex align-items-center gap-3"
                         data-doctor-id="${doc.id}"
                         onclick="selectDoctor(${doc.id}, this)">
                        <div class="doctor-avatar">
                            ${doc.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div class="fw-semibold"> ${doc.name}</div>
                        </div>
                    </div>
                </div>
            `).join('');
        })
        .catch(err => {
            document.getElementById('doctorRow').innerHTML =
                '<div class="col-12 text-danger">Failed to load doctors: ' + err.message + '</div>';
        });
}

function selectDoctor(doctorId, el) {
    document.querySelectorAll('.doctor-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedDoctorId').value = doctorId;
    document.getElementById('confirmBtn').classList.remove('hidden');
    document.getElementById('confirmBtn').disabled = false;
}
</script>

</body>
</html>