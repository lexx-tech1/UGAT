<?php
// ============================================================
//  admin/save_settings.php
//  Saves settings sections to the settings table
//  Actions: save_org, save_sms, save_certs, save_workshops, save_system
// ============================================================
session_name('ugat_admin');

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

$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

// Helper: upsert a setting key-value
function saveSetting($conn, $key, $value) {
    $k = $conn->real_escape_string($key);
    $v = $conn->real_escape_string($value ?? '');
    $conn->query("INSERT INTO settings (`key`, `value`) VALUES ('$k', '$v')
                  ON DUPLICATE KEY UPDATE `value` = '$v', updated_at = NOW()");
}

switch ($action) {

    case 'save_org':
        saveSetting($conn, 'org_name',        $input['org_name']        ?? '');
        saveSetting($conn, 'org_short_name',   $input['org_short_name']  ?? '');
        saveSetting($conn, 'org_address',      $input['org_address']     ?? '');
        saveSetting($conn, 'org_email',        $input['org_email']       ?? '');
        saveSetting($conn, 'org_phone',        $input['org_phone']       ?? '');
        saveSetting($conn, 'org_website',      $input['org_website']     ?? '');
        saveSetting($conn, 'org_description',  $input['org_description'] ?? '');
        echo json_encode(['success' => true, 'message' => 'Organization info saved!']);
        break;

    case 'save_sms':
        saveSetting($conn, 'sms_api_key',              $input['sms_api_key']              ?? '');
        saveSetting($conn, 'sms_cert_trigger',         $input['sms_cert_trigger']         ?? '0');
        saveSetting($conn, 'sms_session_reminder',     $input['sms_session_reminder']     ?? '0');
        saveSetting($conn, 'sms_enrollment_confirm',   $input['sms_enrollment_confirm']   ?? '0');
        saveSetting($conn, 'sms_absence_alert',        $input['sms_absence_alert']        ?? '0');
        echo json_encode(['success' => true, 'message' => 'SMS settings saved!']);
        break;

    case 'save_certs':
        saveSetting($conn, 'cert_title',           $input['cert_title']           ?? 'Certificate of Completion');
        saveSetting($conn, 'cert_authority',       $input['cert_authority']       ?? '');
        saveSetting($conn, 'cert_signatory',       $input['cert_signatory']       ?? '');
        saveSetting($conn, 'cert_prefix',          $input['cert_prefix']          ?? 'UGAT-2026-');
        saveSetting($conn, 'cert_threshold',       $input['cert_threshold']       ?? '67');
        saveSetting($conn, 'cert_min_sessions',    $input['cert_min_sessions']    ?? '2');
        saveSetting($conn, 'cert_auto_flag',       $input['cert_auto_flag']       ?? '0');
        echo json_encode(['success' => true, 'message' => 'Certification settings saved!']);
        break;

    case 'save_workshops':
        saveSetting($conn, 'ws_default_location',   $input['ws_default_location']  ?? '');
        saveSetting($conn, 'ws_default_max_slots',  $input['ws_default_max_slots'] ?? '25');
        saveSetting($conn, 'ws_default_time',       $input['ws_default_time']      ?? '8:00 AM – 5:00 PM');
        saveSetting($conn, 'ws_categories',         $input['ws_categories']        ?? '');
        echo json_encode(['success' => true, 'message' => 'Workshop defaults saved!']);
        break;

    case 'save_system':
        saveSetting($conn, 'timezone',    $input['timezone']    ?? 'Asia/Manila');
        saveSetting($conn, 'date_format', $input['date_format'] ?? 'MMM D, YYYY');
        saveSetting($conn, 'language',    $input['language']    ?? 'English');
        echo json_encode(['success' => true, 'message' => 'System settings saved!']);
        break;

    // ── Edit Admin User ──────────────────────────────────────
    case 'edit_admin_user':
        $id         = (int)($input['id']         ?? 0);
        $first_name = trim($input['first_name']  ?? '');
        $last_name  = trim($input['last_name']   ?? '');
        $is_active  = (int)($input['is_active']  ?? 1);
        $password   = trim($input['password']    ?? '');

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
            break;
        }

        // Update is_active in users table
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'admin'");
        $stmt->bind_param('ii', $is_active, $id);
        $stmt->execute();
        $stmt->close();

        // Update name in trainee_profiles or admin_profiles
        $has_ap = $conn->query("SHOW TABLES LIKE 'admin_profiles'")->num_rows > 0;
        $has_tp = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;

        if ($first_name || $last_name) {
            if ($has_ap) {
                $stmt = $conn->prepare("INSERT INTO admin_profiles (user_id, first_name, last_name)
                    VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE first_name=?, last_name=?");
                $stmt->bind_param('issss', $id, $first_name, $last_name, $first_name, $last_name);
                $stmt->execute(); $stmt->close();
            } elseif ($has_tp) {
                $stmt = $conn->prepare("UPDATE trainee_profiles SET first_name=?, last_name=? WHERE user_id=?");
                $stmt->bind_param('ssi', $first_name, $last_name, $id);
                $stmt->execute(); $stmt->close();
            }
        }

        // Update password if provided
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $hash, $id);
            $stmt->execute(); $stmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Admin user updated successfully!']);
        break;

    case 'save_payment':
        saveSetting($conn, 'gcash_account_name', $input['gcash_account_name'] ?? '');
        echo json_encode(['success' => true, 'message' => 'Payment settings saved!']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();