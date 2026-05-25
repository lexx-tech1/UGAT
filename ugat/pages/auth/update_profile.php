<?php
// ============================================================
//  auth/update_profile.php
//  Updates the logged-in trainee's profile fields.
//  Also handles email update in users table.
// ============================================================

ini_set('session.cookie_path', '/');
// Use same session cookie name as get_session.php
$requested_role = $_GET['role'] ?? 'trainee';
if ($requested_role === 'admin') {
    session_name('ugat_admin');
} else {
    session_name('ugat_trainee');
}
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// All allowed trainee_profiles fields
$allowed = [
    'first_name'      => 's',
    'last_name'       => 's',
    'middle_name'     => 's',
    'phone'           => 's',
    'address'         => 's',
    'city'            => 's',
    'province'        => 's',
    'region'          => 's',
    'nationality'     => 's',
    'birthdate'       => 's',
    'gender'          => 's',
    'civil_status'    => 's',
    'birthplace_city' => 's',
    'birthplace_prov' => 's',
    'birthplace_reg'  => 's',
    'education'       => 's',
    'employment'      => 's',
    'learner_class'   => 's',
    'is_pwd'          => 'i',
    'guardian_name'   => 's',
    'guardian_addr'   => 's',
];

$fields = [];
$params = [];
$types  = '';

foreach ($allowed as $field => $type) {
    if (isset($_POST[$field])) {
        $value = ($type === 'i') ? (int)$_POST[$field] : $_POST[$field];
        
        // Sanitize phone numbers - remove spaces and special characters
        if ($field === 'phone' && is_string($value)) {
            $value = preg_replace('/[^0-9+]/', '', $value);
        }
        
        $fields[] = "$field = ?";
        $params[] = $value;
        $types   .= $type;
    }
}

// Update trainee_profiles if there are fields to update
if (!empty($fields)) {
    $types   .= 'i';
    $params[] = $user_id;
    $sql      = 'UPDATE trainee_profiles SET ' . implode(', ', $fields) . ' WHERE user_id = ?';
    $stmt     = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Profile update failed.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
}

// Update email in users table if provided
if (!empty($_POST['email'])) {
    $new_email = trim(strtolower($_POST['email']));

    // Check if email is taken by another user
    $check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $check->bind_param('si', $new_email, $user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        echo json_encode(['success' => false, 'message' => 'That email is already used by another account.']);
        $conn->close();
        exit;
    }
    $check->close();

    $estmt = $conn->prepare('UPDATE users SET email = ? WHERE id = ?');
    $estmt->bind_param('si', $new_email, $user_id);
    $estmt->execute();
    $estmt->close();

    // Update session so user stays logged in
    $_SESSION['email'] = $new_email;
}

echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
$conn->close();