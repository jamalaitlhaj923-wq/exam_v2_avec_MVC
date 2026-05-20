<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../app/Models/EventModel.php';
require_once __DIR__ . '/../app/Models/RegistrationModel.php';
require_once __DIR__ . '/../app/Models/UserModel.php';
require_once __DIR__ . '/../app/Controllers/MailController.php';
require_once __DIR__ . '/../app/Controllers/EventController.php';
require_once __DIR__ . '/../app/Controllers/ApiController.php';
require_once __DIR__ . '/../app/Controllers/PdfController.php';
require_once __DIR__ . '/../app/Controllers/DashboardController.php';

$router = new Router();

$router->get('', [EventController::class, 'index']);
$router->get('events', [EventController::class, 'index']);
$router->get('events/create', [EventController::class, 'create']);
$router->post('events/store', [EventController::class, 'store']);
$router->post('events/register', [EventController::class, 'register']);
$router->match(['GET', 'POST'], 'api/events', [ApiController::class, 'events']);
$router->get('api/stats', [ApiController::class, 'stats']);
$router->get('dashboard', [DashboardController::class, 'index']);
$router->get('pdf/ticket', [PdfController::class, 'ticket']);
$router->get('pdf/report', [PdfController::class, 'report']);

$route = (string)($_GET['route'] ?? 'events');
$router->dispatch($_SERVER['REQUEST_METHOD'], $route);
