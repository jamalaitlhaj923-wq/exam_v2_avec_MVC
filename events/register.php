<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode non autorisee.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$eventId = isset($data['event_id']) ? (int)$data['event_id'] : 0;
$name = isset($data['name']) ? trim((string)$data['name']) : '';
$email = isset($data['email']) ? trim((string)$data['email']) : '';

if (!$eventId || $name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Donnees manquantes ou invalides.']);
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT e.*,
                (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registered_count
         FROM events e
         WHERE e.id = :id
         FOR UPDATE'
    );
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Evenement introuvable.']);
        exit;
    }

    if ((int)$event['registered_count'] >= (int)$event['capacity']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Evenement complet.', 'full' => true]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM registrations WHERE event_id = :eid AND email = :email');
    $stmt->execute([':eid' => $eventId, ':email' => $email]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Vous etes deja inscrit(e) a cet evenement.']);
        exit;
    }

    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare(
        'INSERT INTO registrations (event_id, name, email, token, registered_at)
         VALUES (:event_id, :name, :email, :token, NOW())'
    );
    $stmt->execute([
        ':event_id' => $eventId,
        ':name' => $name,
        ':email' => $email,
        ':token' => $token,
    ]);
    $registrationId = (int)$pdo->lastInsertId();

    $newCount = (int)$event['registered_count'] + 1;
    $fillPct = ($newCount / (int)$event['capacity']) * 100;
    $isFull = $newCount >= (int)$event['capacity'];
    $pdo->commit();

    require_once __DIR__ . '/../mail/SendConfirmation.php';
    $mailSent = SendConfirmation::send($pdo, $event, $name, $email, $token);

    $alertSent = false;
    if ($fillPct >= 80 && (int)$event['alert_sent'] === 0) {
        require_once __DIR__ . '/../mail/AlertMailer.php';
        $eventWithCount = $event;
        $eventWithCount['registered_count'] = $newCount;
        $alertSent = AlertMailer::sendCapacityAlert($pdo, $eventWithCount);
    }

    echo json_encode([
        'success' => true,
        'registration_id' => $registrationId,
        'token' => $token,
        'capacity_pct' => (int)round($fillPct),
        'registered' => $newCount,
        'capacity' => (int)$event['capacity'],
        'is_full' => $isFull,
        'alert_sent' => $alertSent,
        'mail_sent' => $mailSent,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[EventHub] register.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
}
