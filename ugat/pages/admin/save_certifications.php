<?php
// ============================================================
//  admin/save_certifications.php
//  Actions: issue_certificate, resend_sms
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

switch ($action) {

    // ── Issue Certificate ─────────────────────────────────────
    case 'issue_certificate':
        $cert_id    = (int)($input['cert_id']    ?? 0);
        $contact    = trim($input['contact']     ?? '');

        if (!$cert_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid certificate ID.']);
            break;
        }

        // Get cert record with workshop title
        $stmt = $conn->prepare("SELECT c.*, w.title AS workshop_title FROM certificates c JOIN workshops w ON c.workshop_id = w.id WHERE c.id = ? AND c.status = 'eligible' LIMIT 1");
        $stmt->bind_param('i', $cert_id);
        $stmt->execute();
        $cert = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cert) {
            echo json_encode(['success' => false, 'message' => 'Certificate not found or already issued.']);
            break;
        }

        // Generate unique certificate number
        $r    = $conn->query("SELECT COUNT(*)+1 AS next FROM certificates WHERE status='issued'");
        $next = (int)$r->fetch_assoc()['next'];
        $cert_no = '#UGAT-' . date('Y') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);

        // Ensure unique
        $check = $conn->prepare("SELECT id FROM certificates WHERE certificate_number = ? LIMIT 1");
        $check->bind_param('s', $cert_no); $check->execute(); $check->store_result();
        while ($check->num_rows > 0) {
            $check->close(); $next++;
            $cert_no = '#UGAT-' . date('Y') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
            $check = $conn->prepare("SELECT id FROM certificates WHERE certificate_number = ? LIMIT 1");
            $check->bind_param('s', $cert_no); $check->execute(); $check->store_result();
        }
        $check->close();

        // Calculate real attendance rate
        $rate_r = $conn->query("
            SELECT
                COUNT(a.id) AS total,
                SUM(a.status IN ('present','late')) AS attended
            FROM workshop_sessions ws
            LEFT JOIN attendance a ON a.session_id = ws.id AND a.user_id = {$cert['user_id']}
            WHERE ws.workshop_id = {$cert['workshop_id']}
        ");
        $rate_row = $rate_r ? $rate_r->fetch_assoc() : null;
        $att_rate = ($rate_row && $rate_row['total'] > 0)
            ? round(($rate_row['attended'] / $rate_row['total']) * 100, 1)
            : 0;

        // Update certificate: status → issued
        $has_sms = $conn->query("SHOW COLUMNS FROM certificates LIKE 'sms_sent'")->num_rows > 0;
        $sms_val = 0; // Will be updated after SMS send

        if ($has_sms) {
            $upd = $conn->prepare("
                UPDATE certificates
                SET status = 'issued',
                    certificate_number = ?,
                    issued_at = NOW(),
                    attendance_rate = ?,
                    sms_sent = 0
                WHERE id = ?
            ");
            $upd->bind_param('sdi', $cert_no, $att_rate, $cert_id);
        } else {
            $upd = $conn->prepare("
                UPDATE certificates
                SET status = 'issued',
                    certificate_number = ?,
                    issued_at = NOW(),
                    attendance_rate = ?
                WHERE id = ?
            ");
            $upd->bind_param('sdi', $cert_no, $att_rate, $cert_id);
        }

        if ($upd->execute()) {
            // Update trainee phone if provided
            if ($contact && $has_sms) {
                $has_tp = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;
                if ($has_tp) {
                    $ph = $conn->prepare("UPDATE trainee_profiles SET phone = ? WHERE user_id = ?");
                    $ph->bind_param('si', $contact, $cert['user_id']);
                    $ph->execute(); $ph->close();
                }
            }

            // Send SMS + Email notification to trainee
            try {
                require_once '../../config/sms_helpers.php';
                require_once '../../config/email_service.php';
                require_once '../../config/email.php';

                $workshop_title = $cert['workshop_title'] ?? 'the workshop';

                sendSmsForEvent('certification_issued', (int)$cert['user_id'], [
                    'workshop_name' => $workshop_title,
                ]);

                $uq = $conn->query(
                    "SELECT COALESCE(NULLIF(np.email,''), u.email) AS email,
                            CONCAT(COALESCE(tp.first_name,''), ' ', COALESCE(tp.last_name,'')) AS name
                     FROM users u
                     LEFT JOIN trainee_profiles tp ON tp.user_id = u.id
                     LEFT JOIN notification_preferences np ON np.user_id = u.id
                     WHERE u.id = {$cert['user_id']} LIMIT 1"
                );
                $uinfo = $uq ? $uq->fetch_assoc() : null;
                if ($uinfo && $uinfo['email']) {
                    $name     = trim($uinfo['name']) ?: 'Trainee';
                    $template = getEmailTemplate('certification_issued', [
                        'name'          => $name,
                        'workshop_name' => $workshop_title,
                        'link'          => 'http://localhost:8080/UGAT/pages/trainee/TraineeCertifications.html',
                    ]);
                    getEmailService($conn)->sendEmail($uinfo['email'], $template['subject'], $template['body']);
                }

                if ($has_sms) {
                    $conn->query("UPDATE certificates SET sms_sent = 1 WHERE id = $cert_id");
                    $sms_val = 1;
                }
            } catch (\Throwable $e) {
                error_log('Certificate notification error: ' . $e->getMessage());
            }

            echo json_encode([
                'success'    => true,
                'message'    => "Certificate $cert_no issued successfully!",
                'cert_no'    => $cert_no,
                'att_rate'   => $att_rate,
                'sms_sent'   => (bool)$sms_val,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to issue certificate: ' . $conn->error]);
        }
        $upd->close();
        break;

    // ── Resend SMS ────────────────────────────────────────────
    case 'resend_sms':
        $cert_id = (int)($input['cert_id'] ?? 0);

        if (!$cert_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid certificate ID.']);
            break;
        }

        $stmt = $conn->prepare("SELECT c.user_id, w.title AS workshop_title FROM certificates c JOIN workshops w ON c.workshop_id = w.id WHERE c.id = ? LIMIT 1");
        $stmt->bind_param('i', $cert_id);
        $stmt->execute();
        $cert_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cert_row) {
            echo json_encode(['success' => false, 'message' => 'Certificate not found.']);
            break;
        }

        try {
            require_once '../../config/sms_helpers.php';
            require_once '../../config/email_service.php';
            require_once '../../config/email.php';

            $workshop_title = $cert_row['workshop_title'] ?? 'the workshop';

            sendSmsForEvent('certification_issued', (int)$cert_row['user_id'], [
                'workshop_name' => $workshop_title,
            ]);

            $uq = $conn->query(
                "SELECT COALESCE(NULLIF(np.email,''), u.email) AS email,
                        CONCAT(COALESCE(tp.first_name,''), ' ', COALESCE(tp.last_name,'')) AS name
                 FROM users u
                 LEFT JOIN trainee_profiles tp ON tp.user_id = u.id
                 LEFT JOIN notification_preferences np ON np.user_id = u.id
                 WHERE u.id = {$cert_row['user_id']} LIMIT 1"
            );
            $uinfo = $uq ? $uq->fetch_assoc() : null;
            if ($uinfo && $uinfo['email']) {
                $name     = trim($uinfo['name']) ?: 'Trainee';
                $template = getEmailTemplate('certification_issued', [
                    'name'          => $name,
                    'workshop_name' => $workshop_title,
                    'link'          => 'http://localhost:8080/UGAT/pages/trainee/TraineeCertifications.html',
                ]);
                getEmailService($conn)->sendEmail($uinfo['email'], $template['subject'], $template['body']);
            }

            $has_sms = $conn->query("SHOW COLUMNS FROM certificates LIKE 'sms_sent'")->num_rows > 0;
            if ($has_sms) {
                $conn->query("UPDATE certificates SET sms_sent = 1 WHERE id = $cert_id");
            }

            echo json_encode(['success' => true, 'message' => 'Notification resent to trainee.']);
        } catch (\Throwable $e) {
            error_log('Resend notification error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to resend: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();