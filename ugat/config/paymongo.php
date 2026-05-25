<?php
// PayMongo configuration
// Set these as environment variables in Railway:
//   PAYMONGO_SECRET_KEY=sk_test_xxxx
//   PAYMONGO_WEBHOOK_SECRET=whsk_xxxx
//   APP_URL=https://your-railway-url.up.railway.app/ugat

define('PAYMONGO_SECRET_KEY',    getenv('PAYMONGO_SECRET_KEY')    ?: '');
define('PAYMONGO_WEBHOOK_SECRET',getenv('PAYMONGO_WEBHOOK_SECRET') ?: '');
define('APP_URL',                rtrim(getenv('APP_URL')           ?: 'http://localhost', '/'));

/**
 * Make a request to the PayMongo API.
 * Returns ['code' => int, 'body' => array]
 */
function paymongo_request(string $method, string $endpoint, ?array $payload = null): array {
    $ch = curl_init('https://api.paymongo.com/v1' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
    }
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['code' => 0, 'body' => ['error' => $error]];
    }
    return ['code' => $httpCode, 'body' => json_decode($response, true) ?? []];
}

/**
 * Verify the X-PayMongo-Signature header from a webhook request.
 * Returns true if valid, false otherwise.
 */
function paymongo_verify_webhook(string $rawBody, string $signatureHeader): bool {
    // Header format: t=1234567890,te=test_sig,li=live_sig
    $parts = [];
    foreach (explode(',', $signatureHeader) as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
        $parts[trim($k)] = trim($v);
    }

    if (empty($parts['t'])) return false;

    $payload   = $parts['t'] . '.' . $rawBody;
    $computed  = hash_hmac('sha256', $payload, PAYMONGO_WEBHOOK_SECRET);

    // Check test signature (te) in test mode, live signature (li) in live mode
    $sigToCheck = str_starts_with(PAYMONGO_SECRET_KEY, 'sk_live') ? ($parts['li'] ?? '') : ($parts['te'] ?? '');

    return hash_equals($computed, $sigToCheck);
}
