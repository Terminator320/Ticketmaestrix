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

    // Brute-force protection thresholds — adjust here if needed.
    private const MAX_ATTEMPTS    = 5;    // failed attempts before lockout kicks in
    private const LOCKOUT_SECONDS = 900;  // 15 minutes

    public function login(Request $request, Response $response): Response
    {
        // Guard against brute force: if a lockout timestamp exists in the session
        // and it hasn't expired yet, block the request immediately without touching
        // the database or even reading the POST body.
        $lockedUntil = (int) ($_SESSION['login_lockout_until'] ?? 0);
        if ($lockedUntil > time()) {
            $remaining = (int) ceil(($lockedUntil - time()) / 60);
            $html = $this->twig->render('auth/login.html.twig', [
                'base_path' => $this->basePath,
                'error'     => "Too many failed attempts. Try again in {$remaining} minute(s).",
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(429);
        }

        $data     = (array) ($request->getParsedBody() ?? []);
        $email    = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $user     = $this->users->findByEmail($email);

        if ($user && $user->id && password_verify($password, $user->password)) {
            // Successful login — reset the attempt counter so a user who previously
            // had one bad attempt isn't penalised on their next visit.
            unset($_SESSION['login_attempts'], $_SESSION['login_lockout_until']);
            Auth::login((int) $user->id);
            return $response
                ->withHeader('Location', $this->basePath . '/')
                ->withStatus(302);
        }

        // Wrong credentials — increment the session counter.
        // When the threshold is hit, record the lockout expiry and drop the counter
        // (the expiry timestamp is all we need from this point on).
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= self::MAX_ATTEMPTS) {
            $_SESSION['login_lockout_until'] = time() + self::LOCKOUT_SECONDS;
            unset($_SESSION['login_attempts']);
            $html = $this->twig->render('auth/login.html.twig', [
                'base_path' => $this->basePath,
                'error'     => 'Too many failed attempts. Try again in 15 minutes.',
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(429);
        }

        // Tell the user how many tries they have left so they know the limit exists.
        $attemptsLeft = self::MAX_ATTEMPTS - $_SESSION['login_attempts'];
        $html = $this->twig->render('auth/login.html.twig', [
            'base_path' => $this->basePath,
            'error'     => "Invalid email or password. {$attemptsLeft} attempt(s) remaining.",
            'input'     => $data,
        ]);
        $response->getBody()->write($html);
        return $response->withStatus(401);
    }

    public function signup(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = [];
        $email     = trim((string) ($data['email'] ?? ''));
        $password  = (string) ($data['password'] ?? '');
        $password2 = (string) ($data['password2'] ?? '');

        if (empty($data['fullname'])) $errors['fullname'] = ['Full name is required.'];
        if (empty($email)) {
            $errors['email'] = ['Email is required.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Enter a valid email address.'];
        } elseif (!isset($errors['fullname']) && $this->users->findByEmail($email)) {
            // Only hit the DB once required fields pass — avoids unnecessary query on partially-empty forms.
            $errors['email'] = ['This email is already registered.'];
        }
        if (empty($password)) $errors['password'] = ['Password is required.'];
        elseif (strlen($password) < 8) $errors['password'] = ['Password must be at least 8 characters.'];
        if (empty($password2)) $errors['password2'] = ['Please confirm your password.'];
        elseif ($password !== $password2) $errors['password2'] = ['Passwords do not match.'];

        if ($errors) {
            $html = $this->twig->render('auth/signup.html.twig', [
                'base_path' => $this->basePath,
                'errors'    => $errors,
                'input'     => $data,
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(422);
        }

        $fullname  = trim((string) ($data['fullname'] ?? ''));
        $parts     = explode(' ', $fullname, 2);
        $firstName = $parts[0] ?? '';
        $lastName  = $parts[1] ?? '';

        $this->users->create([
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'email'        => $email,
            'password'     => $password,
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
