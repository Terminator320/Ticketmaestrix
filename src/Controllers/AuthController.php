<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class AuthController
{
    public function __construct(
        private Environment $twig,
        private UserModel $users,
        private string $basePath,
    ) {}

    public function showSignup(Request $request, Response $response): Response
    {
        return $this->render($response, 'signup.html.twig');
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'login.html.twig');
    }

    public function showForgotPassword(Request $request, Response $response): Response
    {
        return $this->render($response, 'forgot_password_p1.html.twig');
    }

    public function showVerificationCode(Request $request, Response $response): Response
    {
        return $this->render($response, 'forgot_password_p2.html.twig');
    }

    public function showNewPassword(Request $request, Response $response): Response
    {
        return $this->render($response, 'forgot_password_p3.html.twig');
    }

    private function render(Response $response, string $template): Response
    {
        $html = $this->twig->render($template, [
            'base_path' => $this->basePath,
        ]);

        $response->getBody()->write($html);

        return $response;
    }
}
