<?php
// payment_return.php
// PayMongo redirects the user here after they complete (or cancel) GCash payment.
// The actual payment confirmation comes from the webhook, not this redirect.
// This page just shows feedback and sends the user to My Orders.

session_name('ugat_trainee');
session_start();
require_once '../../config/db.php';

$order_id = (int) ($_GET['order_id'] ?? 0);
$status   = $_GET['status'] === 'success' ? 'success' : 'failed';

// Check current order status from DB
$order = null;
if ($order_id) {
    $stmt = $conn->prepare("SELECT status, total FROM orders WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$isPaid = $order && in_array($order['status'], ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isPaid || $status === 'success' ? 'Payment Received' : 'Payment Not Completed' ?> — UGAT</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8faf5; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 2.5rem 2rem; max-width: 420px; width: 100%; text-align: center; }
    .icon { font-size: 3.5rem; margin-bottom: 1rem; }
    h2 { font-size: 1.35rem; font-weight: 800; margin-bottom: 0.5rem; }
    p  { font-size: 0.9rem; color: #666; line-height: 1.6; margin-bottom: 0.4rem; }
    .order-num { font-family: monospace; font-weight: 700; font-size: 1rem; color: #4B8423; }
    .btn { display: inline-block; margin-top: 1.5rem; background: #4B8423; color: #fff; padding: 0.7rem 1.8rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; }
    .btn-outline { background: #fff; color: #4B8423; border: 1.5px solid #4B8423; margin-left: 0.5rem; }
    .note { font-size: 0.75rem; color: #999; margin-top: 1rem; }
  </style>
</head>
<body>
<div class="card">
<?php if ($isPaid || $status === 'success'): ?>
  <div class="icon">✅</div>
  <h2>Payment Received!</h2>
  <?php if ($order_id): ?>
    <p>Order <span class="order-num">#<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></span> has been placed.</p>
  <?php endif; ?>
  <p>Your GCash payment was processed successfully. UGAT staff will review and prepare your order.</p>
  <p class="note">You will receive an SMS notification once your order is confirmed.</p>
  <a href="TraineeShop.html" class="btn">Back to Shop</a>
<?php else: ?>
  <div class="icon">❌</div>
  <h2>Payment Not Completed</h2>
  <p>Your GCash payment was not completed or was cancelled.</p>
  <?php if ($order_id): ?>
    <p>Order <span class="order-num">#<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></span> has been cancelled.</p>
    <?php
      // Cancel the pending_payment order so inventory is restored
      if ($order && $order['status'] === 'pending_payment') {
          $conn->query("UPDATE orders SET status = 'cancelled' WHERE id = {$order_id} AND status = 'pending_payment'");
          // Restore inventory
          $items = $conn->query("SELECT product_id, quantity FROM order_items WHERE order_id = {$order_id}");
          while ($row = $items->fetch_assoc()) {
              $conn->query("UPDATE inventory SET quantity = quantity + {$row['quantity']} WHERE id = {$row['product_id']}");
          }
      }
    ?>
  <?php endif; ?>
  <p class="note">No charge was made. You can try again or choose Cash on Delivery.</p>
  <a href="TraineeShop.html" class="btn">Try Again</a>
<?php endif; ?>
</div>
</body>
</html>
