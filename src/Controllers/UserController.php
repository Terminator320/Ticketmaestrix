<?php

namespace App\Controllers;

use App\Models\UserModel as UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class UserController {
    public function __construct(
        private Environment $twig,
        private UserModel $userModel,
        private string $basePath,
    ) {}

     public function store(Request $request, Response $response): Response {
        $data = (array) $request->getParsedBody();
        $user = trim($data['user'] ?? '');

        if ($user !== '') {
            $this->userModel->create(
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['password'],
                $user['phone_number']
            );
        }

        return $response
            ->withHeader('Location', $this->basePath . '/users')
            ->withStatus(302);
     }

     public function roleToggle(Request $request, Response $response): Response {
        $user = $this->userModel-> load((int)$request->getAttribute('id') ?? 0);

        if ($user->id) {
            $user->role = $user->role === 'admin' ? 'user' : 'admin';
            $this->userModel->save($user);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/users')
            ->withStatus(302);
     }

     public function update(Request $request, Response $response): Response {
        $user = $this->userModel->load((int)$request->getAttribute('id') ?? 0);

        if ($user->id) {
            $data = (array) $request->getParsedBody();
            $userData = trim($data['user'] ?? '');

            if ($userData !== '') {
                $user->first_name = $userData['first_name'];
                $user->last_name = $userData['last_name'];
                $user->email = $userData['email'];
                $user->phone_number = $userData['phone_number'];

                if (!empty($userData['password'])) {
                    $user->password = password_hash($userData['password'], PASSWORD_DEFAULT);
                }

                $this->userModel->save($user);
            }
        }

        return $response
            ->withHeader('Location', $this->basePath . '/users')
            ->withStatus(302);

     }

     public function delete(Request $request, Response $response): Response {
        $user = $this->userModel->load((int)$request->getAttribute('id') ?? 0);

        if ($user->id) {
            $this->userModel->delete($user);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/users')
            ->withStatus(302);
     }

     public function viewDetails(Request $request, Response $response): Response {
        $user = $this->userModel->load((int)$request->getAttribute('id') ?? 0);

        if (!$user->id) {
            return $response
                ->withHeader('Location', $this->basePath . '/users')
                ->withStatus(302);
        }

        $html = $this->twig->render('REPLACELATER', [
            'user' => $user,
        ]);

        $response->getBody()->write($html);
        return $response;
     }

     public function showProfile(Request $request, Response $response): Response {
        $user = $this->userModel->load((int)$request->getAttribute('id') ?? 0);

        if (!$user->id) {
            return $response
                ->withHeader('Location', $this->basePath . '/users')
                ->withStatus(302);
        }

        $html = $this->twig->render('profile.html.twig', [
            'user' => $user,
        ]);

        $response->getBody()->write($html);
        return $response;
     }

     public function showProfile($request, $response) {
    return $this->twig->render($response, 'profile.html.twig', [
        'current_route' => 'profile'
    ]);
    }

    public function editProfile(Request $request, Response $response): Response {
    $id = (int)$request->getAttribute('id') ?? 0;
    $user = $this->userModel->load($id);

    if (!$user || !$user->id) {
        return $response->withHeader('Location', $this->basePath . '/')->withStatus(302);
    }

    $html = $this->twig->render('edit_profile.html.twig', [
        'user' => $user,
        'current_route' => 'profile',
        'base_path' => $this->basePath
    ]);

    $response->getBody()->write($html);
    return $response;
    }
}