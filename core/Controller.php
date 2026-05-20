<?php

abstract class Controller
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    protected function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require __DIR__ . '/../app/Views/layouts/header.php';
        require __DIR__ . '/../app/Views/' . $view . '.php';
        require __DIR__ . '/../app/Views/layouts/footer.php';
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }

    protected function input(): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (is_array($json)) {
            return $json;
        }

        return $_POST;
    }
}
