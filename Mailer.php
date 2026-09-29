<?php
declare(strict_types=1);

function sendOtpEmail(string $recipient, int $otp, string $purpose = 'verification'): array
{
  $from = getenv('DUNKHOME_MAIL_FROM') ?: 'no-reply@dunkhome-kicks.local';
  $from = preg_replace('/[\r\n]+/', '', $from);
  $isReset = $purpose === 'reset';
  $subject = $isReset
    ? 'DunkHome Kicks password reset code'
    : 'Your DunkHome Kicks verification code';
  $heading = $isReset ? 'Reset your password' : 'Verify your email';
  $intro = $isReset
    ? 'Use this one-time code to reset your DunkHome Kicks password.'
    : 'Use this one-time code to complete your DunkHome Kicks account verification.';
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
