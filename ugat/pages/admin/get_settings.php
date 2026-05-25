<?php
// ============================================================
//  admin/get_settings.php
//  Returns settings and admin users as JSON
//  Actions: all, admin_users
// ============================================================
session_name('ugat_admin');
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$action = $_GET['action'] ?? 'all';

switch ($action) {

    // ── All settings key-value pairs ──────────────────────────
    case 'all':
        $r = $conn->query("SELECT `key`, `value` FROM settings ORDER BY id ASC");
        $settings = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            $settings[$row['key']] = $row['value'];
        }
        echo json_encode(['success' => true, 'settings' => $settings]);
        break;

    // ── Admin users ───────────────────────────────────────────
    case 'admin_users':
        $has_tp = $conn->query("SHOW TABLES LIKE 'trainee_profiles'")->num_rows > 0;
        $has_ap = $conn->query("SHOW TABLES LIKE 'admin_profiles'")->num_rows > 0;

        // Try admin_profiles first, then trainee_profiles, then email prefix
        if ($has_ap) {
            $name_sel = "COALESCE(CONCAT(ap.first_name,' ',ap.last_name), SUBSTRING_INDEX(u.email,'@',1))";
            $join     = "LEFT JOIN admin_profiles ap ON ap.user_id = u.id";
        } elseif ($has_tp) {
            $name_sel = "COALESCE(CONCAT(tp.first_name,' ',tp.last_name), SUBSTRING_INDEX(u.email,'@',1))";
            $join     = "LEFT JOIN trainee_profiles tp ON tp.user_id = u.id";
        } else {
            $name_sel = "SUBSTRING_INDEX(u.email,'@',1)";
            $join     = "";
        }

        $r = $conn->query("
            SELECT u.id, u.email, u.role, u.is_active,
                   $name_sel AS name,
                   MAX(ll.attempted_at) AS last_login
            FROM users u
            $join
            LEFT JOIN login_logs ll ON ll.user_id = u.id AND ll.status = 'success'
            WHERE u.role = 'admin'
            GROUP BY u.id
            ORDER BY u.id ASC
        ");

        $users = [];
        if ($r) while ($row = $r->fetch_assoc()) {
            // Format last login
            $last = '—';
            if ($row['last_login']) {
                $ts   = strtotime($row['last_login']);
                $diff = time() - $ts;
                if ($diff < 86400)    $last = 'Today, '    . date('g:i A', $ts);
                elseif ($diff < 172800) $last = 'Yesterday, ' . date('g:i A', $ts);
                else                  $last = date('M j, Y', $ts);
            }
            $users[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'email'      => $row['email'],
                'role'       => ucfirst($row['role']),
                'is_active'  => (bool)$row['is_active'],
                'last_login' => $last,
            ];
        }
        echo json_encode(['success' => true, 'users' => $users]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}

$conn->close();