<?php

final class ApiController extends Controller
{
    private EventModel $events;

    public function __construct()
    {
        parent::__construct();
        $this->events = new EventModel($this->pdo);
    }

    public function events(): void
    {
        try {
            $filters = $_SERVER['REQUEST_METHOD'] === 'POST' ? $this->input() : $_GET;
            $page = max(1, (int)($filters['page'] ?? 1));
            $perPage = min(50, max(1, (int)($filters['per_page'] ?? 12)));
            $result = $this->events->search($filters, $page, $perPage);

            $this->json([
                'success' => true,
                'data' => $result['events'],
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $perPage,
            ]);
        } catch (Throwable $e) {
            error_log('[EventHub MVC] ApiController::events ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }
    }

    public function stats(): void
    {
        try {
            $stats = $this->events->stats();
            $this->json([
                'success' => true,
                'generated_at' => date('Y-m-d H:i:s'),
                'summary' => $stats['summary'],
                'top3' => $stats['top3'],
                'per_event' => $stats['per_event'],
                'registrations_by_day' => $stats['registrations_by_day'],
            ]);
        } catch (Throwable $e) {
            error_log('[EventHub MVC] ApiController::stats ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }
    }
}
