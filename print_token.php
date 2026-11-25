<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT t.id, t.token_no, t.status, t.booked_at,
           u.username, u.email,
           d.name AS doctor_name, dep.name AS dept_name
    FROM tokens t
    JOIN users u ON t.user_id = u.id
    JOIN doctors d ON t.doctor_id = d.id
    JOIN departments dep ON t.dept_id = dep.id
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->execute([$id, $_SESSION['user_id']]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    die("Token not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Token Slip — #<?= $token['token_no'] ?></title>
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

        .slip {
            background: white;
            width: 380px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
        }

        .slip-header {
            background: linear-gradient(135deg, #0a6ebd, #084f8a);
            color: white;
            padding: 24px;
            text-align: center;
        }
        .slip-header .hospital-icon { font-size: 32px; margin-bottom: 8px; }
        .slip-header h2 { font-size: 16px; font-weight: 700; letter-spacing: -0.3px; }
        .slip-header p { font-size: 12px; opacity: 0.8; margin-top: 4px; }

        .token-box {
            background: #e8f3fc;
            margin: 20px;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 2px dashed #0a6ebd;
        }
        .token-box .label {
            font-size: 11px;
            font-weight: 600;
            color: #084f8a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .token-box .number {
            font-size: 56px;
            font-weight: 700;
            color: #0a6ebd;
            line-height: 1;
        }

        .details {
            padding: 0 20px 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f4f8;
            font-size: 13px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-row .key {
            color: #6c757d;
            font-weight: 500;
        }
        .detail-row .val {
            font-weight: 600;
            color: #1a1a2e;
            text-align: right;
            max-width: 60%;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: #fef3cd;
            color: #856404;
        }

        .slip-footer {
            background: #f8f9fa;
            padding: 16px 20px;
            text-align: center;
            font-size: 11px;
            color: #6c757d;
            border-top: 1px solid #e2e8f0;
        }
        .slip-footer strong { color: #0a6ebd; }

        .print-btn {
            display: block;
            width: calc(100% - 40px);
            margin: 0 20px 20px;
            padding: 12px;
            background: #0a6ebd;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        .print-btn:hover { background: #084f8a; }

        @media print {
            body { background: white; padding: 0; }
            .slip {
                box-shadow: none;
                border-radius: 0;
                width: 100%;
            }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
<div class="slip">
    <div class="slip-header">
        <div class="hospital-icon">🏥</div>
        <h2>Hospital OPD System</h2>
        <p>Outpatient Department — Token Slip</p>
    </div>

    <div class="token-box">
        <div class="label">Your Token Number</div>
        <div class="number"><?= str_pad($token['token_no'], 3, '0', STR_PAD_LEFT) ?></div>
    </div>

    <div class="details">
        <div class="detail-row">
            <span class="key">Patient Name</span>
            <span class="val"><?= htmlspecialchars($token['username']) ?></span>
        </div>
        <div class="detail-row">
            <span class="key">Department</span>
            <span class="val"><?= htmlspecialchars($token['dept_name']) ?></span>
        </div>
        <div class="detail-row">
            <span class="key">Doctor</span>
            <span class="val"><?= htmlspecialchars($token['doctor_name']) ?></span>
        </div>
        <div class="detail-row">
            <span class="key">Date</span>
            <span class="val"><?= date('d M Y', strtotime($token['booked_at'])) ?></span>
        </div>
        <div class="detail-row">
            <span class="key">Time</span>
            <span class="val"><?= date('h:i A', strtotime($token['booked_at'])) ?></span>
        </div>
        <div class="detail-row">
            <span class="key">Status</span>
            <span class="val">
                <span class="status-badge"><?= ucfirst($token['status']) ?></span>
            </span>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">🖨️ Print Token Slip</button>

    <div class="slip-footer">
        Please arrive 10 minutes before your slot.<br>
        <strong>Helpline: +91 9905879870</strong>
    </div>
</div>
</body>
</html>