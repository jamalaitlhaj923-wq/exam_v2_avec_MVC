<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/db.php';

// En production, decommenter ce bloc.
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'organizer') {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'error' => 'Acces non autorise.']);
//     exit;
// }

try {
    $pdo = getDB();

    $summaryStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_events,
            COALESCE(SUM(ec.registered_count), 0) AS total_registered,
            COALESCE(SUM(ec.new_last_24h), 0) AS new_last_24h,
            COALESCE(ROUND(AVG(ec.fill_pct)), 0) AS avg_fill_pct,
            COALESCE(SUM(CASE WHEN ec.fill_pct >= 80 THEN 1 ELSE 0 END), 0) AS alert_count
        FROM events e
        LEFT JOIN (
            SELECT
                event_id,
                COUNT(*) AS registered_count,
                SUM(CASE WHEN registered_at >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END) AS new_last_24h,
                0 AS fill_pct_placeholder
            FROM registrations
            GROUP BY event_id
        ) rc ON rc.event_id = e.id
        LEFT JOIN (
            SELECT
                e2.id,
                COALESCE(COUNT(r2.id), 0) AS registered_count,
                COALESCE(SUM(CASE WHEN r2.registered_at >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END), 0) AS new_last_24h,
                ROUND(COALESCE(COUNT(r2.id), 0) / e2.capacity * 100) AS fill_pct
            FROM events e2
            LEFT JOIN registrations r2 ON r2.event_id = e2.id
            GROUP BY e2.id
        ) ec ON ec.id = e.id
    ");
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch();

    $topStmt = $pdo->prepare("
        SELECT e.id, e.title,
               COUNT(r.id) AS reg,
               ROUND(COUNT(r.id) / e.capacity * 100) AS fill_pct
        FROM events e
        LEFT JOIN registrations r ON r.event_id = e.id
        GROUP BY e.id
        ORDER BY fill_pct DESC, reg DESC
        LIMIT 3
    ");
    $topStmt->execute();
    $top3 = $topStmt->fetchAll();

    $perEventStmt = $pdo->prepare("
        SELECT e.id, e.title, e.capacity,
               COUNT(r.id) AS registered,
               ROUND(COUNT(r.id) / e.capacity * 100) AS fill_pct,
               (e.capacity - COUNT(r.id) = 0) AS is_full
        FROM events e
        LEFT JOIN registrations r ON r.event_id = e.id
        GROUP BY e.id
        ORDER BY fill_pct DESC, registered DESC
    ");
    $perEventStmt->execute();
    $perEvent = $perEventStmt->fetchAll();

    $byDayStmt = $pdo->prepare("
        SELECT DATE(registered_at) AS day, COUNT(*) AS count
        FROM registrations
        WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(registered_at)
        ORDER BY day ASC
    ");
    $byDayStmt->execute();
    $byDay = $byDayStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => $summary,
        'top3' => $top3,
        'per_event' => $perEvent,
        'registrations_by_day' => $byDay,
    ]);
} catch (Throwable $e) {
    error_log('[EventHub] api/stats.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
}
