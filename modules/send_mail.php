<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Define paths to PHPMailer files
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
        $mail->Host       = 'localhost';
        $mail->SMTPAuth   = false;
        $mail->Port       = 1025;
        $mail->setFrom('noreply@hr-portal.local', 'HR Portal System');

        // ============================================================
        // PRODUCTION CONFIGURATION (Uncomment and fill to use)
        // ============================================================
        /*
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';
        $mail->Password   = 'your-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('your-email@gmail.com', 'HR Portal');
        */

        // Recipients
        $mail->addAddress($to);

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
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Generates a professional HTML email template
 *
 * @param string $title The main heading of the email
 * @param string $content The HTML content body
 * @param string $status_color Hex code for the accent color (e.g., #2563eb for blue, #059669 for green)
 * @return string Full HTML email
 */
function get_email_template($title, $content, $status_color = '#2563eb') {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            .header { background-color: #1e293b; padding: 20px; text-align: center; }
            .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 1px; }
            .content { padding: 40px 30px; color: #334155; line-height: 1.6; }
            .status-bar { height: 6px; background-color: $status_color; width: 100%; }
            .footer { background-color: #f8fafc; padding: 20px; text-align: center; color: #94a3b8; font-size: 12px; border-top: 1px solid #e2e8f0; }
            .btn { display: inline-block; padding: 12px 24px; background-color: $status_color; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-top: 20px; }
            .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .info-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; }
            .info-table td:first-child { font-weight: 600; color: #64748b; width: 140px; }
            .info-table td:last-child { color: #1e293b; font-weight: 500; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>HR PORTAL</h1>
            </div>
            <div class='status-bar'></div>
            <div class='content'>
                <h2 style='color: $status_color; margin-top: 0;'>$title</h2>
                $content
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " HR Portal System. All rights reserved.</p>
                <p>This is an automated message, please do not reply directly.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>