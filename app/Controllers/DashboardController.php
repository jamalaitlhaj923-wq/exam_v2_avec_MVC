<?php

final class DashboardController extends Controller
{
    public function index(): void
    {
        $stats = (new EventModel($this->pdo))->stats();
        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'summary' => $stats['summary'],
            'top3' => $stats['top3'],
            'perEvent' => $stats['per_event'],
        ]);
    }
}
