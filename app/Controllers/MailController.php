<?php

final class MailController extends Controller
{
    public function sendConfirmation(array $event, string $name, string $email, string $token): bool
    {
        require_once __DIR__ . '/../../config/mailer.php';
        require_once __DIR__ . '/../../mail/SendConfirmation.php';

        return SendConfirmation::send($this->pdo, $event, $name, $email, $token);
    }

    public function sendCapacityAlert(array $event): bool
    {
        require_once __DIR__ . '/../../config/mailer.php';
        require_once __DIR__ . '/../../mail/AlertMailer.php';

        return AlertMailer::sendCapacityAlert($this->pdo, $event);
    }
}
