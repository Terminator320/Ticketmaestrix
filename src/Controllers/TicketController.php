<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\EventModel;
use App\Models\TicketModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class TicketController
{
    public function __construct(
        private Environment $twig,
        private TicketModel $ticketModel,
        private EventModel $eventModel,
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
        $data = $request->getParsedBody();
        $this->ticketModel->create(
            (float) ($data['price'] ?? 0),
            (string) ($data['seat'] ?? ''),
            (string) ($data['row'] ?? ''),
            (int) ($data['event_id'] ?? 0),
        );
        return $response->withHeader('Location', $this->basePath . '/tickets')->withStatus(302);
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
        $id     = (int) $args['id'];
        $data   = $request->getParsedBody();
        $ticket = $this->ticketModel->load($id);

        if ($ticket->id) {
            $ticket->price    = (float) ($data['price'] ?? $ticket->price);
            $ticket->seat     = (string) ($data['seat'] ?? $ticket->seat);
            $ticket->row      = (string) ($data['row'] ?? $ticket->row);
            $ticket->event_id = (int) ($data['event_id'] ?? $ticket->event_id);
            $this->ticketModel->save($ticket);
        }

        return $response->withHeader('Location', $this->basePath . '/tickets')->withStatus(302);
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
        return $response->withHeader('Location', $this->basePath . '/tickets')->withStatus(302);
    }

    public function viewDetails(Request $request, Response $response, array $args): Response
    {
        $ticket = $this->ticketModel->getById((int) $args['id']);

        if (!$ticket) {
            return $response->withHeader('Location', $this->basePath . '/tickets')->withStatus(302);
        }

        $html = $this->twig->render('ticket/ticket_detail.html.twig', [
            'base_path' => $this->basePath,
            'ticket'    => $ticket,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function byEvent(Request $request, Response $response, array $args): Response
    {
        $eventId = (int) $args['id'];
        $tickets = $this->ticketModel->findByEvent($eventId);

        $html = $this->twig->render('ticket/tickets_by_event.html.twig', [
            'base_path' => $this->basePath,
            'tickets'   => $tickets,
            'event_id'  => $eventId,
        ]);
        $response->getBody()->write($html);
        return $response;
    }
}
