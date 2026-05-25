<?php
session_name('ugat_admin');
ini_set('display_errors', 0);
error_reporting(0);
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

// ── Fetch all orders ─────────────────────────────────────────
$r = $conn->query("
    SELECT
        o.id,
        o.address_id,
        o.address_snapshot,
        o.payment_method,
        o.gcash_ref,
        o.gcash_screenshot,
        o.subtotal,
        o.shipping_fee,
        o.total,
        o.status,
        o.notes,
        o.created_at,
        o.updated_at,
        COALESCE(tp.first_name, '') AS trainee_first,
        COALESCE(tp.last_name,  '') AS trainee_last
    FROM orders o
    LEFT JOIN users u             ON u.id  = o.user_id
    LEFT JOIN trainee_profiles tp ON tp.user_id = o.user_id
    ORDER BY o.created_at DESC
");

if (!$r) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
    exit;
}

$orders = [];
while ($order = $r->fetch_assoc()) {
    $order_id      = (int)$order['id'];
    $addr_snapshot = json_decode($order['address_snapshot'] ?? '{}', true);

    // Build trainee name
    $trainee_name = trim($order['trainee_first'] . ' ' . $order['trainee_last']);
    if (!$trainee_name) $trainee_name = $addr_snapshot['full_name'] ?? '—';

    // Build structured address object from snapshot + DB lookups
    $city_name     = '';
    $province_name = '';
    $region_name   = '';
    $barangay_name = '';

    if (!empty($addr_snapshot['city_id'])) {
        $city = $conn->query("SELECT name FROM cities WHERE id = " . (int)$addr_snapshot['city_id']);
        if ($city && $cRow = $city->fetch_assoc()) $city_name = $cRow['name'];
    }
    if (!empty($addr_snapshot['province_id'])) {
        $prov = $conn->query("SELECT name FROM provinces WHERE id = " . (int)$addr_snapshot['province_id']);
        if ($prov && $pRow = $prov->fetch_assoc()) $province_name = $pRow['name'];
    }
    if (!empty($addr_snapshot['region_id'])) {
        $reg = $conn->query("SELECT name FROM regions WHERE id = " . (int)$addr_snapshot['region_id']);
        if ($reg && $rRow = $reg->fetch_assoc()) $region_name = $rRow['name'];
    }
    if (!empty($addr_snapshot['barangay_id'])) {
        $bar = $conn->query("SELECT name FROM barangays WHERE id = " . (int)$addr_snapshot['barangay_id']);
        if ($bar && $bRow = $bar->fetch_assoc()) $barangay_name = $bRow['name'];
    }

    $address_obj = [
        'address_line'   => $addr_snapshot['address_line']   ?? '',
        'barangay_name'  => $barangay_name,
        'city_name'      => $city_name,
        'province_name'  => $province_name,
        'region_name'    => $region_name,
        'contact_number' => $addr_snapshot['contact_number'] ?? '',
        'full_name'      => $addr_snapshot['full_name']      ?? '',
    ];

    // Fetch order items
    $items_r = $conn->query("
        SELECT
            oi.quantity,
            oi.unit_price,
            oi.weight_kg,
            i.name,
            i.unit,
            i.image,
            (oi.unit_price * oi.quantity) AS subtotal
        FROM order_items oi
        JOIN inventory i ON i.id = oi.product_id
        WHERE oi.order_id = $order_id
    ");
    $items       = [];
    $items_count = 0;
    while ($item = $items_r->fetch_assoc()) {
        $items[] = [
            'name'       => $item['name'],
            'unit'       => $item['unit'],
            'image'      => $item['image'] ?? '',
            'quantity'   => (int)$item['quantity'],
            'unit_price' => (float)$item['unit_price'],
            'weight_kg'  => (float)$item['weight_kg'],
            'subtotal'   => (float)$item['subtotal'],
        ];
        $items_count += (int)$item['quantity'];
    }

    // Fetch status logs
    $logs_r = $conn->query("
        SELECT status, notes, created_at
        FROM order_status_logs
        WHERE order_id = $order_id
        ORDER BY created_at ASC
    ");
    $logs = [];
    while ($log = $logs_r->fetch_assoc()) {
        $logs[] = [
            'status'     => $log['status'],
            'notes'      => $log['notes'],
            'created_at' => $log['created_at'],
        ];
    }

    // Extract cancel reason from logs
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
        'trainee_name'   => $trainee_name,
        'address'        => $address_obj,
        'contact_number' => $addr_snapshot['contact_number'] ?? '—',
        'payment_method' => $order['payment_method'],
        'gcash_ref'        => $order['gcash_ref'],
        'gcash_screenshot' => $order['gcash_screenshot'],
        'subtotal'       => (float)$order['subtotal'],
        'shipping_fee'   => (float)$order['shipping_fee'],
        'total'          => (float)$order['total'],
        'status'         => $order['status'],
        'notes'          => $order['notes'],
        'cancel_reason'  => $cancel_reason,
        'created_at'     => $order['created_at'],
        'updated_at'     => $order['updated_at'],
        'items'          => $items,
        'items_count'    => $items_count,
        'status_logs'    => $logs,
    ];
}

echo json_encode(['success' => true, 'orders' => $orders]);