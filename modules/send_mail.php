<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Define paths to PHPMailer files
// You must download these from https://github.com/PHPMailer/PHPMailer/tree/master/src
// and place them in includes/PHPMailer/
require_once __DIR__ . '/../includes/PHPMailer/Exception.php';
require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';

function sendMail($to, $subject, $body, $cc_list = []) {
    $mail = new PHPMailer(true);

    try {
        // ============================================================
        // DEVELOPMENT CONFIGURATION (Mailpit/Localhost)
        // ============================================================
        $mail->isSMTP();
        $mail->Host       = 'localhost'; // Mailpit/Mailhog host
        $mail->SMTPAuth   = false;       // No auth for local testing
        $mail->Port       = 1025;        // Standard Mailpit port
        $mail->setFrom('noreply@hr-portal.local', 'HR Portal System');

        // ============================================================
        // PRODUCTION CONFIGURATION (Uncomment and fill to use)
        // ============================================================
        /*
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';                     // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                 // Enable SMTP authentication
        $mail->Username   = 'your-email@gmail.com';               // SMTP username
        $mail->Password   = 'your-app-password';                  // SMTP password (use App Password for Gmail)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;       // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = 587;                                  // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
        $mail->setFrom('your-email@gmail.com', 'HR Portal');      // Sender Email and Name
        */

        // Recipients
        $mail->addAddress($to);

        // Add CCs
        if (!empty($cc_list)) {
            foreach ($cc_list as $cc) {
                $mail->addCC($cc);
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error but don't stop execution
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>