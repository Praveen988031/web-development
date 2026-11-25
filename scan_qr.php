<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
requireRole('doctor');
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="text-primary mb-0">QR Token Scanner</h4>
    <a href="doctor_dashboard.php" class="btn btn-outline-primary btn-sm">Back to Dashboard</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card p-4 text-center">
            <h5 class="mb-3">Scan Patient QR Code</h5>
            <div id="reader" style="width:100%;border-radius:12px;overflow:hidden;"></div>
            <div id="scan-result" class="mt-3"></div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const html5QrCode = new Html5Qrcode("reader");

html5QrCode.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: { width: 250, height: 250 } },
    (decodedText) => {
        try {
            const data = JSON.parse(decodedText);
            document.getElementById('scan-result').innerHTML = `
                <div class="alert alert-success text-start">
                    <strong>Token Verified ✅</strong><br>
                    Token: #${String(data.token_no).padStart(3,'0')}<br>
                    Patient: ${data.patient}<br>
                    Department: ${data.dept}<br>
                    Doctor: ${data.doctor}<br>
                    Date: ${data.date}<br>
                    Status: <strong>${data.status}</strong>
                </div>
                <a href="doctor_dashboard.php" class="btn btn-primary w-100">
                    Go to Dashboard
                </a>
            `;
            html5QrCode.stop();
        } catch(e) {
            document.getElementById('scan-result').innerHTML =
                '<div class="alert alert-danger">Invalid QR Code</div>';
        }
    },
    (error) => {}
);
</script>

<?php include '../includes/footer.php'; ?>