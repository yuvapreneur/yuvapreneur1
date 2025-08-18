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

            $filePath = __DIR__ . '/../files/START.pdf.pdf';
            if (file_exists($filePath)) {
                $content = chunk_split(base64_encode(file_get_contents($filePath)));
                $uid = md5(uniqid((string) time(), true));

                $headers  = "From: {$from}\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/mixed; boundary=\"{$uid}\"\r\n";

                $body  = "--{$uid}\r\n";
                $body .= "Content-Type: text/plain; charset=utf-8\r\n";
                $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $body .= $message . "\r\n\r\n";
                $body .= "--{$uid}\r\n";
                $body .= "Content-Type: application/pdf; name=\"START.pdf\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"START.pdf\"\r\n\r\n";
                $body .= $content . "\r\n\r\n";
                $body .= "--{$uid}--";

                @mail($to, $subject, $body, $headers);
            }
        }
    }
    http_response_code(200);
    echo 'OK';
    exit;
}

http_response_code(400);
echo 'Invalid signature';

