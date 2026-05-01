<?php

namespace App\Controllers;

use App\Models\EventModel as EventModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class EventController {
    public function __construct(
        private Environment $twig,
        private EventModel $eventModel,
        private string $basePath,
    ) {
        $this->twig = $twig;
        $this->eventModel = $eventModel;
        $this->basePath = $basePath;
    }

     public function store(Request $request, Response $response): Response {
       $data = $request->getParsedBody();

       $this->eventModel->create(
           (string) ($data['title'] ?? ''),
           (string) ($data['description'] ?? ''),
           (string) ($data['date'] ?? ''),
           (int) ($data['venue_id'] ?? 0),
           (int) ($data['category_id'] ?? 0),
           (string) ($data['event_image'] ?? '')
       );

        return $response
            ->withHeader('Location', $this->basePath . '/events')
            ->withStatus(302);
     }

     public function update(Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        $event = $this->eventModel->load($id);

        if ($event->id) {
            $event->title = (string) ($data['title'] ?? $event->title);
            $event->description = (string) ($data['description'] ?? $event->description);
            $event->date = (string) ($data['date'] ?? $event->date);
            $event->venue_id = (int) ($data['venue_id'] ?? $event->venue_id);
            $event->category_id = (int) ($data['category_id'] ?? $event->category_id);
            $event->event_image = (string) ($data['event_image'] ?? $event->event_image);
            $this->eventModel->save($event);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/events')
            ->withStatus(302);

     }

     public function delete(Request $request, Response $response): Response {
        $event = $this->eventModel->load((int)$request->getAttribute('id') ?? 0);

        if ($event->id) {
            $this->eventModel->delete($event);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/events')
            ->withStatus(302);
     }

     public function viewDetails(Request $request, Response $response): Response {
        $event = $this->eventModel->load((int)$request->getAttribute('id') ?? 0);

        if (!$event->id) {
            return $response
                ->withHeader('Location', $this->basePath . '/events')
                ->withStatus(302);
        }

        $html = $this->twig->render('REPLACELATER', [
            'event' => $event,
        ]);

        $response->getBody()->write($html);
        return $response;
     }

     public function byCategory(Request $request, Response $response): Response {
        $categoryId = (int) ($request->getQueryParams()['category'] ?? $request->getAttribute('id') ?? 0);
        $events = $this->eventModel->findByCategory($categoryId);

        $html = $this->twig->render('REPLACELATER', [
            'events' => $events,
            'category_id' => $categoryId,
        ]);

        $response->getBody()->write($html);
        return $response;
     }
}