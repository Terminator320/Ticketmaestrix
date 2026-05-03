<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\CategoryModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class CategoryController
{
    public function __construct(
        private Environment $twig,
        private CategoryModel $categoryModel,
        private string $basePath,
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $html = $this->twig->render('category/index.html.twig', [
            'base_path'  => $this->basePath,
            'categories' => $this->categoryModel->getAll(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function create(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $html = $this->twig->render('category/create.html.twig', [
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
        $data = $request->getParsedBody();
        $this->categoryModel->create((string) ($data['name'] ?? ''));
        return $response->withHeader('Location', $this->basePath . '/categories')->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $category = $this->categoryModel->getById((int) $args['id']);

        if (!$category) {
            return $response->withHeader('Location', $this->basePath . '/categories')->withStatus(302);
        }

        $html = $this->twig->render('category/edit.html.twig', [
            'base_path' => $this->basePath,
            'category'  => $category,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $id       = (int) $args['id'];
        $data     = $request->getParsedBody();
        $category = $this->categoryModel->load($id);

        if ($category->id) {
            $category->name = (string) ($data['name'] ?? $category->name);
            $this->categoryModel->save($category);
        }

        return $response->withHeader('Location', $this->basePath . '/categories')->withStatus(302);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }
        $category = $this->categoryModel->load((int) $args['id']);
        if ($category->id) {
            $this->categoryModel->delete($category);
        }
        return $response->withHeader('Location', $this->basePath . '/categories')->withStatus(302);
    }

    public function viewDetails(Request $request, Response $response, array $args): Response
    {
        $category = $this->categoryModel->getById((int) $args['id']);

        if (!$category) {
            return $response->withHeader('Location', $this->basePath . '/categories')->withStatus(302);
        }

        $html = $this->twig->render('category/category_detail.html.twig', [
            'base_path' => $this->basePath,
            'category'  => $category,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

   public function showByCategory(Request $request, Response $response, array $args): Response {
    $categoryId = $args['id'];

    $events = $this->db->table('events')->where('category_id', $categoryId)->get();
    $category = $this->db->table('categories')->where('id', $categoryId)->first();

    return $this->view->render($response, 'events_by_category.html.twig', [
        'events' => $events,
        'category' => $category
    ]);
}
}
