<?php
// check_psgc.php — Diagnostic: shows real PSGC API field names
// Visit: http://localhost/UGAT/pages/admin/check_psgc.php
// DELETE after use.

ini_set('display_errors', 1);
ini_set('max_execution_time', 60);

$opts = [
    'http' => ['method'=>'GET','timeout'=>30,'header'=>"User-Agent: UGAT\r\n"],
    'ssl'  => ['verify_peer'=>false,'verify_peer_name'=>false],
];

// Fetch first 3 barangays only
$raw  = @file_get_contents('https://psgc.gitlab.io/api/barangays/', false, stream_context_create($opts));
$data = $raw ? json_decode($raw, true) : null;

echo '<pre style="font-family:monospace;font-size:13px;padding:2rem">';

if (!$data) {
    echo "❌ Could not reach PSGC API\n";
} else {
    echo "✅ Total barangays fetched: " . count($data) . "\n\n";

    echo "=== FIRST 5 BARANGAY RECORDS (raw keys + values) ===\n\n";
    foreach (array_slice($data, 0, 5) as $i => $b) {
        echo "--- Barangay #$i ---\n";
        foreach ($b as $key => $val) {
            $display = is_null($val) ? 'null' : (is_bool($val) ? ($val?'true':'false') : $val);
            echo "  $key => $display\n";
        }
        echo "\n";
    }

    // Also show a Daet barangay if found
    echo "=== SEARCHING FOR A DAET BARANGAY ===\n";
    foreach ($data as $b) {
        $name = strtolower($b['name'] ?? '');
        if (str_contains($name, 'bagasbas') || str_contains($name, 'lag-on') || str_contains($name, 'daet')) {
            echo "Found: " . print_r($b, true) . "\n";
            break;
        }
    }
}
echo '</pre>';