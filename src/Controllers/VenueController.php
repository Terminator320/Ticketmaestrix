<?php

namespace App\Controllers;

use App\Models\VenueModel as VenueModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class VenueController {
    public function __construct(
        private Environment $twig,
        private VenueModel $venueModel,
        private string $basePath,
    ) {
        $this->twig = $twig;
        $this->venueModel = $venueModel;
        $this->basePath = $basePath;
    }

     public function store(Request $request, Response $response): Response {
       $data = $request->getParsedBody();

       $this->venueModel->create(
           (string) ($data['name'] ?? ''),
           (string) ($data['description'] ?? ''),
           (string) ($data['image_url'] ?? ''),
           (string) ($data['address'] ?? ''),
           (int) ($data['capacity'] ?? 0)
       );

        return $response
            ->withHeader('Location', $this->basePath . '/venues')
            ->withStatus(302);
     }

     public function update(Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        $venue = $this->venueModel->load($id);

        if ($venue->id) {
            $venue->name = (string) ($data['name'] ?? $venue->name);
            $venue->description = (string) ($data['description'] ?? $venue->description);
            $venue->image_url = (string) ($data['image_url'] ?? $venue->image_url);
            $venue->address = (string) ($data['address'] ?? $venue->address);
            $venue->capacity = (int) ($data['capacity'] ?? $venue->capacity);
            $this->venueModel->save($venue);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/venues')
            ->withStatus(302);

     }

     public function delete(Request $request, Response $response): Response {
        $venue = $this->venueModel->load((int)$request->getAttribute('id') ?? 0);

        if ($venue->id) {
            $this->venueModel->delete($venue);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/venues')
            ->withStatus(302);
     }

     public function viewDetails(Request $request, Response $response): Response {
        $venue = $this->venueModel->load((int)$request->getAttribute('id') ?? 0);

        if (!$venue->id) {
            return $response
                ->withHeader('Location', $this->basePath . '/venues')
                ->withStatus(302);
        }

        $html = $this->twig->render('REPLACELATER', [
            'venue' => $venue,
        ]);

        $response->getBody()->write($html);
        return $response;
     }
}