<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token invalide.']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'SELECT r.id, r.event_id, e.title
         FROM registrations r
         JOIN events e ON e.id = r.event_id
         WHERE r.token = :token'
    );
    $stmt->execute([':token' => $token]);
    $registration = $stmt->fetch();

    if (!$registration) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Inscription introuvable.']);
        exit;
    }

    $delete = $pdo->prepare('DELETE FROM registrations WHERE token = :token');
    $delete->execute([':token' => $token]);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE event_id = :event_id');
    $countStmt->execute([':event_id' => (int)$registration['event_id']]);
    $registered = (int)$countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => 'Desinscription effectuee.',
        'event_id' => (int)$registration['event_id'],
        'event_title' => $registration['title'],
        'registered' => $registered,
    ]);
} catch (Throwable $e) {
    error_log('[EventHub] unsubscribe.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
}
