<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
require '../config/payment.php';
requireRole('patient');
include '../includes/header.php';

$token_id = intval($_GET['token_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT t.id, t.token_no, t.payment_status,
           u.username, u.email,
           d.name AS doctor_name, dep.name AS dept_name
    FROM tokens t
    JOIN users u ON t.user_id = u.id
    JOIN doctors d ON t.doctor_id = d.id
    JOIN departments dep ON t.dept_id = dep.id
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->execute([$token_id, $_SESSION['user_id']]);
$token = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$token) {
    echo "<div class='alert alert-danger'>Token not found.</div>";
    include '../includes/footer.php'; exit;
}

if ($token['payment_status'] === 'paid') {
    echo "<div class='alert alert-success'>✅ Payment already completed for Token #{$token['token_no']}.</div>";
    include '../includes/footer.php'; exit;
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card p-4">
            <h4 class="text-primary mb-4">Pay Consultation Fee</h4>

            <div class="card p-3 mb-4" style="background:#f8f9fa">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Token Number</span>
                    <strong class="token-num">#<?= str_pad($token['token_no'],3,'0',STR_PAD_LEFT) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Department</span>
                    <strong><?= htmlspecialchars($token['dept_name']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Doctor</span>
                    <strong><?= htmlspecialchars($token['doctor_name']) ?></strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Consultation Fee</span>
                    <strong class="text-primary fs-5">₹<?= CONSULTATION_FEE ?></strong>
                </div>
            </div>

            <div id="payment-status"></div>

            <button id="pay-btn" class="btn btn-primary w-100 py-3">
                Pay ₹<?= CONSULTATION_FEE ?> via Razorpay
            </button>

            <p class="text-muted text-center mt-3" style="font-size:12px">
                🔒 Secured by Razorpay. Test mode enabled.
            </p>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('pay-btn').addEventListener('click', function () {
    const options = {
        key: '<?= RAZORPAY_KEY_ID ?>',
        amount: <?= CONSULTATION_FEE * 100 ?>,
        currency: 'INR',
        name: 'Hospital OPD System',
        description: 'Consultation Fee — Token #<?= str_pad($token['token_no'],3,'0',STR_PAD_LEFT) ?>',
        image: '',
        handler: function (response) {
            // Send payment ID to backend to verify and save
            fetch('verify_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    razorpay_payment_id: response.razorpay_payment_id,
                    token_id: <?= $token['id'] ?>
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('payment-status').innerHTML = `
                        <div class="alert alert-success">
                            ✅ Payment successful!<br>
                            Payment ID: <strong>${response.razorpay_payment_id}</strong>
                        </div>`;
                    document.getElementById('pay-btn').disabled = true;
                    document.getElementById('pay-btn').textContent = 'Payment Done ✅';
                } else {
                    document.getElementById('payment-status').innerHTML =
                        '<div class="alert alert-danger">Payment verification failed.</div>';
                }
            });
        },
        prefill: {
            name: '<?= htmlspecialchars($token['username']) ?>',
            email: '<?= htmlspecialchars($token['email']) ?>'
        },
        theme: { color: '#0a6ebd' },
        modal: {
            ondismiss: function () {
                document.getElementById('payment-status').innerHTML =
                    '<div class="alert alert-warning">Payment cancelled.</div>';
            }
        }
    };

    const rzp = new Razorpay(options);
    rzp.open();
});
</script>

<?php include '../includes/footer.php'; ?>