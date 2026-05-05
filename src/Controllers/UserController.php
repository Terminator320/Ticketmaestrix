<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\OrderModel;
use App\Models\TicketModel;
use App\Models\UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

/**
 * User-facing profile pages and admin-only user management.
 */
class UserController
{
    public function __construct(
        private Environment $twig,
        private UserModel   $userModel,
        private TicketModel $ticketModel,
        private OrderModel  $orderModel,
        private string      $basePath,
    ) {}

    /** POST /users — create a new user (admin form). */
    public function store(Request $request, Response $response): Response
    {
        $data = (array) ($request->getParsedBody() ?? []);

        $errors = [];
        if (empty($data['first_name'])) $errors['first_name'] = ['First name is required.'];
        if (empty($data['last_name']))  $errors['last_name']  = ['Last name is required.'];
        if (empty($data['email']))      $errors['email']      = ['Email is required.'];
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = ['Enter a valid email address.'];
        if (empty($data['password']))   $errors['password']   = ['Password is required.'];
        elseif (strlen($data['password']) < 8) $errors['password'] = ['Password must be at least 8 characters.'];

        if ($errors) {
            return $response
                ->withHeader('Location', $this->basePath . '/admin')
                ->withStatus(302);
        }

        $this->userModel->create([
            'first_name'   => $data['first_name'] ?? '',
            'last_name'    => $data['last_name'] ?? '',
            'email'        => $data['email'] ?? '',
            'password'     => $data['password'] ?? '',
            'phone_number' => $data['phone_number'] ?? '',
            'role'         => $data['role'] ?? 'user',
        ]);

        return $response
            ->withHeader('Location', $this->basePath . '/users')
            ->withStatus(302);
    }

    /** POST /users/{id}/role — toggle admin/user role. Admin-only. */
    public function roleToggle(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $user = $this->userModel->load((int) ($args['id'] ?? 0));

        if ($user->id) {
            $user->role = $user->role === 'admin' ? 'user' : 'admin';
            $this->userModel->save($user);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/users')
            ->withStatus(302);
    }

    /** POST /users/{id} — admin updates a user's details. */
    public function update(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $id   = (int) ($args['id'] ?? 0);
        $data = $request->getParsedBody();
        $this->userModel->update($id, $data);

        return $response
            ->withHeader('Location', $this->basePath . '/users')
            ->withStatus(302);
    }

    /** POST /users/{id}/delete — admin deletes a user. */
    public function delete(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $userId = (int) ($args['id'] ?? 0);

        $this->userModel->delete($userId);

        return $response->withHeader('Location', $this->basePath . '/users')->withStatus(302);
    }

    /** GET /users/{id} — admin views one user's detail page. */
    public function viewDetails(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $user = $this->userModel->load((int) ($args['id'] ?? 0));

        if (!$user->id) {
            return $response
                ->withHeader('Location', $this->basePath . '/users')
                ->withStatus(302);
        }

        $html = $this->twig->render('user/user_detail.html.twig', [
            'base_path'     => $this->basePath,
            'current_route' => 'profile',
            'user'          => $user,
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /** GET /users — admin-only listing of all users. */
    public function index(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $users = $this->userModel->findAll();

        $html = $this->twig->render('user/index.html.twig', [
            'base_path'     => $this->basePath,
            'current_route' => 'admin',
            'users'         => $users,
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /** GET /profile — the logged-in user's own profile page with stats. */
    public function showProfile(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireLogin($response, $this->basePath)) {
            return $redirect;
        }
        $user = Auth::user();
        $id   = (int) $user->id;

        $html = $this->twig->render('user/profile.html.twig', [
            'base_path'       => $this->basePath,
            'current_route'   => 'profile',
            'user'            => $user,
            'tickets_count'   => $this->ticketModel->countByOrderItemsForUser($id),
            'total_spent'     => number_format($this->orderModel->totalSpentByUser($id), 2, '.', ''),
            'events_attended' => $this->orderModel->eventsAttendedByUser($id),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /** GET /editprofile — show the form for editing the logged-in user. */
    public function editProfile(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireLogin($response, $this->basePath)) {
            return $redirect;
        }

        $html = $this->twig->render('user/edit_profile.html.twig', [
            'base_path'     => $this->basePath,
            'current_route' => 'profile',
            'user'          => Auth::user(),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * POST /editprofile — save the logged-in user's edits. Validates name
     * and email in-controller (nested user[*] keys can't use middleware).
     */
    public function updateProfile(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireLogin($response, $this->basePath)) {
            return $redirect;
        }

        $id   = (int) Auth::userId();
        $data = (array) ($request->getParsedBody() ?? []);
        $form = (array) ($data['user'] ?? []);

        $errors = [];
        if (empty($form['name']))  $errors['name']  = ['Full name is required.'];
        if (empty($form['email'])) $errors['email'] = ['Email is required.'];
        elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = ['Enter a valid email address.'];

        if ($errors) {
            $html = $this->twig->render('user/edit_profile.html.twig', [
                'base_path'     => $this->basePath,
                'current_route' => 'profile',
                'user'          => Auth::user(),
                'errors'        => $errors,
                'input'         => $form,
            ]);
            $response->getBody()->write($html);
            return $response->withStatus(422);
        }

        $name  = trim((string) ($form['name'] ?? ''));
        $email = trim((string) ($form['email'] ?? ''));

        // Split "First Last" into first_name + last_name.
        $parts     = explode(' ', $name, 2);
        $firstName = $parts[0] ?? '';
        $lastName  = $parts[1] ?? '';

        $this->userModel->update($id, [
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'email'        => $email,
            'phone_number' => (string) ($form['phone'] ?? ''),
        ]);

        return $response
            ->withHeader('Location', $this->basePath . '/profile')
            ->withStatus(302);
    }
}
