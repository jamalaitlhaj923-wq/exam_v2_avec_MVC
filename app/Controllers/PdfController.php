<?php

final class PdfController extends Controller
{
    public function ticket(): void
    {
        require_once __DIR__ . '/../../pdf/ticket.php';
        generateTicketPDF(
            $this->pdo,
            (int)($_GET['registration_id'] ?? 0),
            (string)($_GET['token'] ?? ''),
            'D'
        );
    }

    public function report(): void
    {
        require_once __DIR__ . '/../../pdf/report.php';
        generateReportPDF($this->pdo, (int)($_GET['event_id'] ?? 0), 'D');
    }
}
