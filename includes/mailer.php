<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($toEmail, $code) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'nextpickstore5@gmail.com';
        $mail->Password = 'kakhsyklncupwrfe'; // app password without spaces
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('nextpickstore5@gmail.com', 'NextPickStore');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'NextPick verification code';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <h2>Verify your email</h2>
                <p>Your verification code is:</p>
                <div style='font-size: 28px; font-weight: bold; color: #2155f5; letter-spacing: 4px;'>
                    {$code}
                </div>
                <p>This code expires in 60 minutes.</p>
            </div>
        ";
        $mail->AltBody = "Your verification code is: {$code}. This code expires in 60 minutes.";

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}