<?php

final class RegistrationModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function existsForEmail(int $eventId, string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM registrations WHERE event_id = :event_id AND email = :email');
        $stmt->execute([':event_id' => $eventId, ':email' => $email]);

        return (bool)$stmt->fetch();
    }

    public function create(int $eventId, string $name, string $email, string $token): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO registrations (event_id, name, email, token, registered_at)
             VALUES (:event_id, :name, :email, :token, NOW())'
        );
        $stmt->execute([
            ':event_id' => $eventId,
            ':name' => $name,
            ':email' => $email,
            ':token' => $token,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, e.title AS event_title
             FROM registrations r
             JOIN events e ON e.id = r.event_id
             WHERE r.token = :token'
        );
        $stmt->execute([':token' => $token]);
        $registration = $stmt->fetch();

        return $registration ?: null;
    }

    public function deleteByToken(string $token): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM registrations WHERE token = :token');
        $stmt->execute([':token' => $token]);

        return $stmt->rowCount() > 0;
    }
}
