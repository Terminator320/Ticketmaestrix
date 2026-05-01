<?php

namespace App\Controllers;

use App\Models\OrderModel as OrderModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class OrderController {
    public function __construct(
        private Environment $twig,
        private OrderModel $orderModel,
        private string $basePath,
    ) {
        $this->twig = $twig;
        $this->orderModel = $orderModel;
        $this->basePath = $basePath;
    }

     public function store(Request $request, Response $response): Response {
       $data = $request->getParsedBody();

       $this->orderModel->create(
           (float) ($data['total_price'] ?? 0),
           (int) ($data['user_id'] ?? 0)
       );

        return $response
            ->withHeader('Location', $this->basePath . '/orders')
            ->withStatus(302);
     }

     public function update(Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        $order = $this->orderModel->load($id);

        if ($order->id) {
            $order->total_price = (float) ($data['total_price'] ?? $order->total_price);
            $order->status = (int) ($data['status'] ?? $order->status);
            $order->user_id = (int) ($data['user_id'] ?? $order->user_id);
            $order->order_time = (string) ($data['order_time'] ?? $order->order_time);
            $this->orderModel->save($order);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/orders')
            ->withStatus(302);

     }

     public function delete(Request $request, Response $response): Response {
        $order = $this->orderModel->load((int)$request->getAttribute('id') ?? 0);

        if ($order->id) {
            $this->orderModel->delete($order);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/orders')
            ->withStatus(302);
     }

     public function viewDetails(Request $request, Response $response): Response {
        $order = $this->orderModel->load((int)$request->getAttribute('id') ?? 0);

        if (!$order->id) {
            return $response
                ->withHeader('Location', $this->basePath . '/orders')
                ->withStatus(302);
        }

        $html = $this->twig->render('REPLACELATER', [
            'order' => $order,
        ]);

        $response->getBody()->write($html);
        return $response;
     }

     public function byUser(Request $request, Response $response): Response {
        $userId = (int) ($request->getQueryParams()['user'] ?? $request->getAttribute('id') ?? 0);
        $orders = $this->orderModel->findByUser($userId);

        $html = $this->twig->render('REPLACELATER', [
            'orders' => $orders,
            'user_id' => $userId,
        ]);

        $response->getBody()->write($html);
        return $response;
     }
}