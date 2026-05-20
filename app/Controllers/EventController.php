<?php

final class EventController extends Controller
{
    private EventModel $events;
    private RegistrationModel $registrations;

    public function __construct()
    {
        parent::__construct();
        $this->events = new EventModel($this->pdo);
        $this->registrations = new RegistrationModel($this->pdo);
    }

    public function index(): void
    {
        $result = $this->events->search($_GET, max(1, (int)($_GET['page'] ?? 1)), 12);
        $this->view('events/index', [
            'title' => 'Evenements',
            'events' => $result['events'],
            'total' => $result['total'],
        ]);
    }

    public function create(): void
    {
        $this->view('events/create', ['title' => 'Creer un evenement']);
    }

    public function store(): void
    {
        try {
            $eventId = $this->events->create($this->input());
            $this->json([
                'success' => true,
                'event_id' => $eventId,
                'message' => 'Evenement cree avec succes.',
            ]);
        } catch (InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            error_log('[EventHub MVC] EventController::store ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }
    }

    public function register(): void
    {
        $data = $this->input();
        $eventId = (int)($data['event_id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));

        if (!$eventId || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Donnees manquantes ou invalides.'], 400);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            $event = $this->events->findWithCountForUpdate($eventId);

            if (!$event) {
                $this->pdo->rollBack();
                $this->json(['success' => false, 'error' => 'Evenement introuvable.'], 404);
                return;
            }

            if ((int)$event['registered_count'] >= (int)$event['capacity']) {
                $this->pdo->rollBack();
                $this->json(['success' => false, 'error' => 'Evenement complet.', 'full' => true]);
                return;
            }

            if ($this->registrations->existsForEmail($eventId, $email)) {
                $this->pdo->rollBack();
                $this->json(['success' => false, 'error' => 'Vous etes deja inscrit(e) a cet evenement.']);
                return;
            }

            $token = bin2hex(random_bytes(32));
            $registrationId = $this->registrations->create($eventId, $name, $email, $token);
            $newCount = (int)$event['registered_count'] + 1;
            $fillPct = ($newCount / (int)$event['capacity']) * 100;
            $isFull = $newCount >= (int)$event['capacity'];
            $this->pdo->commit();

            $mailSent = (new MailController())->sendConfirmation($event, $name, $email, $token);
            $alertSent = false;

            if ($fillPct >= 80 && (int)$event['alert_sent'] === 0) {
                $event['registered_count'] = $newCount;
                $alertSent = (new MailController())->sendCapacityAlert($event);
            }

            $this->json([
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
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EventHub MVC] EventController::register ' . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }
    }
}
