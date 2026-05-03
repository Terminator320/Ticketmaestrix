<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
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
        return $this->render($response, 'auth/signup.html.twig');
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.html.twig');
    }

    public function showForgotPassword(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/forgot_password_p1.html.twig');
    }

    public function showVerificationCode(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/forgot_password_p2.html.twig');
    }

    public function showNewPassword(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/forgot_password_p3.html.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        // Read submitted credentials with safe defaults so a missing field
        // doesn't crash the controller.
        $data     = $request->getParsedBody();
        $email    = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        // Look up the user and verify the password against the stored hash.
        $user = $this->users->findByEmail($email);

        if ($user && $user->id && password_verify($password, $user->password)) {
            // Mark the session as authenticated, then send them home.
            Auth::login((int) $user->id);
            return $response
                ->withHeader('Location', $this->basePath . '/')
                ->withStatus(302);
        }

        // Re-render the login page with an error and a 401 status.
        $html = $this->twig->render('auth/login.html.twig', [
            'base_path' => $this->basePath,
            'error'     => 'Invalid email or password.',
        ]);
        $response->getBody()->write($html);
        return $response->withStatus(401);
    }

    public function signup(Request $request, Response $response): Response
    {
        $data      = $request->getParsedBody();
        $fullname  = trim((string) ($data['fullname'] ?? ''));
        $parts     = explode(' ', $fullname, 2);
        $firstName = $parts[0] ?? '';
        $lastName  = $parts[1] ?? '';

        $this->users->create([
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'email'        => (string) ($data['email'] ?? ''),
            'password'     => (string) ($data['password'] ?? ''),
            'phone_number' => (string) ($data['phone_number'] ?? ''),
            'role'         => 'user',
        ]);

        return $response
            ->withHeader('Location', $this->basePath . '/login')
            ->withStatus(302);
    }

    /**
     * Wipe the session and bounce the user back to the home page.
     * Called from POST /logout (the navbar's logout form).
     */
    public function logout(Request $request, Response $response): Response
    {
        Auth::logout();
        return $response
            ->withHeader('Location', $this->basePath . '/')
            ->withStatus(302);
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
