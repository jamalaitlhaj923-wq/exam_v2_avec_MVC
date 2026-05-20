<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode non autorisee.']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Donnees JSON invalides.']);
    exit;
}

try {
    $pdo = getDB();
    $eventId = createEvent($pdo, $data);
    echo json_encode([
        'success' => true,
        'event_id' => $eventId,
        'message' => 'Evenement cree avec succes.',
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[EventHub] create.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
}

function createEvent(PDO $pdo, array $data): int
{
    $required = ['title', 'description', 'date', 'location', 'capacity', 'category', 'organizer_email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new InvalidArgumentException("Champ manquant : $field");
        }
    }

    if (!filter_var($data['organizer_email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email organisateur invalide.');
    }

    $capacity = (int)$data['capacity'];
    if ($capacity <= 0) {
        throw new InvalidArgumentException('La capacite doit etre superieure a 0.');
    }

    // Corrections : requete preparee parametree, pas de concatenation SQL utilisateur.
    $sql = "INSERT INTO events (title, description, event_date, location, capacity, category, organizer_email, created_at)
            VALUES (:title, :description, :event_date, :location, :capacity, :category, :organizer_email, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title' => htmlspecialchars(trim((string)$data['title']), ENT_QUOTES, 'UTF-8'),
        ':description' => trim((string)$data['description']),
        ':event_date' => (string)$data['date'],
        ':location' => trim((string)$data['location']),
        ':capacity' => $capacity,
        ':category' => trim((string)$data['category']),
        ':organizer_email' => trim((string)$data['organizer_email']),
    ]);

    $id = (int)$pdo->lastInsertId();
    if ($id === 0) {
        throw new RuntimeException("L'insertion en base a echoue sans exception.");
    }

    return $id;
}
