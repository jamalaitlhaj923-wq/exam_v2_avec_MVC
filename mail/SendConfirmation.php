<?php
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/db.php';

class SendConfirmation
{
    public static function send(PDO $pdo, array $event, string $name, string $email, string $token): bool
    {
        $html = file_get_contents(__DIR__ . '/templates/confirmation.html');
        if ($html === false) {
            logMailError($pdo, 'confirmation', $email, 'Template confirmation introuvable.');
            return false;
        }

        $stmt = $pdo->prepare('SELECT id FROM registrations WHERE token = :token AND email = :email LIMIT 1');
        $stmt->execute([':token' => $token, ':email' => $email]);
        $registrationId = (int)$stmt->fetchColumn();

        if ($registrationId <= 0) {
            logMailError($pdo, 'confirmation', $email, 'Inscription introuvable pour generation du ticket PDF.');
            return false;
        }

        $eventDate = (new DateTime($event['event_date']))->format('d/m/Y à H\hi');
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/eventhub_mvp/events/register.php'));
        $appPath = rtrim(dirname($scriptDir), '/');
        if ($appPath === '' || $appPath === '.') {
            $appPath = '/eventhub_mvp';
        }
        $baseUrl = $scheme . '://' . $host . $appPath;

        $ticketLink = $baseUrl . '/pdf/ticket.php?registration_id=' . $registrationId . '&token=' . urlencode($token);
        $unsubscribeLink = $baseUrl . '/events/unsubscribe.php?token=' . urlencode($token);

        $html = str_replace([
            '{{PARTICIPANT_NAME}}',
            '{{EVENT_TITLE}}',
            '{{EVENT_DATE}}',
            '{{EVENT_LOCATION}}',
            '{{TICKET_LINK}}',
            '{{UNSUBSCRIBE_LINK}}',
            '{{YEAR}}',
        ], [
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'),
            $eventDate,
            htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8'),
            $ticketLink,
            $unsubscribeLink,
            date('Y'),
        ], $html);

        $html = preg_replace(
            '/\s*<!-- Bouton ticket -->\s*<div class="btn-wrap">.*?<\/div>/s',
            "\n      <p style=\"font-size:14px; color:#475569; text-align:center; font-weight:bold;\">Votre ticket PDF est joint directement a cet email.</p>",
            $html
        );

        $tempPdf = '';
        try {
            require_once __DIR__ . '/../pdf/ticket.php';
            $tempPdf = sys_get_temp_dir() . '/ticket_' . $registrationId . '_' . time() . '.pdf';
            generateTicketPDF($pdo, $registrationId, $token, 'F', $tempPdf);

            if (!file_exists($tempPdf) || filesize($tempPdf) <= 0) {
                logMailError($pdo, 'confirmation', $email, 'Ticket PDF non genere ou fichier vide : ' . $tempPdf);
                @unlink($tempPdf);
                return false;
            }

            $mail = createMailer();
            $mail->addAddress($email, $name);
            $mail->Subject = 'Inscription confirmee - ' . $event['title'];
            $mail->Body = $html;
            $mail->AltBody = "Votre inscription est confirmee. Le ticket PDF est joint a cet email.";
            $mail->addAttachment(
                $tempPdf,
                'ticket_' . $registrationId . '.pdf',
                \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                'application/pdf',
                'attachment'
            );

            $mail->send();
            @unlink($tempPdf);
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            logMailError($pdo, 'confirmation', $email, $e->getMessage());
            @unlink($tempPdf);
            return false;
        } catch (Throwable $e) {
            logMailError($pdo, 'confirmation', $email, $e->getMessage());
            @unlink($tempPdf);
            return false;
        }
    }
}
