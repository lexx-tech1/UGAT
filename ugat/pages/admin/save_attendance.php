<?php
// ============================================================
//  admin/save_attendance.php
//  Actions: save_attendance, bulk_save
//  After every save, auto-checks eligibility from settings.
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

$input   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action  = $input['action'] ?? '';
$user_id = (int)$_SESSION['user_id'];

// ── Load eligibility settings ─────────────────────────────────
function getSettings($conn) {
    $r = $conn->query("SELECT `key`, `value` FROM settings WHERE `key` IN
        ('cert_threshold','cert_min_sessions','cert_auto_flag')");
    $s = ['cert_threshold' => 67, 'cert_min_sessions' => 2, 'cert_auto_flag' => '0'];
    if ($r) while ($row = $r->fetch_assoc()) $s[$row['key']] = $row['value'];
    return $s;
}

// ── Auto-flag eligible trainees ───────────────────────────────
// Runs after any attendance save.
// Checks all enrollments for the affected workshop and flags
// trainees who meet the threshold as 'eligible' in certificates.
function checkEligibility($conn, $workshop_id) {
    $settings = getSettings($conn);

    // Only run if auto-flag is enabled
    if ($settings['cert_auto_flag'] !== '1') return 0;

    $threshold    = (float)$settings['cert_threshold'];    // e.g. 67 (%)
    $min_sessions = (int)$settings['cert_min_sessions'];   // e.g. 2

    $wid = (int)$workshop_id;

    // Get total sessions in this workshop
    $r = $conn->query("SELECT COUNT(*) AS n FROM workshop_sessions WHERE workshop_id = $wid");
    $total_sessions = (int)$r->fetch_assoc()['n'];
    if ($total_sessions === 0) return 0;

    // Find all enrolled trainees for this workshop with attendance data
    $r = $conn->query("
        SELECT
            e.user_id,
            e.workshop_id,
            COUNT(a.id)                              AS total_att,
            SUM(a.status IN ('present','late'))      AS attended
        FROM enrollments e
        JOIN workshop_sessions ws ON ws.workshop_id = e.workshop_id
        LEFT JOIN attendance a ON a.session_id = ws.id
                               AND a.user_id = e.user_id
                               AND a.status != 'upcoming'
        WHERE e.workshop_id = $wid
        GROUP BY e.user_id, e.workshop_id
    ");

    $newly_flagged = 0;

    if (!$r) return 0;

    while ($row = $r->fetch_assoc()) {
        $total   = (int)$row['total_att'];
        $attended = (int)$row['attended'];

        if ($total === 0) continue;

        $rate = ($attended / $total) * 100;

        // Check if meets threshold
        if ($rate >= $threshold && $attended >= $min_sessions) {
            $uid = (int)$row['user_id'];
            $wid2 = (int)$row['workshop_id'];
            $rate_rounded = round($rate, 1);

            // Check if certificate record already exists
            $check = $conn->query("
                SELECT id, status FROM certificates
                WHERE user_id = $uid AND workshop_id = $wid2 LIMIT 1
            ");
            $existing = $check ? $check->fetch_assoc() : null;

            if (!$existing) {
                // Insert new eligible certificate record
                $stmt = $conn->prepare(
                    "INSERT INTO certificates (user_id, workshop_id, status, attendance_rate, created_at)
                     VALUES (?, ?, 'eligible', ?, NOW())"
                );
                $stmt->bind_param('iid', $uid, $wid2, $rate_rounded);
                $stmt->execute();
                $stmt->close();
                $newly_flagged++;
            } elseif ($existing['status'] === 'eligible') {
                // Update attendance rate if already eligible
                $stmt = $conn->prepare(
                    "UPDATE certificates SET attendance_rate = ? WHERE user_id = ? AND workshop_id = ?"
                );
                $stmt->bind_param('dii', $rate_rounded, $uid, $wid2);
                $stmt->execute();
                $stmt->close();
            }
            // Don't touch 'issued' records
        }
    }

    return $newly_flagged;
}

switch ($action) {

    // ── Save single attendance record ─────────────────────────
    case 'save_attendance':
        $session_id  = (int)($input['session_id']  ?? 0);
        $trainee_id  = (int)($input['trainee_id']  ?? 0);
        $status      = trim($input['status']        ?? 'present');
        $notes       = trim($input['notes']         ?? '');

        if (!$session_id || !$trainee_id) {
            echo json_encode(['success' => false, 'message' => 'Missing session or trainee ID.']);
            break;
        }

        $valid = ['present', 'late', 'absent'];
        if (!in_array($status, $valid)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
            break;
        }

        // Enforce: attendance can only be marked on or after the session date
        $dateChk = $conn->prepare("SELECT session_date FROM workshop_sessions WHERE id = ? LIMIT 1");
        $dateChk->bind_param('i', $session_id);
        $dateChk->execute();
        $dateRow = $dateChk->get_result()->fetch_assoc();
        $dateChk->close();
        if ($dateRow && $dateRow['session_date']) {
            $sessionDate = new DateTime($dateRow['session_date']);
            $today       = new DateTime('today');
            if ($sessionDate > $today) {
                echo json_encode(['success' => false, 'message' => 'Attendance can only be marked on or after the session date (' . $sessionDate->format('M j, Y') . ').']);
                break;
            }
        }

        // Upsert attendance record
        $stmt = $conn->prepare("
            INSERT INTO attendance (session_id, user_id, status, recorded_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                recorded_by = VALUES(recorded_by)
        ");
        $stmt->bind_param('iisi', $session_id, $trainee_id, $status, $user_id);

        if ($stmt->execute()) {
            $stmt->close();

            // Get workshop_id for this session
            $ws = $conn->query("SELECT workshop_id FROM workshop_sessions WHERE id = $session_id LIMIT 1");
            $ws_row = $ws ? $ws->fetch_assoc() : null;
            $newly_flagged = $ws_row ? checkEligibility($conn, $ws_row['workshop_id']) : 0;

            $msg = 'Attendance saved.';
            if ($newly_flagged > 0) {
                $msg .= " $newly_flagged trainee(s) newly flagged as eligible for certification!";
            }

            echo json_encode([
                'success'        => true,
                'message'        => $msg,
                'newly_flagged'  => $newly_flagged,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $conn->error]);
        }
        break;

    // ── Bulk save (entire session's attendance at once) ───────
    // Input: { action, session_id, records: [{trainee_id, status, notes}] }
    case 'bulk_save':
        $session_id = (int)($input['session_id'] ?? 0);
        $records    = $input['records'] ?? [];

        if (!$session_id || !is_array($records) || empty($records)) {
            echo json_encode(['success' => false, 'message' => 'Missing session ID or records.']);
            break;
        }

        // Enforce: attendance can only be marked on or after the session date
        $bdateChk = $conn->prepare("SELECT session_date FROM workshop_sessions WHERE id = ? LIMIT 1");
        $bdateChk->bind_param('i', $session_id);
        $bdateChk->execute();
        $bdateRow = $bdateChk->get_result()->fetch_assoc();
        $bdateChk->close();
        if ($bdateRow && $bdateRow['session_date']) {
            $bSessionDate = new DateTime($bdateRow['session_date']);
            $bToday       = new DateTime('today');
            if ($bSessionDate > $bToday) {
                echo json_encode(['success' => false, 'message' => 'Attendance can only be marked on or after the session date (' . $bSessionDate->format('M j, Y') . ').']);
                break;
            }
        }

        $saved   = 0;
        $errors  = 0;
        $valid   = ['present', 'late', 'absent'];

        foreach ($records as $rec) {
            $trainee_id = (int)($rec['trainee_id'] ?? 0);
            $status     = trim($rec['status']       ?? 'present');
            $notes      = trim($rec['notes']        ?? '');

            if (!$trainee_id || !in_array($status, $valid)) { $errors++; continue; }

            $stmt = $conn->prepare("
                INSERT INTO attendance (session_id, user_id, status, recorded_by)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    recorded_by = VALUES(recorded_by)
            ");
            $stmt->bind_param('iisi', $session_id, $trainee_id, $status, $user_id);
            if ($stmt->execute()) $saved++;
            else $errors++;
            $stmt->close();
        }

        // Get workshop_id and check eligibility once for the whole session
        $ws = $conn->query("SELECT workshop_id FROM workshop_sessions WHERE id = $session_id LIMIT 1");
        $ws_row = $ws ? $ws->fetch_assoc() : null;
        $newly_flagged = $ws_row ? checkEligibility($conn, $ws_row['workshop_id']) : 0;

        $msg = "Saved $saved record(s).";
        if ($errors)         $msg .= " $errors failed.";
        if ($newly_flagged)  $msg .= " 🎓 $newly_flagged trainee(s) newly eligible for certification!";

        echo json_encode([
            'success'       => $saved > 0,
            'message'       => $msg,
            'saved'         => $saved,
            'errors'        => $errors,
            'newly_flagged' => $newly_flagged,
        ]);
        break;

    // ── Manual eligibility check (run on demand) ─────────────
    case 'check_eligibility':
        $workshop_id = (int)($input['workshop_id'] ?? 0);

        if (!$workshop_id) {
            // Check all workshops
            $ws_list = $conn->query("SELECT id FROM workshops");
            $total_flagged = 0;
            if ($ws_list) while ($row = $ws_list->fetch_assoc()) {
                $total_flagged += checkEligibility($conn, $row['id']);
            }
            echo json_encode([
                'success' => true,
                'message' => "Eligibility check complete. $total_flagged trainee(s) newly flagged.",
                'newly_flagged' => $total_flagged,
            ]);
        } else {
            $flagged = checkEligibility($conn, $workshop_id);
            echo json_encode([
                'success' => true,
                'message' => "Check complete. $flagged trainee(s) newly flagged as eligible.",
                'newly_flagged' => $flagged,
            ]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();