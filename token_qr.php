<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT t.id, t.token_no, t.status, t.booked_at,
           u.username, d.name AS doctor_name, dep.name AS dept_name
    FROM tokens t
    JOIN users u ON t.user_id = u.id
    JOIN doctors d ON t.doctor_id = d.id
    JOIN departments dep ON t.dept_id = dep.id
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->execute([$id, $_SESSION['user_id']]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token) die("Token not found.");

// Generate QR data
$qr_data = json_encode([
    'token_id'  => $token['id'],
    'token_no'  => $token['token_no'],
    'patient'   => $token['username'],
    'doctor'    => $token['doctor_name'],
    'dept'      => $token['dept_name'],
    'date'      => date('d M Y', strtotime($token['booked_at'])),
    'status'    => $token['status'],
]);

// Use Google Charts QR API (no library needed!)
$qr_url = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl='
        . urlencode($qr_data) . '&choe=UTF-8';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QR Token — #<?= $token['token_no'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            background: white;
            width: 360px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
        }
        .card-header {
            background: linear-gradient(135deg, #0a6ebd, #084f8a);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .card-header h2 { font-size: 16px; font-weight: 700; }
        .card-header p { font-size: 12px; opacity: 0.8; margin-top: 4px; }
        .qr-section {
            padding: 24px;
            text-align: center;
            border-bottom: 2px dashed #e2e8f0;
        }
        .qr-section img {
            border-radius: 12px;
            border: 3px solid #e8f3fc;
            padding: 8px;
        }
        .qr-section .token-label {
            margin-top: 12px;
            font-size: 11px;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .qr-section .token-num {
            font-size: 40px;
            font-weight: 700;
            color: #0a6ebd;
            line-height: 1;
        }
        .info { padding: 16px 24px; }
        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f4f8;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .key { color: #6c757d; }
        .info-row .val { font-weight: 600; }
        .btn-print {
            display: block;
            margin: 0 24px 24px;
            padding: 12px;
            background: #0a6ebd;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            width: calc(100% - 48px);
        }
        @media print {
            body { background: white; }
            .card { box-shadow: none; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h2>🏥 Hospital OPD — QR Token</h2>
        <p>Show this QR at the hospital counter</p>
    </div>

    <div class="qr-section">
        <img src="<?= $qr_url ?>" width="220" height="220" alt="QR Code">
        <div class="token-label">Token Number</div>
        <div class="token-num">#<?= str_pad($token['token_no'], 3, '0', STR_PAD_LEFT) ?></div>
    </div>

    <div class="info">
        <div class="info-row">
            <span class="key">Patient</span>
            <span class="val"><?= htmlspecialchars($token['username']) ?></span>
        </div>
        <div class="info-row">
            <span class="key">Department</span>
            <span class="val"><?= htmlspecialchars($token['dept_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="key">Doctor</span>
            <span class="val"><?= htmlspecialchars($token['doctor_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="key">Date</span>
            <span class="val"><?= date('d M Y', strtotime($token['booked_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="key">Status</span>
            <span class="val"><?= ucfirst($token['status']) ?></span>
        </div>
    </div>

    <button class="btn-print" onclick="window.print()">🖨️ Print QR Token</button>
</div>
</body>
</html>