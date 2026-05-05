<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\VenueModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class VenueController
{
    public function __construct(
        private Environment $twig,
        private VenueModel $venueModel,
        private string $basePath,
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $html = $this->twig->render('venue/index.html.twig', [
            'base_path' => $this->basePath,
            'venues'    => $this->venueModel->getAll(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function create(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $html = $this->twig->render('venue/create.html.twig', [
            'base_path' => $this->basePath,
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
        if (empty($data['name']))     $errors['name']     = ['Name is required.'];
        if (empty($data['address']))  $errors['address']  = ['Address is required.'];
        if (empty($data['capacity'])) $errors['capacity'] = ['Capacity is required.'];
        elseif (!is_numeric($data['capacity']) || (int)$data['capacity'] <= 0) $errors['capacity'] = ['Capacity must be a positive number.'];

        if ($errors) {
            $html = $this->twig->render('venue/create.html.twig', [
                'base_path' => $this->basePath,
                'errors'    => $errors,
                'input'     => $data,
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(422);
        }

        $this->venueModel->create(
            (string) ($data['name'] ?? ''),
            (string) ($data['description'] ?? ''),
            (string) ($data['image_url'] ?? ''),
            (string) ($data['address'] ?? ''),
            (int) ($data['capacity'] ?? 0),
        );

        return $response->withHeader('Location', $this->basePath . '/venues')->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $venue = $this->venueModel->getById((int) $args['id']);

        if (!$venue) {
            return $response->withHeader('Location', $this->basePath . '/venues')->withStatus(302);
        }

        $html = $this->twig->render('venue/edit.html.twig', [
            'base_path' => $this->basePath,
            'venue'     => $venue,
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
        if (empty($data['name']))     $errors['name']     = ['Name is required.'];
        if (empty($data['address']))  $errors['address']  = ['Address is required.'];
        if (empty($data['capacity'])) $errors['capacity'] = ['Capacity is required.'];
        elseif (!is_numeric($data['capacity']) || (int)$data['capacity'] <= 0) $errors['capacity'] = ['Capacity must be a positive number.'];

        if ($errors) {
            $venue = $this->venueModel->getById($id);
            $html  = $this->twig->render('venue/edit.html.twig', [
                'base_path' => $this->basePath,
                'venue'     => $venue,
                'errors'    => $errors,
                'input'     => $data,
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(422);
        }

        $venue = $this->venueModel->load($id);

        if ($venue->id) {
            $venue->name        = (string) ($data['name'] ?? $venue->name);
            $venue->description = (string) ($data['description'] ?? $venue->description);
            $venue->image_url   = (string) ($data['image_url'] ?? $venue->image_url);
            $venue->address     = (string) ($data['address'] ?? $venue->address);
            $venue->capacity    = (int) ($data['capacity'] ?? $venue->capacity);
            $this->venueModel->save($venue);
        }

        return $response->withHeader('Location', $this->basePath . '/venues')->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $venue = $this->venueModel->load((int) $args['id']);
        if ($venue->id) {
            $this->venueModel->delete($venue);
        }
        return $response->withHeader('Location', $this->basePath . '/venues')->withStatus(302);
    }

    public function viewDetails(Request $request, Response $response, array $args): Response
    {
        $venue = $this->venueModel->getById((int) $args['id']);

        if (!$venue) {
            return $response->withHeader('Location', $this->basePath . '/venues')->withStatus(302);
        }

        $html = $this->twig->render('venue/venue_detail.html.twig', [
            'base_path' => $this->basePath,
            'venue'     => $venue,
        ]);
        $response->getBody()->write($html);
        return $response;
    }
}
