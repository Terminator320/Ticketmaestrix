<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\EventModel;
use App\Models\TicketModel;
use App\Models\VenueModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class TicketController
{
    public function __construct(
        private Environment $twig,
        private TicketModel $ticketModel,
        private EventModel $eventModel,
        private VenueModel $venueModel,
        private string $basePath,
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $html = $this->twig->render('ticket/index.html.twig', [
            'base_path' => $this->basePath,
            'tickets'   => $this->ticketModel->getAll(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function create(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $html = $this->twig->render('ticket/create.html.twig', [
            'base_path' => $this->basePath,
            'events'    => $this->eventModel->getAll(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function store(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }

        $data = (array) ($request->getParsedBody() ?? []);

        $errors = [];
        if (empty($data['price']))    $errors['price']    = ['Price is required.'];
        elseif (!is_numeric($data['price']) || (float)$data['price'] <= 0) $errors['price'] = ['Price must be a positive number.'];
        if (empty($data['seat']))     $errors['seat']     = ['Seat is required.'];
        if (empty($data['row']))      $errors['row']      = ['Row is required.'];
        if (empty($data['event_id'])) $errors['event_id'] = ['Please select an event.'];

        if ($errors) {
            $html = $this->twig->render('ticket/create.html.twig', [
                'base_path' => $this->basePath,
                'events'    => $this->eventModel->getAll(),
                'errors'    => $errors,
                'input'     => $data,
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(422);
        }

        $eventId = (int) ($data['event_id'] ?? 0);
        $this->ticketModel->create(
            (float) ($data['price'] ?? 0),
            (string) ($data['seat'] ?? ''),
            (string) ($data['row'] ?? ''),
            $eventId,
        );

        return $response->withHeader('Location', $this->basePath . '/events/' . $eventId . '/tickets')->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $ticket = $this->ticketModel->getById((int) $args['id']);

        if (!$ticket) {
            return $response->withHeader('Location', $this->basePath . '/tickets')->withStatus(302);
        }

        $html = $this->twig->render('ticket/edit.html.twig', [
            'base_path' => $this->basePath,
            'ticket'    => $ticket,
            'events'    => $this->eventModel->getAll(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }

        $id   = (int) $args['id'];
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = [];
        if (empty($data['price']))    $errors['price']    = ['Price is required.'];
        elseif (!is_numeric($data['price']) || (float)$data['price'] <= 0) $errors['price'] = ['Price must be a positive number.'];
        if (empty($data['seat']))     $errors['seat']     = ['Seat is required.'];
        if (empty($data['row']))      $errors['row']      = ['Row is required.'];
        if (empty($data['event_id'])) $errors['event_id'] = ['Please select an event.'];

        if ($errors) {
            $ticket = $this->ticketModel->getById($id);
            $html   = $this->twig->render('ticket/edit.html.twig', [
                'base_path' => $this->basePath,
                'ticket'    => $ticket,
                'events'    => $this->eventModel->getAll(),
                'errors'    => $errors,
                'input'     => $data,
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(422);
        }

        $ticket = $this->ticketModel->load($id);

        if ($ticket->id) {
            $ticket->price    = (float) ($data['price'] ?? $ticket->price);
            $ticket->seat     = (string) ($data['seat'] ?? $ticket->seat);
            $ticket->row      = (string) ($data['row'] ?? $ticket->row);
            $ticket->event_id = (int) ($data['event_id'] ?? $ticket->event_id);
            $this->ticketModel->save($ticket);
        }

        return $response->withHeader('Location', $this->basePath . '/tickets/' . $id . '/edit')->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $ticket = $this->ticketModel->load((int) $args['id']);
        if ($ticket->id) {
            $this->ticketModel->delete($ticket);
        }
        return $response->withHeader('Location', $this->basePath . '/admin')->withStatus(302);
    }

    public function viewDetails(Request $request, Response $response, array $args): Response
    {
        $ticket = $this->ticketModel->getById((int) $args['id']);

        if (!$ticket) {
            return $response->withHeader('Location', $this->basePath . '/tickets')->withStatus(302);
        }

        // Ticket detail page is admin-only for inventory management
        // Regular users should not access this page directly
        if (!\App\Helpers\Auth::isAdmin()) {
            // Redirect regular users to the event page instead
            return $response->withHeader('Location', $this->basePath . '/events/' . $ticket->event_id)->withStatus(302);
        }

        $html = $this->twig->render('ticket/ticket_detail.html.twig', [
            'base_path' => $this->basePath,
            'ticket'    => $ticket,
            'is_admin'  => true,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function byEvent(Request $request, Response $response, array $args): Response
    {
        $eventId = (int) $args['id'];
        $tickets = $this->ticketModel->findByEvent($eventId);
        $event   = $this->eventModel->getById($eventId);

        if ($event) {
            $event = $this->eventModel->hydrate([$event], $this->venueModel, $this->ticketModel)[0];
        }

        $html = $this->twig->render('ticket/tickets_by_event.html.twig', [
            'base_path' => $this->basePath,
            'tickets'   => $tickets,
            'event'     => $event,
            'event_id'  => $eventId, // Fallback
        ]);
        $response->getBody()->write($html);
        return $response;
    }
}
