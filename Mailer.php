<?php
declare(strict_types=1);

function sendOtpEmail(string $recipient, int $otp, string $purpose = 'verification'): array
{
  $from = getenv('DUNKHOME_MAIL_FROM') ?: 'no-reply@dunkhome-kicks.local';
  $from = preg_replace('/[\r\n]+/', '', $from);
  $isReset = $purpose === 'reset';
  $isPasswordChange = $purpose === 'change_password';
  $subject = $isReset
    ? 'DunkHome Kicks password reset code'
    : ($isPasswordChange ? 'DunkHome Kicks password change verification code' : 'Your DunkHome Kicks verification code');
  $heading = $isReset ? 'Reset your password' : ($isPasswordChange ? 'Confirm your password change' : 'Verify your email');
  $intro = $isReset
    ? 'Use this one-time code to reset your DunkHome Kicks password.'
    : ($isPasswordChange ? 'Use this one-time code to verify your request to change your DunkHome Kicks admin password.' : 'Use this one-time code to complete your DunkHome Kicks account verification.');
  $plainMessage = "Hello,\n\n{$intro}\n\nYour verification code is: {$otp}\n\nThis code expires in 10 minutes. If you did not request it, you can ignore this email.\n\nDunkHome Kicks";
  $htmlMessage = '<!doctype html><html lang="en"><body style="margin:0;padding:0;background:#07100d;color:#f4f8f5;font-family:Arial,Helvetica,sans-serif">'
    . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#07100d;padding:36px 14px"><tr><td align="center">'
    . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#0d1b15;border:1px solid #20372b;border-radius:18px;overflow:hidden">'
    . '<tr><td style="padding:30px 32px 10px;text-align:center"><div style="font-size:13px;font-weight:bold;letter-spacing:2px;color:#f4f8f5">DUNKHOME <span style="color:#79e6aa">KICKS</span></div>'
    . '<div style="margin-top:28px;color:#79e6aa;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase">ACCOUNT SECURITY</div>'
    . '<h1 style="margin:10px 0;color:#f4f8f5;font-family:Georgia,serif;font-size:28px;font-weight:normal">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h1>'
    . '<p style="margin:0 auto;max-width:400px;color:#a8b8ae;font-size:14px;line-height:1.7">' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p></td></tr>'
    . '<tr><td align="center" style="padding:26px 32px"><div style="display:inline-block;padding:17px 28px;border:1px solid #31543f;border-radius:12px;background:#10231a;color:#9af0bc;font-size:32px;font-weight:bold;letter-spacing:9px">' . $otp . '</div>'
    . '<p style="margin:16px 0 0;color:#92a398;font-size:12px">This code expires in <strong style="color:#dce8e0">10 minutes</strong>.</p></td></tr>'
    . '<tr><td style="padding:0 32px 28px;text-align:center;color:#74857a;font-size:12px;line-height:1.7">If you did not request this code, ignore this email.<br><span style="color:#d1ddd5">DunkHome Kicks</span></td></tr>'
    . '</table></td></tr></table></body></html>';

  $boundary = 'dhk-' . bin2hex(random_bytes(16));
  $headers = implode("\r\n", [
    'From: ' . $from,
    'Reply-To: ' . $from,
    'MIME-Version: 1.0',
    'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    'X-Mailer: DunkHome Kicks',
  ]);
  $body = '--' . $boundary . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
    . $plainMessage . "\r\n\r\n--" . $boundary . "\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
    . $htmlMessage . "\r\n\r\n--" . $boundary . '--';

  if (!mail($recipient, $subject, $body, $headers)) {
    error_log('DunkHome OTP email failed for ' . $recipient);
    return [
      'status' => 'error',
      'message' => 'We could not send the OTP. Check PHP mail/SMTP configuration.',
    ];
  }

  return ['status' => 'success', 'message' => 'OTP sent successfully.'];
}

function dunkhomeAbsoluteUrl(string $path): string
{
  $publicUrl = trim((string) getenv('DUNKHOME_PUBLIC_URL'));
  if ($publicUrl !== '') {
    $parts = parse_url($publicUrl);
    if (is_array($parts)
      && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
      && !empty($parts['host'])
      && !isset($parts['user'])
      && !isset($parts['pass'])
      && !isset($parts['query'])
      && !isset($parts['fragment'])) {
      return rtrim($publicUrl, '/') . '/' . ltrim($path, '/');
    }
    error_log('DunkHome public URL configuration is invalid.');
  }

  $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
  if (!preg_match('/^(?:[a-z0-9.-]+|\[[a-f0-9:]+\])(?::[0-9]{1,5})?$/i', $host)) {
    return appUrl($path);
  }

  $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
  return $scheme . '://' . $host . appUrl($path);
}

function sendDunkHomeOrderEmail(string $recipient, string $subject, string $plainBody, string $htmlBody): array
{
  if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    error_log('DunkHome order email skipped because the recipient address is invalid.');
    return ['status' => 'error', 'message' => 'Recipient email address is invalid.'];
  }

  $from = trim((string) (getenv('DUNKHOME_MAIL_FROM') ?: 'no-reply@dunkhome-kicks.local'));
  if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) {
    error_log('DunkHome order email skipped because the sender address is invalid.');
    return ['status' => 'error', 'message' => 'Sender email is not configured correctly.'];
  }

  $boundary = 'dhk-order-' . bin2hex(random_bytes(16));
  $headers = implode("\r\n", [
    'From: ' . $from,
    'Reply-To: ' . $from,
    'MIME-Version: 1.0',
    'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    'X-Mailer: DunkHome Kicks',
  ]);
  $body = '--' . $boundary . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
    . $plainBody . "\r\n\r\n--" . $boundary . "\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
    . $htmlBody . "\r\n\r\n--" . $boundary . '--';

  if (!mail($recipient, $subject, $body, $headers)) {
    error_log('DunkHome order email delivery failed.');
    return ['status' => 'error', 'message' => 'PHP mail could not accept the message.'];
  }

  return ['status' => 'success', 'message' => 'Email accepted by the configured mail service.'];
}

function sendDunkHomeContactEmail(string $name, string $email, string $message): array
{
  $recipient = 'theradiramachandran@gmail.com';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $name)) {
    error_log('DunkHome contact message rejected because the submitted sender details are invalid.');
    return ['status' => 'error', 'message' => 'Enter a valid name and email address.'];
  }

  $from = trim((string) (getenv('DUNKHOME_MAIL_FROM') ?: 'no-reply@dunkhome-kicks.local'));
  if (!filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $from)) {
    error_log('DunkHome contact message skipped because the sender address is invalid.');
    return ['status' => 'error', 'message' => 'Email sending is not configured correctly. Please call us instead.'];
  }

  $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
  $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
  $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
  $subject = 'DunkHome Kicks contact message';
  $plainBody = "A customer sent a message through the DunkHome Kicks contact form.\n\n"
    . "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}";
  $htmlBody = '<!doctype html><html lang="en"><body style="margin:0;padding:28px;background:#07100d;color:#f4f8f5;font-family:Arial,Helvetica,sans-serif">'
    . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;margin:auto;padding:26px;border:1px solid #294637;border-radius:18px;background:#0d1b15">'
    . '<tr><td><p style="color:#79e6aa;font-size:11px;font-weight:bold;letter-spacing:2px">DUNKHOME KICKS · CONTACT</p>'
    . '<h1 style="font-family:Georgia,serif;font-size:28px;font-weight:normal">A new customer message</h1>'
    . '<p style="color:#b7c7bd;line-height:1.7"><strong style="color:#f4f8f5">Name:</strong> ' . $safeName . '<br><strong style="color:#f4f8f5">Email:</strong> ' . $safeEmail . '</p>'
    . '<div style="margin-top:18px;padding:18px;border:1px solid #294637;border-radius:12px;background:#10231a;color:#d9e5dd;line-height:1.7">' . nl2br($safeMessage) . '</div>'
    . '</td></tr></table></body></html>';
  $boundary = 'dhk-contact-' . bin2hex(random_bytes(16));
  $headers = implode("\r\n", [
    'From: ' . $from,
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    'X-Mailer: DunkHome Kicks',
  ]);
  $body = '--' . $boundary . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
    . $plainBody . "\r\n\r\n--" . $boundary . "\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
    . $htmlBody . "\r\n\r\n--" . $boundary . '--';

  if (!mail($recipient, $subject, $body, $headers)) {
    error_log('DunkHome contact message could not be accepted by the configured mail service.');
    return ['status' => 'error', 'message' => 'We could not send your message right now. Please call us or try again later.'];
  }

  return ['status' => 'success', 'message' => 'Thanks for reaching out. Your message has been sent to our team.'];
}

function sendDunkHomeOrderStatusEmail(array $order, string $previousStatus, string $newStatus, string $note = ''): array
{
  $orderCode = (string) ($order['order_code'] ?? '');
  $customerName = (string) ($order['customer_name'] ?? 'Customer');
  $recipient = (string) ($order['email'] ?? $order['customer_email'] ?? '');
  $trackingUrl = dunkhomeAbsoluteUrl('User/TrackOrder.php?code=' . rawurlencode($orderCode));
  $plainBody = "Hello {$customerName},\n\n"
    . "The status of your DunkHome Kicks booking {$orderCode} has changed from {$previousStatus} to {$newStatus}.\n"
    . ($note !== '' ? "\nUpdate from the store: {$note}\n" : '')
    . "\nTrack your booking: {$trackingUrl}\n\nDunkHome Kicks";
  $safeCustomerName = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
  $safeOrderCode = htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8');
  $safePreviousStatus = htmlspecialchars($previousStatus, ENT_QUOTES, 'UTF-8');
  $safeNewStatus = htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8');
  $safeNote = htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
  $safeTrackingUrl = htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8');
  $noteMarkup = $note !== ''
    ? '<p style="margin:18px 0 0;padding:14px 16px;border:1px solid #294637;border-radius:12px;background:#10231a;color:#c5d5ca;line-height:1.6"><strong style="color:#f4f8f5">Store update:</strong><br>' . nl2br($safeNote) . '</p>'
    : '';
  $htmlBody = '<!doctype html><html lang="en"><body style="margin:0;padding:0;background:#07100d;color:#f4f8f5;font-family:Arial,Helvetica,sans-serif">'
    . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#07100d;padding:36px 14px"><tr><td align="center">'
    . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#0d1b15;border:1px solid #20372b;border-radius:18px">'
    . '<tr><td style="padding:30px 32px;text-align:left"><div style="font-size:13px;font-weight:bold;letter-spacing:2px">DUNKHOME <span style="color:#79e6aa">KICKS</span></div>'
    . '<div style="margin-top:28px;color:#79e6aa;font-size:11px;font-weight:bold;letter-spacing:2px">BOOKING UPDATE</div>'
    . '<h1 style="margin:10px 0;color:#f4f8f5;font-family:Georgia,serif;font-size:28px;font-weight:normal">Your booking status changed</h1>'
    . '<p style="color:#a8b8ae;font-size:14px;line-height:1.7">Hello ' . $safeCustomerName . ', the status of booking <strong style="color:#f4f8f5">' . $safeOrderCode . '</strong> has been updated.</p>'
    . '<div style="margin-top:20px;padding:18px;border:1px solid #294637;border-radius:12px;background:#10231a"><span style="color:#a8b8ae">' . $safePreviousStatus . '</span><span style="padding:0 10px;color:#79e6aa">&rarr;</span><strong style="color:#9af0bc">' . $safeNewStatus . '</strong></div>'
    . $noteMarkup
    . '<p style="margin:24px 0"><a href="' . $safeTrackingUrl . '" style="display:inline-block;padding:13px 19px;border-radius:999px;background:#79e6aa;color:#07100d;font-weight:bold;text-decoration:none">Track your booking</a></p>'
    . '<p style="margin:24px 0 0;color:#74857a;font-size:12px;line-height:1.7">DunkHome Kicks</p></td></tr></table></td></tr></table></body></html>';

  return sendDunkHomeOrderEmail(
    $recipient,
    'Booking ' . $orderCode . ' status changed to ' . $newStatus,
    $plainBody,
    $htmlBody
  );
}

function sendDunkHomeOrderWhatsApp(array $orderDetails): array
{
  $accessToken = trim((string) getenv('DUNKHOME_WHATSAPP_ACCESS_TOKEN'));
  $phoneNumberId = trim((string) getenv('DUNKHOME_WHATSAPP_PHONE_NUMBER_ID'));
  $apiVersion = trim((string) getenv('DUNKHOME_WHATSAPP_API_VERSION'));
  $templateName = trim((string) (getenv('DUNKHOME_WHATSAPP_ORDER_TEMPLATE') ?: 'dunkhome_new_order'));
  $templateLanguage = trim((string) (getenv('DUNKHOME_WHATSAPP_ORDER_TEMPLATE_LANGUAGE') ?: 'en_US'));
  $recipient = preg_replace('/\D+/', '', (string) (getenv('DUNKHOME_ORDER_WHATSAPP_TO') ?: '919003341515'));

  if ($accessToken === '' || $phoneNumberId === '' || $apiVersion === '') {
    return [
      'status' => 'not_configured',
      'message' => 'Automatic WhatsApp delivery is not configured. Use the manual WhatsApp link below.',
    ];
  }
  if (!extension_loaded('curl') || !preg_match('/^v[0-9]+\.[0-9]+$/', $apiVersion)
    || !ctype_digit($phoneNumberId) || !ctype_digit((string) $recipient)
    || !preg_match('/^[a-z0-9_]+$/i', $templateName)
    || !preg_match('/^[a-z]{2}_[A-Z]{2}$/', $templateLanguage)) {
    error_log('DunkHome WhatsApp delivery configuration or cURL support is invalid.');
    return ['status' => 'error', 'message' => 'WhatsApp delivery is not configured correctly.'];
  }

  $parameters = [];
  foreach (['order_code', 'customer_name', 'mobile', 'total', 'tracking_url'] as $key) {
    $parameters[] = ['type' => 'text', 'text' => (string) ($orderDetails[$key] ?? '')];
  }
  $handle = curl_init('https://graph.facebook.com/' . $apiVersion . '/' . $phoneNumberId . '/messages');
  if ($handle === false) {
    error_log('DunkHome WhatsApp request could not be initialized.');
    return ['status' => 'error', 'message' => 'WhatsApp message could not be initialized.'];
  }

  $payload = json_encode([
    'messaging_product' => 'whatsapp',
    'to' => $recipient,
    'type' => 'template',
    'template' => [
      'name' => $templateName,
      'language' => ['code' => $templateLanguage],
      'components' => [[
        'type' => 'body',
        'parameters' => $parameters,
      ]],
    ],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
  curl_setopt_array($handle, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $accessToken,
      'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 12,
  ]);

  $response = curl_exec($handle);
  $httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
  $curlError = curl_error($handle);
  curl_close($handle);

  $decodedResponse = is_string($response) ? json_decode($response, true) : null;
  if ($response === false || $httpStatus < 200 || $httpStatus >= 300 || empty($decodedResponse['messages'][0]['id'])) {
    error_log('DunkHome WhatsApp delivery failed. HTTP status: ' . $httpStatus . ($curlError !== '' ? '; transport error: ' . $curlError : ''));
    return [
      'status' => 'error',
      'message' => 'WhatsApp delivery failed. Check the WhatsApp Business API configuration; use the manual link below.',
    ];
  }

  return ['status' => 'success', 'message' => 'Order notification accepted by WhatsApp.'];
}
