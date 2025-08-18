<?php
// Secure download endpoint: verifies token, serves PDF only to valid buyers

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function respond($code, $message) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if ($token === '') {
    respond(400, 'Missing token');
}

$dataDir = __DIR__ . '/../data';
$storeFile = $dataDir . '/tokens.json';

if (!file_exists($storeFile)) {
    respond(403, 'Invalid or expired token');
}

$json = @file_get_contents($storeFile);
$tokens = $json ? json_decode($json, true) : [];
if (!is_array($tokens)) { $tokens = []; }

// Validate token
if (!isset($tokens[$token])) {
    // Allow a fallback test token if configured via env
    $fallback = getenv('TEST_DOWNLOAD_TOKEN') ?: '';
    if ($fallback !== '' && hash_equals($fallback, $token) === true) {
        // ok
    } else {
        respond(403, 'Invalid or expired token');
    }
}

if (isset($tokens[$token])) {
    $record = $tokens[$token];
    $expiresAt = isset($record['expiresAt']) ? (int)$record['expiresAt'] : 0;
    if ($expiresAt > 0 && $expiresAt < time()) {
        respond(403, 'Token expired');
    }
}

// Resolve file path
$preferredFile = __DIR__ . '/../files/cafe-course.pdf';
$fallbackFile = __DIR__ . '/../files/START.pdf.pdf';
$filePath = file_exists($preferredFile) ? $preferredFile : $fallbackFile;

if (!file_exists($filePath)) {
    respond(404, 'File not found');
}

$fileName = basename($filePath);
$fileSize = filesize($filePath);

// Serve file
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Optional: mark token usage (one-time use)
if (isset($tokens[$token])) {
    $tokens[$token]['usedAt'] = time();
    // To enforce one-time: unset($tokens[$token]);
    @file_put_contents($storeFile, json_encode($tokens, JSON_PRETTY_PRINT));
}

// Output file
readfile($filePath);
exit;

