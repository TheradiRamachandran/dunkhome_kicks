<?php
declare(strict_types=1);
function sendOtpEmail(string $recipient,int $otp,string $purpose='verification'): array{
  $from=getenv('DUNKHOME_MAIL_FROM')?:'no-reply@dunkhome-kicks.local';
  $subject=$purpose==='reset'?'DunkHome Kicks password reset OTP':'Your DunkHome Kicks verification code';
  $message="Hello,\n\nYour DunkHome Kicks OTP is: {$otp}\n\nThis code expires in 10 minutes. If you did not request it, ignore this email.\n\nDunkHome Kicks\nLace Up. Live More.";
  $headers="From: {$from}\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Mailer: DunkHome Kicks";
  if(!mail($recipient,$subject,$message,$headers)){error_log('DunkHome OTP email failed for '.$recipient);return ['status'=>'error','message'=>'We could not send the OTP. Check PHP mail/SMTP configuration.'];}
  return ['status'=>'success','message'=>'OTP sent successfully.'];
}
