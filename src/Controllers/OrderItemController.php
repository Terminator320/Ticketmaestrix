<?php

namespace App\Controllers;

use App\Models\OrderItemModel as OrderItemModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class OrderItemController {
    public function __construct(
        private Environment $twig,
        private OrderItemModel $orderItemModel,
        private string $basePath,
    ) {
        $this->twig = $twig;
        $this->orderItemModel = $orderItemModel;
        $this->basePath = $basePath;
    }

     public function store(Request $request, Response $response): Response {
       $data = $request->getParsedBody();

       $this->orderItemModel->create(
           (int) ($data['quantity'] ?? 0),
           (int) ($data['order_id'] ?? 0),
           (int) ($data['ticket_id'] ?? 0)
       );

        return $response
            ->withHeader('Location', $this->basePath . '/order-items')
            ->withStatus(302);
     }

     public function update(Request $request, Response $response, array $args): Response {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        $orderItem = $this->orderItemModel->load($id);

        if ($orderItem->id) {
            $orderItem->quantity = (int) ($data['quantity'] ?? $orderItem->quantity);
            $orderItem->order_id = (int) ($data['order_id'] ?? $orderItem->order_id);
            $orderItem->ticket_id = (int) ($data['ticket_id'] ?? $orderItem->ticket_id);
            $this->orderItemModel->save($orderItem);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/order-items')
            ->withStatus(302);

     }

     // Hard-delete one order_item by id.
     public function delete(Request $request, Response $response, array $args): Response {
        $orderItem = $this->orderItemModel->load((int) ($args['id'] ?? 0));

        if ($orderItem->id) {
            $this->orderItemModel->delete($orderItem);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/order-items')
            ->withStatus(302);
     }

     // Show one order_item's detail page.
     public function viewDetails(Request $request, Response $response, array $args): Response {
        $orderItem = $this->orderItemModel->load((int) ($args['id'] ?? 0));

        if (!$orderItem->id) {
            return $response
                ->withHeader('Location', $this->basePath . '/order-items')
                ->withStatus(302);
        }

        $html = $this->twig->render('order-item/order_item_detail.html.twig', [
            'base_path' => $this->basePath,
            'orderItem' => $orderItem,
        ]);

        $response->getBody()->write($html);
        return $response;
     }

     // List all line items belonging to a given order id.
     public function byOrder(Request $request, Response $response, array $args): Response {
        $orderId    = (int) ($args['id'] ?? 0);
        $orderItems = $this->orderItemModel->findByOrder($orderId);

        $html = $this->twig->render('order-item/order_items_by_order.html.twig', [
            'base_path'   => $this->basePath,
            'order_items' => $orderItems,
            'order_id'    => $orderId,
        ]);

        $response->getBody()->write($html);
        return $response;
     }
}