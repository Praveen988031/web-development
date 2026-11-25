<?php
session_start();
require '../config/db.php';
require '../includes/auth.php';
require '../config/payment.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$payment_id = $input['razorpay_payment_id'] ?? '';
$token_id   = intval($input['token_id'] ?? 0);

if (empty($payment_id) || $token_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    // Verify with Razorpay API
    $url  = "https://api.razorpay.com/v1/payments/" . $payment_id;
    $ch   = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($response['status']) && $response['status'] === 'captured') {
        // Save payment to DB
        $stmt = $pdo->prepare("
            UPDATE tokens
            SET payment_status = 'paid',
                payment_id = ?,
                payment_amount = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $payment_id,
            $response['amount'] / 100,
            $token_id,
            $_SESSION['user_id']
        ]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment not captured']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 