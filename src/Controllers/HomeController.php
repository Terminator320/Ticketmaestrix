<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class HomeController
{
    public function __construct(
        private Environment $twig,
        private string $basePath,
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $html = $this->twig->render('home.html.twig', [
            'base_path' => $this->basePath,
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    public function showCart(Request $request, Response $response): Response
    {
        $html = $this->twig->render('cart.html.twig', [
            'base_path' => $this->basePath,
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    public function showMap(Request $request, Response $response): Response
    {
        $html = $this->twig->render('map.html.twig', [
            'base_path' => $this->basePath,
            'events' => $this->eventModel->getEventsNearYou(),
        ]);

        $response->getBody()->write($html);

        return $response;
    }
}
