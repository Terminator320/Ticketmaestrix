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
    ) {
        $this->twig = $twig;
        $this->userModel = $userModel;
        $this->basePath = $basePath;
    }

     public function store(Request $request, Response $response): Response {
       $data = $request->getParsedBody();

       $this->userModel->create($data);

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

     public function update(Request $request, Response $response, array $args): Response {
        $id = (int)$args['id'];
        $data = $request->getParsedBody();

        $this->userModel->update($id, $data);

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