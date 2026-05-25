<?php
// ============================================================
//  update_order_status.php — UGAT TrainTrack
//  Admin: update a Farm Shop order status and log the change
//
//  POST body (JSON):
//    { "order_id": 1, "status": "confirmed", "note": "optional" }
// ============================================================
ini_set('display_errors', 0);
error_reporting(0);
session_name('ugat_admin');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$admin_id   = (int)$_SESSION['user_id'];
$data       = json_decode(file_get_contents('php://input'), true);

$order_id   = (int)($data['order_id'] ?? 0);
$new_status = trim($data['status']    ?? '');
$note       = trim($data['note']      ?? '');

$valid_statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

// Sequential flow: each status can only move to the next one or be cancelled
$status_flow = [
    'pending'          => 'confirmed',
    'confirmed'        => 'preparing',
    'preparing'        => 'out_for_delivery',
    'out_for_delivery' => 'delivered',
    'delivered'        => null,
    'cancelled'        => null,
];

if (!$order_id || !in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID or status.']);
    exit;
}

// ── Verify order exists ───────────────────────────────────────
$stmt = $conn->prepare("SELECT id, status, user_id, total, payment_method, gcash_ref FROM orders WHERE id = ?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

if ($order['status'] === $new_status) {
    echo json_encode(['success' => false, 'message' => 'Order is already in "' . $new_status . '" status.']);
    exit;
}

// Enforce sequential progression: only allow next step or cancel
$current = $order['status'];
if ($current === 'cancelled' || $current === 'delivered') {
    echo json_encode(['success' => false, 'message' => 'Cannot update a ' . $current . ' order.']);
    exit;
}
if ($new_status !== 'cancelled' && $status_flow[$current] !== $new_status) {
    $allowed_next = $status_flow[$current] ?? 'none';
    echo json_encode(['success' => false, 'message' => 'Invalid status change. Order must move from "' . $current . '" to "' . $allowed_next . '" or be cancelled.']);
    exit;
}

$conn->begin_transaction();
try {
    // ── Restore stock if cancelling ───────────────────────────
    if ($new_status === 'cancelled' && $order['status'] !== 'cancelled') {
        $items_r = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = $order_id");
        while ($item = $items_r->fetch_assoc()) {
            $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
            $stmt->bind_param('ii', $item['quantity'], $item['product_id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    // ── Update order status ───────────────────────────────────
    $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $new_status, $order_id);
    $stmt->execute();
    $stmt->close();

    // ── Log the change ────────────────────────────────────────
    $log_note = $note ?: 'Status updated to ' . str_replace('_', ' ', $new_status);

    $stmt = $conn->prepare("
        INSERT INTO order_status_logs (order_id, status, changed_by, notes)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('isis', $order_id, $new_status, $admin_id, $log_note);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // ── Send notification ─────────────────────────────────────
    $status_event_map = [
        'confirmed'        => 'order_confirmed',
        'preparing'        => 'order_preparing',
        'out_for_delivery' => 'order_shipped',
        'delivered'        => 'order_delivered',
        'cancelled'        => 'order_cancelled',
    ];

    if (isset($status_event_map[$new_status]) && !empty($order['user_id'])) {
        try {
            require_once '../../config/sms_helpers.php';
            require_once '../../config/email_service.php';
            require_once '../../config/email.php';

            $event    = $status_event_map[$new_status];
            $order_no = str_pad($order_id, 6, '0', STR_PAD_LEFT);
            $amount   = '₱' . number_format((float)$order['total'], 2);

            // SMS (direct — bypasses preferences, uses trainee_profiles.phone)
            sendSmsForEvent($event, (int)$order['user_id'], [
                'order_id' => $order_no,
                'amount'   => $amount,
            ]);

            // Email (always send — get notification email or fall back to login email)
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
                $template = getEmailTemplate($event, [
                    'name'     => $name,
                    'order_id' => $order_no,
                    'amount'   => $amount,
                ]);
                getEmailService($conn)->sendEmail($uinfo['email'], $template['subject'], $template['body']);
            }
        } catch (\Throwable $e) {
            error_log('Order notification error: ' . $e->getMessage());
        }
    }

    $response = [
        'success'    => true,
        'message'    => 'Order status updated to "' . $new_status . '".',
        'order_id'   => $order_id,
        'new_status' => $new_status,
    ];

    // Include GCash ref if cancelling a GCash order
    if ($new_status === 'cancelled' && $order['payment_method'] === 'gcash') {
        $response['gcash_ref']     = $order['gcash_ref'];
        $response['refund_needed'] = true;
    }

    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed: ' . $e->getMessage()]);
}