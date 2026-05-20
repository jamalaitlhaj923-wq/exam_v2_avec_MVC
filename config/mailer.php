<?php
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: 'jamalaitlhaj923@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_NAME', 'EventHub Pro - ENSA Marrakech');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');

function createMailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 8;
    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->isHTML(true);

    return $mail;
}

function logMailError(PDO $pdo, string $type, string $to, string $error): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO mail_logs (type, recipient, error_message, created_at)
             VALUES (:type, :to, :error, NOW())'
        );
        $stmt->execute([
            ':type' => $type,
            ':to' => $to,
            ':error' => $error,
        ]);
    } catch (PDOException $e) {
        error_log('[EventHub] logMailError DB failed: ' . $e->getMessage());
    }
}
