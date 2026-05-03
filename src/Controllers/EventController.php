<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\CategoryModel;
use App\Models\EventModel;
use App\Models\VenueModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class EventController
{
    public function __construct(
        private Environment $twig,
        private EventModel $eventModel,
        private CategoryModel $categoryModel,
        private VenueModel $venueModel,
        private string $basePath,
    ) {}

    public function index(Request $request, Response $response): Response
{
    $queryParams = $request->getQueryParams();
    $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    
    $perPage = 9;
    $offset = ($page - 1) * $perPage;

    $events = $this->eventModel->getPaginated($perPage, $offset); 

    $totalEvents = $this->eventModel->countAll();
    $totalPages = ceil($totalEvents / $perPage);

    $html = $this->twig->render('event/index.html.twig', [
        'base_path'   => $this->basePath,
        'events'      => $events,
        'currentPage' => $page,
        'totalPages'  => $totalPages,
    ]);

    $response->getBody()->write($html);
    return $response;
}

    public function create(Request $request, Response $response): Response
    {
        // Admin-only: anyone else gets bounced to /login or /.
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $html = $this->twig->render('event/create.html.twig', [
            'base_path'  => $this->basePath,
            'categories' => $this->categoryModel->getAll(),
            'venues'     => $this->venueModel->getAll(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function store(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $data = $request->getParsedBody();
        $this->eventModel->create(
            (string) ($data['title'] ?? ''),
            (string) ($data['description'] ?? ''),
            (string) ($data['date'] ?? ''),
            (int) ($data['venue_id'] ?? 0),
            (int) ($data['category_id'] ?? 0),
            (string) ($data['event_image'] ?? ''),
        );
        return $response->withHeader('Location', $this->basePath . '/events')->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $event = $this->eventModel->getById((int) $args['id']);

        if (!$event) {
            return $response->withHeader('Location', $this->basePath . '/events')->withStatus(302);
        }

        $html = $this->twig->render('event/edit.html.twig', [
            'base_path'  => $this->basePath,
            'event'      => $event,
            'categories' => $this->categoryModel->getAll(),
            'venues'     => $this->venueModel->getAll(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $id    = (int) $args['id'];
        $data  = $request->getParsedBody();
        $event = $this->eventModel->load($id);

        if ($event->id) {
            $event->title       = (string) ($data['title'] ?? $event->title);
            $event->description = (string) ($data['description'] ?? $event->description);
            $event->date        = (string) ($data['date'] ?? $event->date);
            $event->venue_id    = (int) ($data['venue_id'] ?? $event->venue_id);
            $event->category_id = (int) ($data['category_id'] ?? $event->category_id);
            $event->event_image = (string) ($data['event_image'] ?? $event->event_image);
            $this->eventModel->save($event);
        }

        return $response->withHeader('Location', $this->basePath . '/events')->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $event = $this->eventModel->load((int) $args['id']);
        if ($event->id) {
            $this->eventModel->delete($event);
        }
        return $response->withHeader('Location', $this->basePath . '/events')->withStatus(302);
    }

    public function viewDetails(Request $request, Response $response, array $args): Response
    {
        $event = $this->eventModel->getById((int) $args['id']);

        if (!$event) {
            return $response->withHeader('Location', $this->basePath . '/events')->withStatus(302);
        }

        $html = $this->twig->render('event/event_detail.html.twig', [
            'base_path'     => $this->basePath,
            'current_route' => 'events',
            'event'         => $event,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function byCategory(Request $request, Response $response, array $args): Response
    {
        $categoryId = (int) $args['id'];
        $events     = $this->eventModel->findByCategory($categoryId);

        $html = $this->twig->render('event/events_by_category.html.twig', [
            'base_path'     => $this->basePath,
            'current_route' => 'events',
            'events'        => $events,
            'category_id'   => $categoryId,
        ]);
        $response->getBody()->write($html);
        return $response;
    }
}
