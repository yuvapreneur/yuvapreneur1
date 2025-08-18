<?php
// Razorpay webhook handler: payment.captured -> email PDF attachment

// Read raw POST body
$input = @file_get_contents('php://input');
$event = json_decode($input, true);

// Verify Razorpay signature
$secret = getenv('RAZORPAY_WEBHOOK_SECRET') ?: 'YOUR_RAZORPAY_WEBHOOK_SECRET';
$receivedSignature = isset($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']) ? $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] : '';
$expectedSignature = hash_hmac('sha256', $input, $secret);

if (hash_equals($expectedSignature, $receivedSignature)) {
    if (isset($event['event']) && $event['event'] === 'payment.captured') {
        $email = $event['payload']['payment']['entity']['email'] ?? null;

        if ($email) {
            $to = $email;
            $subject = 'Your Course PDF - Payment Successful';
            $message = "Hi, thank you for your payment! Please find your course PDF attached.";
            $from = getenv('MAIL_FROM') ?: 'support@yuvapreneur.in';

            // Generate signed token (simple HMAC) and store with expiry
            $secretKey = getenv('DOWNLOAD_TOKEN_SECRET') ?: bin2hex(random_bytes(16));
            $paymentId = $event['payload']['payment']['entity']['id'] ?? '';
            $issuedAt = time();
            $expiresAt = $issuedAt + (3600 * 24 * 7); // 7 days
            $raw = $paymentId . '|' . $email . '|' . $issuedAt;
            $sig = hash_hmac('sha256', $raw, $secretKey);
            $token = rtrim(strtr(base64_encode($raw . '|' . $sig), '+/', '-_'), '=');

            // Persist token -> file store
            $dataDir = __DIR__ . '/../data';
            if (!is_dir($dataDir)) { @mkdir($dataDir, 0775, true); }
            $storeFile = $dataDir . '/tokens.json';
            $tokens = [];
            if (file_exists($storeFile)) {
                $json = @file_get_contents($storeFile);
                $tokens = $json ? json_decode($json, true) : [];
                if (!is_array($tokens)) { $tokens = []; }
            }
            $tokens[$token] = [
                'email' => $email,
                'paymentId' => $paymentId,
                'issuedAt' => $issuedAt,
                'expiresAt' => $expiresAt
            ];
            @file_put_contents($storeFile, json_encode($tokens, JSON_PRETTY_PRINT));

            // Send link with token
            $headers  = "From: {$from}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
            $downloadUrl = (getenv('PUBLIC_BASE_URL') ?: 'https://yuvapreneur.in') . '/download?token=' . urlencode($token);
            $body = "Dear thanks for your payment.\n\nHere’s your course PDF: {$downloadUrl}\n\n";
            @mail($to, $subject, $body, $headers);
        }
    }
    http_response_code(200);
    echo 'OK';
    exit;
}

http_response_code(400);
echo 'Invalid signature';

