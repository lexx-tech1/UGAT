<?php
// ============================================================
//  admin/save_workshops.php
//  Actions: add_workshop, edit_workshop, save_attendance,
//           issue_certificate, add_trainee, delete_trainee
// ============================================================
session_name('ugat_admin');

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

error_log('Session user_id: ' . ($_SESSION['user_id'] ?? 'none') . ', role: ' . ($_SESSION['role'] ?? 'none'));
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in. Please login again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

// ── Semaphore SMS helper ──────────────────────────────────────
function sendSemaphoreSMS($conn, $phone, $message) {
    // Get API key from settings
    $r = $conn->query("SELECT value FROM settings WHERE `key` = 'sms_api_key' LIMIT 1");
    $api_key = $r ? trim($r->fetch_assoc()['value'] ?? '') : '';

    $r2 = $conn->query("SELECT value FROM settings WHERE `key` = 'sms_sender' LIMIT 1");
    $sender = $r2 ? trim($r2->fetch_assoc()['value'] ?? 'UGAT') : 'UGAT';

    if (empty($api_key)) {
        return ['success' => false, 'error' => 'No Semaphore API key configured in Settings.'];
    }

    // Normalize phone: remove spaces and dashes, ensure +63 format
    $phone_clean = preg_replace('/[\s\-]/', '', $phone);
    if (substr($phone_clean, 0, 3) === '+63') {
        $phone_clean = '0' . substr($phone_clean, 3); // Convert +63XXXXXXXXX → 09XXXXXXXXX
    } elseif (substr($phone_clean, 0, 2) === '63') {
        $phone_clean = '0' . substr($phone_clean, 2);
    }

    // Validate PH mobile number: 09XX XXX XXXX (11 digits starting with 09)
    if (!preg_match('/^09\d{9}$/', $phone_clean)) {
        return ['success' => false, 'error' => "Invalid phone number format: $phone_clean"];
    }

    // Send via Semaphore API
    $post_data = [
        'apikey'  => $api_key,
        'number'  => $phone_clean,
        'message' => $message,
        'sendername' => $sender,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['success' => false, 'error' => "Network error: $curl_error"];
    }

    $result = json_decode($response, true);

    // Semaphore returns array of message objects on success
    if ($http_code === 200 && is_array($result) && !empty($result)) {
        $msg_status = $result[0]['status'] ?? 'unknown';

        // Check for common failure statuses
        if (in_array(strtolower($msg_status), ['failed', 'invalid', 'blocked', 'expired'])) {
            return [
                'success' => false,
                'error'   => "SMS could not be delivered. Number may be invalid, blocked, or unreachable. Status: $msg_status",
            ];
        }

        return ['success' => true, 'status' => $msg_status, 'message_id' => $result[0]['message_id'] ?? null];
    }

    // Handle error response
    $err_msg = 'SMS send failed.';
    if (is_array($result) && isset($result['message'])) $err_msg = $result['message'];
    elseif (is_array($result) && isset($result['error']))   $err_msg = $result['error'];
    elseif ($http_code === 401) $err_msg = 'Invalid Semaphore API key.';
    elseif ($http_code === 422) $err_msg = 'Invalid phone number or message content.';
    elseif ($http_code === 429) $err_msg = 'SMS rate limit reached. Try again later.';

    return ['success' => false, 'error' => $err_msg, 'http_code' => $http_code];
}

// ── Auto-eligibility check ────────────────────────────────────
function checkEligibility($conn, $workshop_id) {
    $r = $conn->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('cert_threshold','cert_min_sessions','cert_auto_flag')");
    $s = ['cert_threshold' => 67, 'cert_min_sessions' => 1, 'cert_auto_flag' => '1'];
    if ($r) while ($row = $r->fetch_assoc()) $s[$row['key']] = $row['value'];
    if ($s['cert_auto_flag'] !== '1') return 0;

    $threshold     = (float)$s['cert_threshold'];
    $min_sessions  = (int)$s['cert_min_sessions'];
    $wid           = (int)$workshop_id;

    $r = $conn->query("
        SELECT e.user_id, e.workshop_id,
               COUNT(a.id) AS total_att,
               SUM(a.status IN ('present','late')) AS attended
        FROM enrollments e
        JOIN workshop_sessions ws ON ws.workshop_id = e.workshop_id
        LEFT JOIN attendance a ON a.session_id = ws.id AND a.user_id = e.user_id
                               AND a.status != 'upcoming'
        WHERE e.workshop_id = $wid
        GROUP BY e.user_id, e.workshop_id
    ");

    $newly_flagged = 0;
    if (!$r) return 0;

    while ($row = $r->fetch_assoc()) {
        $total    = (int)$row['total_att'];
        $attended = (int)$row['attended'];
        if ($total === 0) continue;
        $rate = ($attended / $total) * 100;
        if ($rate >= $threshold && $attended >= $min_sessions) {
            $uid  = (int)$row['user_id'];
            $wid2 = (int)$row['workshop_id'];
            $rate_rounded = round($rate, 1);
            $check = $conn->query("SELECT id, status FROM certificates WHERE user_id=$uid AND workshop_id=$wid2 LIMIT 1");
            $existing = $check ? $check->fetch_assoc() : null;
            if (!$existing) {
                $stmt = $conn->prepare("INSERT INTO certificates (user_id, workshop_id, status, attendance_rate, created_at) VALUES (?,?,'eligible',?,NOW())");
                $stmt->bind_param('iid', $uid, $wid2, $rate_rounded);
                $stmt->execute(); $stmt->close();
                $newly_flagged++;
            }
        }
    }
    return $newly_flagged;
}

switch ($action) {

    // ── Add Workshop ──────────────────────────────────────────
    case 'add_workshop':
        $title     = trim($input['title']    ?? '');
        $category  = trim($input['category'] ?? '');
        $location  = trim($input['location'] ?? 'UGAT Demo Farm');
        $max_slots = (int)($input['max_slots'] ?? 25);
        $status    = $input['status']   ?? 'upcoming';
        $desc      = trim($input['description'] ?? '');

        if (!$title || !$category) {
            echo json_encode(['success' => false, 'message' => 'Title and category are required.']);
            break;
        }

        $facilitator = trim($input['facilitator']      ?? '');
        $outcomes    = trim($input['outcomes']          ?? '');
        $materials   = trim($input['materials']         ?? '');
        $cert_req    = trim($input['cert_requirement']  ?? '');

        $stmt = $conn->prepare(
            'INSERT INTO workshops (title, category, facilitator, description, outcomes, materials, cert_requirement, location, max_slots, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssssssss', $title, $category, $facilitator, $desc, $outcomes, $materials, $cert_req, $location, $max_slots, $status);
        if ($stmt->execute()) {
            $workshop_id = $conn->insert_id;
            $sessions = $input['sessions'] ?? [];
            foreach ($sessions as $i => $sess) {
                $sess_date = $sess['date'] ?? null;
                $sess_time = $sess['time'] ?? null;
                $sess_no   = $i + 1;
                $s = $conn->prepare('INSERT INTO workshop_sessions (workshop_id, session_no, session_date, start_time) VALUES (?, ?, ?, ?)');
                $s->bind_param('iiss', $workshop_id, $sess_no, $sess_date, $sess_time);
                $s->execute(); $s->close();
            }
            $log = $conn->prepare('INSERT INTO activity_log (action, color) VALUES (?, ?)');
            $msg = "Workshop \"$title\" created"; $col = '#1a56db';
            $log->bind_param('ss', $msg, $col); $log->execute(); $log->close();
            echo json_encode(['success' => true, 'message' => "\"$title\" added!", 'workshop_id' => $workshop_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add workshop.']);
        }
        $stmt->close();
        break;

    // ── Edit Workshop ─────────────────────────────────────────
    case 'edit_workshop':
        $id          = (int)($input['id']          ?? 0);
        $title       = trim($input['title']        ?? '');
        $category    = trim($input['category']     ?? '');
        $facilitator = trim($input['facilitator']  ?? '');
        $location    = trim($input['location']     ?? '');
        $max_slots   = (int)($input['max_slots']   ?? 25);
        $desc        = trim($input['description']  ?? '');
        $sessions    = $input['sessions']          ?? [];

        $stmt = $conn->prepare('UPDATE workshops SET title=?, category=?, facilitator=?, description=?, location=?, max_slots=? WHERE id=?');
        $stmt->bind_param('sssssii', $title, $category, $facilitator, $desc, $location, $max_slots, $id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Update failed.']);
            $stmt->close(); break;
        }
        $stmt->close();

        // Update session dates
        foreach ($sessions as $sess) {
            $sess_id   = (int)($sess['id']   ?? 0);
            $sess_date = $sess['date'] ?? null;
            $sess_time = $sess['time'] ?? null;
            if (!$sess_id) continue;
            $s = $conn->prepare('UPDATE workshop_sessions SET session_date=?, start_time=? WHERE id=? AND workshop_id=?');
            $s->bind_param('ssii', $sess_date, $sess_time, $sess_id, $id);
            $s->execute(); $s->close();
        }

        echo json_encode(['success' => true, 'message' => "\"$title\" updated!"]);
        break;

    // ── Save Attendance (with auto-eligibility) ───────────────
    case 'save_attendance':
        $session_id = (int)($input['session_id'] ?? 0);
        $records    = $input['records']           ?? [];

        if (!$session_id || empty($records)) {
            echo json_encode(['success' => false, 'message' => 'Session ID and records required.']);
            break;
        }

        $saved = 0;
        foreach ($records as $rec) {
            $user_id = (int)($rec['user_id'] ?? 0);
            $status  = $rec['status'] ?? 'present';
            if (!$user_id) continue;
            $stmt = $conn->prepare('INSERT INTO attendance (session_id, user_id, status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)');
            $stmt->bind_param('iis', $session_id, $user_id, $status);
            if ($stmt->execute()) $saved++;
            $stmt->close();
        }

        // Auto-eligibility check
        $ws = $conn->query("SELECT workshop_id FROM workshop_sessions WHERE id=$session_id LIMIT 1");
        $ws_row = $ws ? $ws->fetch_assoc() : null;
        $newly_flagged = $ws_row ? checkEligibility($conn, $ws_row['workshop_id']) : 0;

        $log = $conn->prepare('INSERT INTO activity_log (action, color) VALUES (?,?)');
        $msg = "Attendance logged for session #$session_id ($saved records)"; $col = '#2a9d8f';
        $log->bind_param('ss', $msg, $col); $log->execute(); $log->close();

        $msg = "Attendance saved — $saved records.";
        if ($newly_flagged > 0) $msg .= " 🎓 $newly_flagged trainee(s) newly eligible!";

        echo json_encode(['success' => true, 'message' => $msg, 'newly_flagged' => $newly_flagged]);
        break;

    // ── Issue Certificate ─────────────────────────────────────
    case 'issue_certificate':
        $user_id     = (int)($input['user_id']     ?? 0);
        $workshop_id = (int)($input['workshop_id'] ?? 0);

        if (!$user_id || !$workshop_id) {
            echo json_encode(['success' => false, 'message' => 'User and workshop required.']);
            break;
        }

        $r    = $conn->query("SELECT COUNT(*)+1 AS next FROM certificates WHERE status='issued'");
        $next = (int)$r->fetch_assoc()['next'];
        $cert_no = '#UGAT-' . date('Y') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare('INSERT INTO certificates (user_id, workshop_id, certificate_number, issued_at, status) VALUES (?,?,?,NOW(),"issued") ON DUPLICATE KEY UPDATE certificate_number=VALUES(certificate_number), issued_at=NOW(), status="issued"');
        $stmt->bind_param('iis', $user_id, $workshop_id, $cert_no);
        if ($stmt->execute()) {
            $log = $conn->prepare('INSERT INTO activity_log (action, color) VALUES (?,?)');
            $msg = "Certificate $cert_no issued"; $col = '#4B8423';
            $log->bind_param('ss', $msg, $col); $log->execute(); $log->close();
            echo json_encode(['success' => true, 'message' => "$cert_no issued!", 'cert_no' => $cert_no]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to issue certificate.']);
        }
        $stmt->close();
        break;

    // ── Add Trainee ───────────────────────────────────────────
    case 'add_trainee':
        $first_name   = trim($input['first_name']    ?? '');
        $last_name    = trim($input['last_name']     ?? '');
        $middle_name  = trim($input['middle_name']   ?? '');
        $email        = trim(strtolower($input['email'] ?? ''));
        $phone        = trim($input['phone']         ?? '');
        $address      = trim($input['address']       ?? '');
        $workshop_ids = $input['workshop_ids']       ?? []; // array of ints

        if (!$first_name || !$last_name || !$email) {
            echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required.']);
            break;
        }

        // Validate phone format
        $phone_clean = preg_replace('/[\s\-]/', '', $phone);
        if (substr($phone_clean, 0, 3) === '+63') $phone_clean = '0' . substr($phone_clean, 3);
        elseif (substr($phone_clean, 0, 2) === '63') $phone_clean = '0' . substr($phone_clean, 2);

        if (!empty($phone) && !preg_match('/^09\d{9}$/', $phone_clean)) {
            echo json_encode(['success' => false, 'message' => 'Invalid phone number. Must be a valid Philippine mobile number (09XX XXX XXXX).']);
            break;
        }

        // Check duplicate email
        $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email); $check->execute(); $check->store_result();
        if ($check->num_rows > 0) {
            $check->close();
            echo json_encode(['success' => false, 'message' => 'Email already registered.']);
            break;
        }
        $check->close();

        // Generate temporary password
        $chars    = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $tmp_pass = '';
        for ($i = 0; $i < 8; $i++) $tmp_pass .= $chars[random_int(0, strlen($chars) - 1)];
        $tmp_pass = 'U' . $tmp_pass . '!'; // e.g. UaBcD3f7!

        $hash = password_hash($tmp_pass, PASSWORD_BCRYPT);

        // Create user account
        $stmt = $conn->prepare('INSERT INTO users (email, password_hash, role, is_active) VALUES (?, ?, "trainee", 1)');
        $stmt->bind_param('ss', $email, $hash);
        $stmt->execute();
        $user_id = $conn->insert_id;
        $stmt->close();

        // Create trainee profile
// Read the individual address components sent from the form
$middle_name   = trim($input['middle_name']   ?? '');
$region_name   = trim($input['region_name']   ?? '');
$province_name = trim($input['province_name'] ?? '');
$city_name     = trim($input['city_name']     ?? '');

$stmt = $conn->prepare('
    INSERT INTO trainee_profiles 
        (user_id, first_name, last_name, middle_name, phone, address, region, province, city) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
');
$stmt->bind_param('issssssss', 
    $user_id, $first_name, $last_name, $middle_name, 
    $phone, $address, $region_name, $province_name, $city_name
);
$stmt->execute();
$stmt->close();

        // Enroll in all selected workshops
        $enrolled_workshops = [];
        foreach ($workshop_ids as $wid) {
            $wid = (int)$wid;
            if (!$wid) continue;
            $stmt = $conn->prepare('INSERT IGNORE INTO enrollments (user_id, workshop_id, status) VALUES (?, ?, "enrolled")');
            $stmt->bind_param('ii', $user_id, $wid);
            $stmt->execute();
            $stmt->close();
            // Get workshop title for SMS
            $wr = $conn->query("SELECT title FROM workshops WHERE id = $wid LIMIT 1");
            if ($wr) $enrolled_workshops[] = $wr->fetch_assoc()['title'];
        }

        // Send SMS with temporary password
        $sms_result   = null;
        $sms_sent     = false;
        $sms_error    = null;

        if (!empty($phone)) {
            $ws_list = !empty($enrolled_workshops) ? implode(', ', $enrolled_workshops) : 'a workshop';
            $sms_msg = "Hi $first_name! You've been enrolled in $ws_list at UGAT Integrated Farm. "
                     . "Your temporary login password is: $tmp_pass "
                     . "Please change your password after logging in. — UGAT TrainTrack";

            $sms_result = sendSemaphoreSMS($conn, $phone, $sms_msg);
            $sms_sent   = $sms_result['success'];
            $sms_error  = $sms_result['error'] ?? null;
        }

        // Log activity
        $log = $conn->prepare('INSERT INTO activity_log (action, color) VALUES (?, ?)');
        $msg = "New trainee added: $first_name $last_name"; $col = '#4B8423';
        $log->bind_param('ss', $msg, $col); $log->execute(); $log->close();

        // Build response
        $ws_count = count($enrolled_workshops);
        $response = [
            'success'      => true,
            'message'      => "$first_name $last_name added and enrolled in $ws_count workshop(s).",
            'tmp_password' => $tmp_pass,
            'sms_sent'     => $sms_sent,
        ];

        if ($sms_sent) {
            $response['message'] .= " Temporary password sent via SMS to $phone.";
        } elseif (!empty($phone)) {
            $response['sms_error'] = $sms_error;
            $response['message']  .= " Note: SMS could not be sent ($sms_error). Temp password: $tmp_pass";
        } else {
            $response['message'] .= " No phone number provided — SMS not sent. Temp password: $tmp_pass";
        }

        echo json_encode($response);
        break;

    // ── Delete Trainee ────────────────────────────────────────
    case 'delete_trainee':
        $user_id = (int)($input['user_id'] ?? 0);
        if (!$user_id) { echo json_encode(['success' => false, 'message' => 'User ID required.']); break; }

        $stmt = $conn->prepare('DELETE FROM users WHERE id = ? AND role = "trainee"');
        $stmt->bind_param('i', $user_id);
        echo $stmt->execute()
            ? json_encode(['success' => true,  'message' => 'Trainee removed.'])
            : json_encode(['success' => false, 'message' => 'Delete failed.']);
        $stmt->close();
        break;

    // ── Retroactive eligibility recheck ──────────────────────────
    // Bypasses settings flags — directly inserts 'eligible' certificates
    // for any enrolled trainee with at least 1 present/late attendance
    // who doesn't already have a certificate record.
    case 'recheck_eligibility':
        $conn->query("
            INSERT INTO certificates (user_id, workshop_id, status, attendance_rate, created_at)
            SELECT
                e.user_id,
                e.workshop_id,
                'eligible',
                ROUND(SUM(a.status IN ('present','late')) / NULLIF(COUNT(DISTINCT ws.id), 0) * 100, 1),
                NOW()
            FROM enrollments e
            JOIN workshop_sessions ws ON ws.workshop_id = e.workshop_id
            LEFT JOIN attendance a    ON a.session_id = ws.id AND a.user_id = e.user_id
            WHERE e.status IN ('enrolled','completed')
              AND ws.session_date <= CURDATE()
              AND NOT EXISTS (
                  SELECT 1 FROM certificates c
                  WHERE c.user_id = e.user_id AND c.workshop_id = e.workshop_id
              )
            GROUP BY e.user_id, e.workshop_id
            HAVING SUM(a.status IN ('present','late')) >= 1
        ");
        echo json_encode(['success' => true, 'newly_flagged' => $conn->affected_rows]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();