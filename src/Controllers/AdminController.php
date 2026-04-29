<?php

namespace App\Controllers;

use App\Models\UserModel as UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class AdminController {
    public function __construct(
        private Environment $twig,
        private UserModel $userModel,
        private string $basePath,
    ) {}

    public function showAdminDashboard(Request $request, Response $response): Response {
        $html = $this->twig->render('admin_dashboard.html.twig', [
            'base_path' => $this->basePath,
        ]);

        $response->getBody()->write($html);

        return $response;
    }
}