<?php
// submit_review.php — trainee submits a product review (only for delivered orders)
session_name('ugat_trainee');
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'trainee') {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$data    = json_decode(file_get_contents('php://input'), true);

$product_id = (int)($data['product_id'] ?? 0);
$rating     = (int)($data['rating']     ?? 0);
$comment    = trim($data['comment']     ?? '');

if (!$product_id || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or rating (1–5 required).']);
    exit;
}

// Verify the trainee has a delivered order containing this product
$chk = $conn->prepare("
    SELECT oi.id FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
    LIMIT 1
");
$chk->bind_param('ii', $user_id, $product_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    $chk->close();
    echo json_encode(['success' => false, 'message' => 'You can only review products from delivered orders.']);
    exit;
}
$chk->close();

// Ensure reviews table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS product_reviews (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        product_id  INT NOT NULL,
        user_id     INT NOT NULL,
        rating      TINYINT NOT NULL,
        comment     TEXT,
        created_at  DATETIME DEFAULT NOW(),
        UNIQUE KEY uq_review (product_id, user_id)
    )
");

// Upsert review (one review per product per user)
$stmt = $conn->prepare("
    INSERT INTO product_reviews (product_id, user_id, rating, comment)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = NOW()
");
$stmt->bind_param('iiis', $product_id, $user_id, $rating, $comment);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Review submitted. Thank you!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit review.']);
}
$stmt->close();
$conn->close();
