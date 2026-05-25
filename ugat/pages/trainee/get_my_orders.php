<?php
// ============================================================
//  get_my_orders.php — UGAT TrainTrack
//  Returns all orders for the logged-in trainee
// ============================================================
ini_set('display_errors', 0);
error_reporting(0);
session_name('ugat_trainee');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Fetch orders
$stmt = $conn->prepare("
    SELECT 
        o.id,
        o.address_snapshot,
        o.payment_method,
        o.gcash_ref,
        o.subtotal,
        o.shipping_fee,
        o.total,
        o.status,
        o.notes,
        o.created_at
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();

$orders = [];
while ($order = $orders_result->fetch_assoc()) {
    $order_id = (int)$order['id'];

    // Fetch order items
    $stmt2 = $conn->prepare("
        SELECT
            oi.product_id,
            oi.quantity,
            oi.unit_price,
            oi.weight_kg,
            i.name,
            i.image,
            i.unit
        FROM order_items oi
        JOIN inventory i ON i.id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $stmt2->bind_param('i', $order_id);
    $stmt2->execute();
    $items_result = $stmt2->get_result();
    $stmt2->close();

    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = [
            'product_id' => (int)$item['product_id'],
            'name'       => $item['name'],
            'image'      => $item['image'] ?? '',
            'quantity'   => (int)$item['quantity'],
            'unit_price' => (float)$item['unit_price'],
            'unit'       => $item['unit'],
            'subtotal'   => (float)$item['unit_price'] * (int)$item['quantity'],
        ];
    }

    // Fetch status logs
    $stmt3 = $conn->prepare("
        SELECT status, notes, created_at
        FROM order_status_logs
        WHERE order_id = ?
        ORDER BY created_at ASC
    ");
    $stmt3->bind_param('i', $order_id);
    $stmt3->execute();
    $logs_result = $stmt3->get_result();
    $stmt3->close();

    $logs = [];
    while ($log = $logs_result->fetch_assoc()) {
        $logs[] = [
            'status'     => $log['status'],
            'notes'      => $log['notes'],
            'created_at' => $log['created_at'],
        ];
    }

    $addr_snapshot = json_decode($order['address_snapshot'] ?? '{}', true);

    $cancel_reason = '';
    foreach ($logs as $log) {
        if ($log['status'] === 'cancelled' && !empty($log['notes'])) {
            $cancel_reason = $log['notes'];
            break;
        }
    }

    $orders[] = [
        'id'             => $order_id,
        'order_code'     => 'ORD-' . str_pad($order_id, 6, '0', STR_PAD_LEFT),
        'payment_method' => $order['payment_method'],
        'gcash_ref'      => $order['gcash_ref'],
        'subtotal'       => (float)$order['subtotal'],
        'shipping_fee'   => (float)$order['shipping_fee'],
        'total'          => (float)$order['total'],
        'status'         => $order['status'],
        'notes'          => $order['notes'],
        'cancel_reason'  => $cancel_reason,
        'created_at'     => $order['created_at'],
        'address'        => $addr_snapshot,
        'items'          => $items,
        'status_logs'    => $logs,
    ];
}

echo json_encode(['success' => true, 'orders' => $orders]);