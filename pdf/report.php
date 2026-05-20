<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/tcpdf/tcpdf.php';

if (!class_exists('EventHubPDF')) {
    class EventHubPDF extends TCPDF
    {
        public string $eventTitle = '';

        public function Header()
        {
            $this->SetFont('helvetica', 'B', 10);
            $this->SetFillColor(15, 31, 61);
            $this->Rect(0, 0, 297, 14, 'F');
            $this->SetTextColor(255, 255, 255);
            $this->SetXY(0, 2);
            $this->Cell(0, 10, 'EventHub Pro - Rapport : ' . $this->eventTitle, 0, 0, 'C');
        }

        public function Footer()
        {
            $this->SetY(-12);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(150, 150, 150);
            $this->Cell(0, 10, 'Genere le ' . date('d/m/Y a H:i') . ' | Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }
}

function generateReportPDF(PDO $pdo, int $eventId, string $output = 'D', string $filePath = '')
{
    $stmt = $pdo->prepare(
        'SELECT e.*,
                COUNT(r.id) AS registered_count,
                (e.capacity - COUNT(r.id)) AS available_places,
                ROUND(COUNT(r.id) / e.capacity * 100) AS fill_pct
         FROM events e
         LEFT JOIN registrations r ON r.event_id = e.id
         WHERE e.id = :id
         GROUP BY e.id'
    );
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        die('Evenement introuvable.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, email, registered_at
         FROM registrations
         WHERE event_id = :id
         ORDER BY name ASC'
    );
    $stmt->execute([':id' => $eventId]);
    $registrations = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT DATE(registered_at) AS day, COUNT(*) AS count
         FROM registrations
         WHERE event_id = :id
           AND registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(registered_at)
         ORDER BY day ASC'
    );
    $stmt->execute([':id' => $eventId]);
    $statsByDay = $stmt->fetchAll();

    $pdf = new EventHubPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->eventTitle = $event['title'];
    $pdf->SetCreator('EventHub Pro');
    $pdf->SetAuthor('ENSA Marrakech');
    $pdf->SetTitle('Rapport - ' . $event['title']);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->SetMargins(15, 20, 15);

    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->SetY(20);
    $pdf->Cell(0, 12, $event['title'], 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, date('d/m/Y a H\hi', strtotime($event['event_date'])) . ' - ' . $event['location'], 0, 1, 'C');
    $pdf->Ln(6);

    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->RoundedRect(15, 48, 80, 40, 4, '1111', 'DF');
    $pdf->RoundedRect(105, 48, 80, 40, 4, '1111', 'DF');
    $pdf->RoundedRect(195, 48, 80, 40, 4, '1111', 'DF');

    $pdf->SetFont('helvetica', 'B', 28);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->SetXY(15, 52);
    $pdf->Cell(80, 20, (string)$event['registered_count'], 0, 0, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(148, 163, 184);
    $pdf->SetXY(15, 72);
    $pdf->Cell(80, 8, 'Inscrits', 0, 0, 'C');

    $pdf->SetFont('helvetica', 'B', 28);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->SetXY(105, 52);
    $pdf->Cell(80, 20, (string)$event['capacity'], 0, 0, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(148, 163, 184);
    $pdf->SetXY(105, 72);
    $pdf->Cell(80, 8, 'Capacite totale', 0, 0, 'C');

    $fillPct = (int)$event['fill_pct'];
    $fillColor = $fillPct >= 80 ? [220, 38, 38] : [37, 99, 235];
    $pdf->SetFont('helvetica', 'B', 28);
    $pdf->SetTextColor($fillColor[0], $fillColor[1], $fillColor[2]);
    $pdf->SetXY(195, 52);
    $pdf->Cell(80, 20, $fillPct . '%', 0, 0, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(148, 163, 184);
    $pdf->SetXY(195, 72);
    $pdf->Cell(80, 8, 'Taux de remplissage', 0, 0, 'C');

    $pdf->SetY(100);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->Cell(0, 6, 'Progression de remplissage', 0, 1);
    $pdf->SetFillColor(226, 232, 240);
    $pdf->Rect(15, 110, 267, 8, 'F');
    $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
    $pdf->Rect(15, 110, 267 * min(100, $fillPct) / 100, 8, 'F');

    $pdf->SetY(128);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->MultiCell(0, 7, 'Organisateur : ' . $event['organizer_email'] . "\nPlaces disponibles : " . $event['available_places'] . "\nRapport genere automatiquement par EventHub Pro.", 0, 'L');

    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->SetY(20);
    $pdf->Cell(0, 10, 'Liste des participants inscrits (' . count($registrations) . ')', 0, 1);
    $pdf->Ln(2);

    drawRegistrationHeader($pdf);
    $pdf->SetFont('helvetica', '', 8);
    $fill = false;
    foreach ($registrations as $i => $reg) {
        if ($pdf->GetY() > 175) {
            $pdf->AddPage();
            drawRegistrationHeader($pdf);
            $pdf->SetFont('helvetica', '', 8);
        }
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255);
        $pdf->SetTextColor(15, 31, 61);
        $pdf->Cell(10, 7, (string)($i + 1), 1, 0, 'C', true);
        $pdf->Cell(70, 7, $reg['name'], 1, 0, 'L', true);
        $pdf->Cell(100, 7, $reg['email'], 1, 0, 'L', true);
        $pdf->Cell(50, 7, date('d/m/Y H:i', strtotime($reg['registered_at'])), 1, 1, 'C', true);
        $fill = !$fill;
    }

    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->SetY(20);
    $pdf->Cell(0, 10, 'Inscriptions par jour - 7 derniers jours', 0, 1, 'C');

    if (empty($statsByDay)) {
        $pdf->SetFont('helvetica', 'I', 12);
        $pdf->SetTextColor(148, 163, 184);
        $pdf->Cell(0, 10, 'Aucune inscription dans les 7 derniers jours.', 0, 1, 'C');
    } else {
        $maxCount = max(array_map('intval', array_column($statsByDay, 'count')));
        $maxCount = max(1, $maxCount);
        $barW = 30;
        $gap = 6;
        $chartH = 80;
        $originX = 30;
        $originY = 170;

        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.3);
        $chartW = count($statsByDay) * ($barW + $gap);
        $pdf->Line($originX, $originY - $chartH, $originX, $originY);
        $pdf->Line($originX, $originY, $originX + $chartW, $originY);

        $pdf->SetTextColor(180, 180, 180);
        $pdf->SetFont('helvetica', '', 7);
        for ($i = 1; $i <= 5; $i++) {
            $yGrid = $originY - ($chartH * $i / 5);
            $pdf->Line($originX, $yGrid, $originX + $chartW, $yGrid);
            $pdf->SetXY($originX - 14, $yGrid - 2);
            $pdf->Cell(12, 5, (string)(int)round($maxCount * $i / 5), 0, 0, 'R');
        }

        foreach ($statsByDay as $i => $row) {
            $count = (int)$row['count'];
            $barH = ($count / $maxCount) * $chartH;
            $x = $originX + $i * ($barW + $gap) + $gap;
            $y = $originY - $barH;

            $pdf->SetFillColor(37, 99, 235);
            $pdf->Rect($x, $y, $barW, $barH, 'F');
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(15, 31, 61);
            $pdf->SetXY($x, $y - 6);
            $pdf->Cell($barW, 5, (string)$count, 0, 0, 'C');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY($x, $originY + 2);
            $pdf->Cell($barW, 5, date('d/m', strtotime($row['day'])), 0, 0, 'C');
        }
    }

    if ($output === 'F' && $filePath !== '') {
        $pdf->Output($filePath, 'F');
        return $filePath;
    }

    if ($output === 'S') {
        return $pdf->Output('rapport_evenement_' . $eventId . '.pdf', 'S');
    }

    $pdf->Output('rapport_evenement_' . $eventId . '.pdf', 'D');
}

function drawRegistrationHeader(TCPDF $pdf): void
{
    $pdf->SetFillColor(15, 31, 61);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(70, 8, 'Nom', 1, 0, 'L', true);
    $pdf->Cell(100, 8, 'Email', 1, 0, 'L', true);
    $pdf->Cell(50, 8, 'Date inscription', 1, 1, 'C', true);
}

if (php_sapi_name() !== 'cli' && isset($_GET['event_id'])) {
    $pdo = getDB();
    generateReportPDF($pdo, (int)$_GET['event_id'], 'D');
}
