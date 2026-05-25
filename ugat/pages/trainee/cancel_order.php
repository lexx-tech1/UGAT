<?php
ini_set('display_errors', 0);
session_name('ugat_trainee');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$data     = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($data['order_id'] ?? 0);
$reason   = trim($data['reason'] ?? 'Cancelled by trainee');

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order.']);
    exit;
}

// Only allow cancellation if order belongs to this user AND is still pending
$stmt = $conn->prepare("
    SELECT id, status FROM orders 
    WHERE id = ? AND user_id = ? AND status = 'pending'
");
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found or already confirmed.']);
    exit;
}

$conn->begin_transaction();
try {
    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();

    // Restore stock for each item
    $items = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $items->bind_param('i', $order_id);
    $items->execute();
    $rows = $items->get_result();
    $items->close();

    while ($item = $rows->fetch_assoc()) {
        $restore = $conn->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
        $restore->bind_param('ii', $item['quantity'], $item['product_id']);
        $restore->execute();
        $restore->close();
    }

    // Log the cancellation with reason
    $log = $conn->prepare("
        INSERT INTO order_status_logs (order_id, status, changed_by, notes)
        VALUES (?, 'cancelled', ?, ?)
    ");
    $log->bind_param('iis', $order_id, $user_id, $reason);
    $log->execute();
    $log->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to cancel order.']);
}