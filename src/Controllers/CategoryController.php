<?php

namespace App\Controllers;

use App\Models\CategoryModel as CategoryModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class CategoryController {
    public function __construct(
        private Environment $twig,
        private CategoryModel $categoryModel,
        private string $basePath,
    ) {
        $this->twig = $twig;
        $this->categoryModel = $categoryModel;
        $this->basePath = $basePath;
    }

     public function store(Request $request, Response $response): Response {
       $data = $request->getParsedBody();

       $this->categoryModel->create((string) ($data['name'] ?? ''));

        return $response
            ->withHeader('Location', $this->basePath . '/categories')
            ->withStatus(302);
     }

     public function update(Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        $category = $this->categoryModel->load($id);

        if ($category->id) {
            $category->name = (string) ($data['name'] ?? $category->name);
            $this->categoryModel->save($category);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/categories')
            ->withStatus(302);

     }

     public function delete(Request $request, Response $response): Response {
        $category = $this->categoryModel->load((int)$request->getAttribute('id') ?? 0);

        if ($category->id) {
            $this->categoryModel->delete($category);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/categories')
            ->withStatus(302);
     }

     public function viewDetails(Request $request, Response $response): Response {
        $category = $this->categoryModel->load((int)$request->getAttribute('id') ?? 0);

        if (!$category->id) {
            return $response
                ->withHeader('Location', $this->basePath . '/categories')
                ->withStatus(302);
        }

        $html = $this->twig->render('REPLACELATER', [
            'category' => $category,
        ]);

        $response->getBody()->write($html);
        return $response;
     }
}