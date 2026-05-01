<?php

namespace App\Controllers;

use App\Models\TicketModel as TicketModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class TicketController {
    public function __construct(
        private Environment $twig,
        private TicketModel $ticketModel,
        private string $basePath,
    ) {
        $this->twig = $twig;
        $this->ticketModel = $ticketModel;
        $this->basePath = $basePath;
    }

     public function store(Request $request, Response $response): Response {
       $data = $request->getParsedBody();

       $this->ticketModel->create(
           (float) ($data['price'] ?? 0),
           (string) ($data['seat'] ?? ''),
           (string) ($data['row'] ?? ''),
           (int) ($data['event_id'] ?? 0)
       );

        return $response
            ->withHeader('Location', $this->basePath . '/tickets')
            ->withStatus(302);
     }

     public function update(Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        $ticket = $this->ticketModel->load($id);

        if ($ticket->id) {
            $ticket->price = (float) ($data['price'] ?? $ticket->price);
            $ticket->seat = (string) ($data['seat'] ?? $ticket->seat);
            $ticket->row = (string) ($data['row'] ?? $ticket->row);
            $ticket->event_id = (int) ($data['event_id'] ?? $ticket->event_id);
            $this->ticketModel->save($ticket);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/tickets')
            ->withStatus(302);

     }

     public function delete(Request $request, Response $response): Response {
        $ticket = $this->ticketModel->load((int)$request->getAttribute('id') ?? 0);

        if ($ticket->id) {
            $this->ticketModel->delete($ticket);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/tickets')
            ->withStatus(302);
     }

     public function viewDetails(Request $request, Response $response): Response {
        $ticket = $this->ticketModel->load((int)$request->getAttribute('id') ?? 0);

        if (!$ticket->id) {
            return $response
                ->withHeader('Location', $this->basePath . '/tickets')
                ->withStatus(302);
        }

        $html = $this->twig->render('REPLACELATER', [
            'ticket' => $ticket,
        ]);

        $response->getBody()->write($html);
        return $response;
     }

     public function byEvent(Request $request, Response $response): Response {
        $eventId = (int) ($request->getQueryParams()['event'] ?? $request->getAttribute('id') ?? 0);
        $tickets = $this->ticketModel->findByEvent($eventId);

        $html = $this->twig->render('REPLACELATER', [
            'tickets' => $tickets,
            'event_id' => $eventId,
        ]);

        $response->getBody()->write($html);
        return $response;
     }
}