<?php
// get_product_reviews.php — returns reviews and avg rating for a product
session_name('ugat_trainee');
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

$product_id = (int)($_GET['product_id'] ?? 0);
$user_id    = (int)($_SESSION['user_id'] ?? 0);

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

// Ensure table exists
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

// Avg rating and count
$agg = $conn->query("
    SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total
    FROM product_reviews WHERE product_id = $product_id
")->fetch_assoc();

// Reviews with trainee first name (no last name for privacy)
$rows = $conn->query("
    SELECT pr.rating, pr.comment, pr.created_at,
           CONCAT(LEFT(tp.first_name,1), REPEAT('*', CHAR_LENGTH(tp.first_name)-1), ' ', LEFT(tp.last_name,1), '.') AS display_name
    FROM product_reviews pr
    LEFT JOIN trainee_profiles tp ON tp.user_id = pr.user_id
    WHERE pr.product_id = $product_id
    ORDER BY pr.created_at DESC
    LIMIT 20
");

$reviews = [];
if ($rows) {
    while ($r = $rows->fetch_assoc()) {
        $reviews[] = [
            'rating'       => (int)$r['rating'],
            'comment'      => $r['comment'],
            'display_name' => $r['display_name'] ?: 'Trainee',
            'date'         => $r['created_at'] ? date('M j, Y', strtotime($r['created_at'])) : '',
        ];
    }
}

// Check if this user already reviewed
$myReview = null;
if ($user_id) {
    $mr = $conn->query("SELECT rating, comment FROM product_reviews WHERE product_id = $product_id AND user_id = $user_id LIMIT 1");
    if ($mr) $myReview = $mr->fetch_assoc();
}

// Check if user has a delivered order with this product (eligibility to review)
$canReview = false;
if ($user_id) {
    $el = $conn->prepare("
        SELECT oi.id FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
        LIMIT 1
    ");
    $el->bind_param('ii', $user_id, $product_id);
    $el->execute();
    $el->store_result();
    $canReview = $el->num_rows > 0;
    $el->close();
}

echo json_encode([
    'success'    => true,
    'avg_rating' => (float)($agg['avg_rating'] ?? 0),
    'total'      => (int)($agg['total'] ?? 0),
    'reviews'    => $reviews,
    'can_review' => $canReview,
    'my_review'  => $myReview,
]);
$conn->close();
