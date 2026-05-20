<?php

final class EventModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
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

        $stmt = $this->pdo->prepare(
            'INSERT INTO events (title, description, event_date, location, capacity, category, organizer_email, created_at)
             VALUES (:title, :description, :event_date, :location, :capacity, :category, :organizer_email, NOW())'
        );
        $stmt->execute([
            ':title' => htmlspecialchars(trim((string)$data['title']), ENT_QUOTES, 'UTF-8'),
            ':description' => trim((string)$data['description']),
            ':event_date' => (string)$data['date'],
            ':location' => trim((string)$data['location']),
            ':capacity' => $capacity,
            ':category' => trim((string)$data['category']),
            ':organizer_email' => trim((string)$data['organizer_email']),
        ]);

        $id = (int)$this->pdo->lastInsertId();
        if ($id === 0) {
            throw new RuntimeException("L'insertion en base a echoue.");
        }

        return $id;
    }

    public function findWithCountForUpdate(int $eventId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registered_count
             FROM events e
             WHERE e.id = :id
             FOR UPDATE'
        );
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch();

        return $event ?: null;
    }

    public function search(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $keyword = trim((string)($filters['keyword'] ?? ''));
        $category = trim((string)($filters['category'] ?? ''));
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        $hasPlaces = filter_var($filters['has_places'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $tab = trim((string)($filters['tab'] ?? 'all'));

        $conditions = [];
        $bindings = [];

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

        if ($hasPlaces) {
            $conditions[] = 'e.capacity > (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.id)';
        }

        if ($tab === 'upcoming') {
            $conditions[] = 'e.event_date >= NOW()';
        } elseif ($tab === 'full') {
            $conditions[] = 'e.capacity <= (SELECT COUNT(*) FROM registrations rf WHERE rf.event_id = e.id)';
        }

        $from = ' FROM events e LEFT JOIN registrations r ON r.event_id = e.id';
        $where = empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare('SELECT COUNT(DISTINCT e.id)' . $from . $where);
        $countStmt->execute($bindings);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = 'SELECT e.*, COUNT(r.id) AS registered_count,
                       ROUND(COUNT(r.id) / e.capacity * 100) AS fill_pct
                ' . $from . $where . '
                GROUP BY e.id
                ORDER BY e.event_date ASC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['events' => $stmt->fetchAll(), 'total' => $total];
    }

    public function stats(): array
    {
        $summary = $this->pdo->query(
            "SELECT
                COUNT(*) AS total_events,
                COALESCE(SUM(ec.registered_count), 0) AS total_registered,
                COALESCE(SUM(ec.new_last_24h), 0) AS new_last_24h,
                COALESCE(ROUND(AVG(ec.fill_pct)), 0) AS avg_fill_pct,
                COALESCE(SUM(CASE WHEN ec.fill_pct >= 80 THEN 1 ELSE 0 END), 0) AS alert_count
             FROM events e
             LEFT JOIN (
                SELECT e2.id,
                       COUNT(r2.id) AS registered_count,
                       COALESCE(SUM(CASE WHEN r2.registered_at >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END), 0) AS new_last_24h,
                       ROUND(COUNT(r2.id) / e2.capacity * 100) AS fill_pct
                FROM events e2
                LEFT JOIN registrations r2 ON r2.event_id = e2.id
                GROUP BY e2.id
             ) ec ON ec.id = e.id"
        )->fetch();

        $top3 = $this->pdo->query(
            'SELECT e.id, e.title, COUNT(r.id) AS reg,
                    ROUND(COUNT(r.id) / e.capacity * 100) AS fill_pct
             FROM events e
             LEFT JOIN registrations r ON r.event_id = e.id
             GROUP BY e.id
             ORDER BY fill_pct DESC, reg DESC
             LIMIT 3'
        )->fetchAll();

        $perEvent = $this->pdo->query(
            'SELECT e.id, e.title, e.capacity, COUNT(r.id) AS registered,
                    ROUND(COUNT(r.id) / e.capacity * 100) AS fill_pct,
                    (e.capacity - COUNT(r.id) = 0) AS is_full
             FROM events e
             LEFT JOIN registrations r ON r.event_id = e.id
             GROUP BY e.id
             ORDER BY fill_pct DESC, registered DESC'
        )->fetchAll();

        $byDay = $this->pdo->query(
            'SELECT DATE(registered_at) AS day, COUNT(*) AS count
             FROM registrations
             WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(registered_at)
             ORDER BY day ASC'
        )->fetchAll();

        return [
            'summary' => $summary,
            'top3' => $top3,
            'per_event' => $perEvent,
            'registrations_by_day' => $byDay,
        ];
    }

    public function markAlertSent(int $eventId): void
    {
        $stmt = $this->pdo->prepare('UPDATE events SET alert_sent = 1 WHERE id = :id');
        $stmt->execute([':id' => $eventId]);
    }
}
