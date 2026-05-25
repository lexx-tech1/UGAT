<?php
// ============================================================
//  auth/register.php  —  Trainee Registration Handler
// ============================================================

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Collect & sanitise
$email       = trim(strtolower($_POST['email']       ?? ''));
$password    = $_POST['password']                    ?? '';
$confirm     = $_POST['confirm_password']            ?? '';
$first_name  = trim($_POST['first_name']             ?? '');
$last_name   = trim($_POST['last_name']              ?? '');
$phone       = trim($_POST['phone']                  ?? '');
$address     = trim($_POST['address']                ?? '');
$city        = trim($_POST['city']                   ?? '');
$province    = trim($_POST['province']               ?? '');
$region      = trim($_POST['region']                 ?? '');
$barangay    = trim($_POST['barangay']    ?? '');

// Validate
$errors = [];
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
if (strlen($password) < 8)                      $errors[] = 'Password must be at least 8 characters.';
if ($password !== $confirm)                      $errors[] = 'Passwords do not match.';
if (empty($first_name) || empty($last_name))     $errors[] = 'First name and last name are required.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Check duplicate email
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Email is already registered.']);
    exit;
}
$stmt->close();

// Hash password
$hash = password_hash($password, PASSWORD_BCRYPT);

// Insert
$conn->begin_transaction();
try {
    $stmt = $conn->prepare('INSERT INTO users (email, password_hash, role, is_active) VALUES (?, ?, "trainee", 1)');
    $stmt->bind_param('ss', $email, $hash);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();

$stmt = $conn->prepare(
    'INSERT INTO trainee_profiles
        (user_id, first_name, last_name, phone, address, city, province, region, barangay)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
// Build full address including barangay
$full_address = implode(', ', array_filter([$address, $barangay, $city, $province, $region]));
$stmt->bind_param('issssssss', $user_id, $first_name, $last_name, $phone, $full_address, $city, $province, $region, $barangay);
$stmt->execute();
$stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Registration successful!']);

} catch (Exception $e) {
    $conn->rollback();
    error_log('Registration error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}

$conn->close();