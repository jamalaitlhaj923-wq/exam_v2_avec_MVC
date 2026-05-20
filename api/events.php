<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db.php';

$params = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $params = json_decode($body, true) ?? [];
} else {
    $params = $_GET;
}

try {
    $pdo = getDB();

    if (isset($params['preview'], $params['id']) && (int)$params['preview'] === 1) {
        echo json_encode(['success' => true, 'data' => getEventPreview($pdo, (int)$params['id'])]);
        exit;
    }

    $keyword = isset($params['keyword']) ? trim((string)$params['keyword']) : '';
    $category = isset($params['category']) ? trim((string)$params['category']) : '';
    $dateFrom = isset($params['date_from']) ? trim((string)$params['date_from']) : '';
    $dateTo = isset($params['date_to']) ? trim((string)$params['date_to']) : '';
    $hasPlaces = isset($params['has_places']) ? filter_var($params['has_places'], FILTER_VALIDATE_BOOLEAN) : false;
    $tab = isset($params['tab']) ? trim((string)$params['tab']) : 'all';
    $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
    $perPage = 6;

    $result = searchEvents($pdo, $keyword, $category, $dateFrom, $dateTo, $hasPlaces, $page, $perPage, $tab);

    echo json_encode([
        'success' => true,
        'data' => $result['events'],
        'meta' => [
            'total' => $result['total'],
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int)ceil($result['total'] / $perPage),
        ],
    ]);
} catch (Throwable $e) {
    error_log('[EventHub] api/events.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
}

function searchEvents(
    PDO $pdo,
    string $keyword = '',
    string $category = '',
    string $dateFrom = '',
    string $dateTo = '',
    bool $hasPlaces = false,
    int $page = 1,
    int $perPage = 6,
    string $tab = 'all'
): array {
    $baseSelect = "SELECT e.id,
                          e.title,
                          e.description,
                          e.event_date,
                          e.location,
                          e.capacity,
                          e.category,
                          e.organizer_email,
                          e.alert_sent,
                          COUNT(r.id) AS registered_count,
                          (e.capacity - COUNT(r.id)) AS available_places,
                          ROUND(COUNT(r.id) / e.capacity * 100) AS fill_percentage
                   FROM events e
                   LEFT JOIN registrations r ON r.event_id = e.id";

    $conditions = [];
    $bindings = [];

    // Strategie choisie : tableaux $conditions et $bindings nommes.
    if ($keyword !== '') {
        $conditions[] = '(e.title LIKE :keyword OR e.description LIKE :keyword)';
        $bindings[':keyword'] = '%' . $keyword . '%';
    }

    if ($category !== '') {
        $conditions[] = 'e.category = :category';
        $bindings[':category'] = $category;
    }

    if ($dateFrom !== '') {
        $conditions[] = 'e.event_date >= :date_from';
        $bindings[':date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo !== '') {
        $conditions[] = 'e.event_date <= :date_to';
        $bindings[':date_to'] = $dateTo . ' 23:59:59';
    }

    if ($hasPlaces || $tab === 'upcoming') {
        $conditions[] = 'e.capacity > (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.id)';
    }

    if ($tab === 'full') {
        $conditions[] = 'e.capacity <= (SELECT COUNT(*) FROM registrations r3 WHERE r3.event_id = e.id)';
    }

    $countSql = "SELECT COUNT(DISTINCT e.id)
                 FROM events e
                 LEFT JOIN registrations r ON r.event_id = e.id";
    if (!empty($conditions)) {
        $countSql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($bindings);
    $total = (int)$countStmt->fetchColumn();

    $sql = $baseSelect;
    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' GROUP BY e.id ORDER BY e.event_date ASC';

    $offset = ($page - 1) * $perPage;
    $sql .= ' LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['events' => $stmt->fetchAll(), 'total' => $total];
}

function getEventPreview(PDO $pdo, int $eventId): array
{
    $eventStmt = $pdo->prepare(
        'SELECT e.id, e.title, e.capacity, COUNT(r.id) AS registered_count,
                ROUND(COUNT(r.id) / e.capacity * 100) AS fill_percentage
         FROM events e
         LEFT JOIN registrations r ON r.event_id = e.id
         WHERE e.id = :id
         GROUP BY e.id'
    );
    $eventStmt->execute([':id' => $eventId]);
    $event = $eventStmt->fetch();

    if (!$event) {
        throw new RuntimeException('Evenement introuvable.');
    }

    $latestStmt = $pdo->prepare(
        'SELECT CONCAT(LEFT(name, 1), "***") AS masked_name, registered_at
         FROM registrations
         WHERE event_id = :id
         ORDER BY registered_at DESC
         LIMIT 5'
    );
    $latestStmt->execute([':id' => $eventId]);

    $hourlyStmt = $pdo->prepare(
        'SELECT HOUR(registered_at) AS hour, COUNT(*) AS count
         FROM registrations
         WHERE event_id = :id AND registered_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         GROUP BY HOUR(registered_at)
         ORDER BY hour ASC'
    );
    $hourlyStmt->execute([':id' => $eventId]);

    return [
        'event' => $event,
        'latest' => $latestStmt->fetchAll(),
        'hourly' => $hourlyStmt->fetchAll(),
    ];
}
