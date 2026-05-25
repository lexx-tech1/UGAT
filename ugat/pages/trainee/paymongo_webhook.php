<?php
// paymongo_webhook.php
// PayMongo calls this URL when a GCash payment source becomes chargeable.
// Register this URL in: PayMongo Dashboard → Developers → Webhooks
// Event to listen to: source.chargeable

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../config/paymongo.php';

$rawBody         = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_PAYMONGO_SIGNATURE'] ?? '';

// Always respond 200 quickly so PayMongo doesn't retry for signature errors
if (!paymongo_verify_webhook($rawBody, $signatureHeader)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($rawBody, true);
$type  = $event['data']['attributes']['type'] ?? '';

if ($type !== 'source.chargeable') {
    // Not the event we care about — acknowledge and exit
    echo json_encode(['received' => true]);
    exit;
}

$source    = $event['data']['attributes']['data'] ?? [];
$source_id = $source['id'] ?? '';

if (!$source_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing source ID']);
    exit;
}

// Find the pending_payment order with this source ID
$stmt = $conn->prepare("SELECT id, user_id, total FROM orders WHERE paymongo_source_id = ? AND status = 'pending_payment' LIMIT 1");
$stmt->bind_param('s', $source_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    // Already processed or unknown — still return 200 so PayMongo doesn't retry
    echo json_encode(['received' => true]);
    exit;
}

$order_id       = (int) $order['id'];
$amountCentavos = (int) round((float) $order['total'] * 100);

// ── Create a PayMongo Payment using the chargeable source ─────
$result = paymongo_request('POST', '/payments', [
    'data' => [
        'attributes' => [
            'amount'      => $amountCentavos,
            'source'      => ['id' => $source_id, 'type' => 'source'],
            'currency'    => 'PHP',
            'description' => 'UGAT Order #' . str_pad($order_id, 6, '0', STR_PAD_LEFT),
        ],
    ],
]);

if ($result['code'] === 201) {
    // Payment created successfully — mark order as pending (awaiting admin confirmation)
    $conn->begin_transaction();
    try {
        $conn->query("UPDATE orders SET status = 'pending', gcash_ref = '{$source_id}' WHERE id = {$order_id}");
        $conn->query("
            INSERT INTO order_status_logs (order_id, status, notes)
            VALUES ({$order_id}, 'pending', 'GCash payment confirmed via PayMongo')
        ");
        $conn->commit();

        // Notify trainee that payment was received
        try {
            require_once '../../config/sms_helpers.php';
            require_once '../../config/email_service.php';
            require_once '../../config/email.php';

            $order_no = str_pad($order_id, 6, '0', STR_PAD_LEFT);
            $amount   = '₱' . number_format((float)$order['total'], 2);

            sendSmsForEvent('payment_received', (int)$order['user_id'], [
                'order_id' => $order_no,
                'amount'   => $amount,
            ]);

            $uq = $conn->query(
                "SELECT COALESCE(NULLIF(np.email,''), u.email) AS email,
                        CONCAT(COALESCE(tp.first_name,''), ' ', COALESCE(tp.last_name,'')) AS name
                 FROM users u
                 LEFT JOIN trainee_profiles tp ON tp.user_id = u.id
                 LEFT JOIN notification_preferences np ON np.user_id = u.id
                 WHERE u.id = {$order['user_id']} LIMIT 1"
            );
            $uinfo = $uq ? $uq->fetch_assoc() : null;
            if ($uinfo && $uinfo['email']) {
                $name     = trim($uinfo['name']) ?: 'Trainee';
                $template = getEmailTemplate('payment_received', [
                    'name'     => $name,
                    'order_id' => $order_no,
                    'amount'   => $amount,
                ]);
                getEmailService($conn)->sendEmail($uinfo['email'], $template['subject'], $template['body']);
            }
        } catch (\Throwable $e) {
            error_log('Payment notification error: ' . $e->getMessage());
        }
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'DB update failed']);
        exit;
    }
} else {
    // Payment creation failed — log it but still return 200 to avoid PayMongo retrying
    error_log('PayMongo payment creation failed for order ' . $order_id . ': ' . json_encode($result['body']));
}

echo json_encode(['received' => true]);
