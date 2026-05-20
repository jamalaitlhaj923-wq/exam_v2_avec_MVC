<?php
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/db.php';

class AlertMailer
{
    public static function sendCapacityAlert(PDO $pdo, array $event): bool
    {
        // Anti-doublon persistant : une alerte reussie met events.alert_sent a 1.
        if ((int)($event['alert_sent'] ?? 0) === 1) {
            return false;
        }

        require_once __DIR__ . '/../pdf/report.php';
        $tempPdf = sys_get_temp_dir() . '/report_event_' . (int)$event['id'] . '_' . time() . '.pdf';
        generateReportPDF($pdo, (int)$event['id'], 'F', $tempPdf);

        $registered = (int)($event['registered_count'] ?? 0);
        $capacity = max(1, (int)$event['capacity']);
        $fillPct = (int)round($registered / $capacity * 100);
        $available = max(0, $capacity - $registered);
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost/eventhub_mvp');

        $html = file_get_contents(__DIR__ . '/templates/alert.html');
        if ($html === false) {
            logMailError($pdo, 'capacity_alert', $event['organizer_email'], 'Template alerte introuvable.');
            @unlink($tempPdf);
            return false;
        }

        $html = str_replace([
            '{{ORGANIZER_NAME}}',
            '{{EVENT_TITLE}}',
            '{{FILL_PCT}}',
            '{{REGISTERED}}',
            '{{CAPACITY}}',
            '{{AVAILABLE}}',
            '{{DASHBOARD_LINK}}',
            '{{YEAR}}',
        ], [
            'Organisateur',
            htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'),
            (string)$fillPct,
            (string)$registered,
            (string)$capacity,
            (string)$available,
            $baseUrl . '/dashboard.php',
            date('Y'),
        ], $html);

        try {
            $mail = createMailer();
            $mail->addAddress($event['organizer_email']);
            $mail->Subject = 'Alerte capacite 80% - ' . $event['title'];
            $mail->Body = $html;
            $mail->AltBody = strip_tags($html);

            if (file_exists($tempPdf)) {
                $mail->addAttachment($tempPdf, 'rapport_evenement_' . (int)$event['id'] . '.pdf');
            }

            $mail->send();

            $stmt = $pdo->prepare('UPDATE events SET alert_sent = 1 WHERE id = :id');
            $stmt->execute([':id' => (int)$event['id']]);

            @unlink($tempPdf);
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            logMailError($pdo, 'capacity_alert', $event['organizer_email'], $e->getMessage());
            @unlink($tempPdf);
            return false;
        } catch (Throwable $e) {
            logMailError($pdo, 'capacity_alert', $event['organizer_email'], $e->getMessage());
            @unlink($tempPdf);
            return false;
        }
    }
}
