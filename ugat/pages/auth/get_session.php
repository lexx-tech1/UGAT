<?php
ini_set('session.cookie_path', '/');
// Use separate session cookies for admin and trainee
$requested_role = $_GET['role'] ?? '';
if ($requested_role === 'admin') {
    session_name('ugat_admin');
} else {
    session_name('ugat_trainee');
}
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

if ($role === 'trainee') {
    $stmt = $conn->prepare(
        'SELECT u.email, u.role, u.created_at,
                t.first_name, t.last_name, t.middle_name,
                t.phone, t.address, t.city, t.province, t.region,
                t.barangay,
                t.nationality, t.birthdate, t.gender, t.civil_status,
                t.birthplace_city, t.birthplace_prov, t.birthplace_reg,
                t.education, t.employment, t.learner_class,
                t.is_pwd, t.guardian_name, t.guardian_addr,
                t.profile_pic, t.trainee_id_no, t.batch,
                t.date_enrolled, t.status
         FROM users u
         LEFT JOIN trainee_profiles t ON t.user_id = u.id
         WHERE u.id = ? LIMIT 1'
    );
} else {
    $stmt = $conn->prepare(
        'SELECT u.email, u.role, u.created_at,
                a.first_name, a.last_name, a.phone, a.profile_pic
         FROM users u
         JOIN admin_profiles a ON a.user_id = u.id
         WHERE u.id = ? LIMIT 1'
    );
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

// ── Auto-seed user_addresses from trainee_profiles if trainee has none ──
if ($role === 'trainee') {
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM user_addresses WHERE user_id = ?");
    $check->bind_param('i', $user_id);
    $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['cnt'];
    $check->close();

    if ($cnt == 0 && !empty($user['address'])) {
        // Try to match city from trainee_profiles to cities table
        $city_name = trim($user['city'] ?? '');
        $city_id = $province_id = $region_id = null;

        if ($city_name) {
            $cs = $conn->prepare("SELECT id, province_id FROM cities WHERE name = ? LIMIT 1");
            $cs->bind_param('s', $city_name);
            $cs->execute();
            $crow = $cs->get_result()->fetch_assoc();
            $cs->close();
            if ($crow) {
                $city_id     = $crow['id'];
                $province_id = $crow['province_id'];
                // Get region
                $rs = $conn->prepare("SELECT region_id FROM provinces WHERE id = ? LIMIT 1");
                $rs->bind_param('i', $province_id);
                $rs->execute();
                $rrow = $rs->get_result()->fetch_assoc();
                $rs->close();
                $region_id = $rrow['region_id'] ?? null;
            }
        }

        $full_name = trim(
    ($user['first_name'] ?? '') . ' ' .
    ($user['last_name']  ?? '') . ' ' .
    ($user['middle_name'] ?? '')
);
        $phone     = $user['phone'] ?? '';
        $address   = $user['address'] ?? '';

        $ins = $conn->prepare("
            INSERT INTO user_addresses 
            (user_id, full_name, contact_number, address_line, city_id, province_id, region_id, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $ins->bind_param('isssiii',
            $user_id, $full_name, $phone, $address,
            $city_id, $province_id, $region_id
        );
        $ins->execute();
        $ins->close();
    }
}

$conn->close();

echo json_encode(['success' => true, 'user' => $user]);