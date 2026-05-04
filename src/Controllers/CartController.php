<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Cart;
use App\Models\EventModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\TicketModel;
use App\Models\VenueModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RedBeanPHP\R;
use Twig\Environment;

/**
 * Mutates the session-backed cart and converts it into real orders +
 * order_items rows on checkout. Read-only cart rendering lives in
 * HomeController::showCart.
 */
class CartController
{
    public function __construct(
        private Environment     $twig,
        private TicketModel     $ticketModel,
        private EventModel      $eventModel,
        private VenueModel      $venueModel,
        private OrderModel      $orderModel,
        private OrderItemModel  $orderItemModel,
        private string          $basePath,
    ) {}

    /** POST /cart/add — add a ticket id to the session cart. */
    public function add(Request $request, Response $response): Response
    {
        $data     = $request->getParsedBody();
        $ticketId = (int) ($data['ticket_id'] ?? 0);
        $qty      = (int) ($data['quantity']  ?? 1);

        Cart::add($ticketId, max(1, $qty));

        return $response->withHeader('Location', $this->basePath . '/cart')->withStatus(302);
    }

    /** POST /cart/remove/{ticket_id} — remove a single line. */
    public function remove(Request $request, Response $response, array $args): Response
    {
        Cart::remove((int) ($args['ticket_id'] ?? 0));

        return $response
            ->withHeader('Location', $this->basePath . '/cart')
            ->withStatus(302);
    }

    /** POST /cart/clear — empty the cart entirely. */
    public function clear(Request $request, Response $response): Response
    {
        Cart::clear();

        return $response
            ->withHeader('Location', $this->basePath . '/cart')
            ->withStatus(302);
    }

    /**
     * POST /cart/expire — called by the client-side countdown when the timer
     * hits zero. Clears the cart and sends the user back to browse events.
     */
    public function expire(Request $request, Response $response): Response
    {
        Cart::clear();

        return $response
            ->withHeader('Location', $this->basePath . '/events')
            ->withStatus(302);
    }

    /**
     * POST /cart/checkout — convert the session cart into a real order.
     *
     * Login is required. Creates one orders row (status=1, paid) and one
     * order_items row per cart line, awards 10% of the total as points,
     * empties the cart, and bounces to /profile.
     *
     * Wraps everything in a single transaction so a partial failure
     * doesn't leave a half-checked-out order.
     */
    public function checkout(Request $request, Response $response): Response
    {
        if ($redirect = Auth::requireLogin($response, $this->basePath)) {
            return $redirect;
        }

        // Reject an expired cart before touching the DB.
        Cart::checkExpiry();

        $rows = Cart::hydrate($this->ticketModel, $this->eventModel, $this->venueModel);
        if (count($rows) === 0) {
            return $response
                ->withHeader('Location', $this->basePath . '/cart')
                ->withStatus(302);
        }

        $userId = (int) Auth::userId();
        $total  = Cart::subtotal($rows);

        R::begin();
        try {
            // Create the parent order with status=1 (paid).
            $order = R::dispense('orders');
            $order->total_price = $total;
            $order->status      = 1;
            $order->order_time  = date('Y-m-d H:i:s');
            $order->user_id     = $userId;
            $orderId = (int) R::store($order);

            // Persist each cart line as an order_item.
            foreach ($rows as $row) {
                $item = R::dispense('order_items');
                $item->quantity  = (int) $row['quantity'];
                $item->order_id  = $orderId;
                $item->ticket_id = (int) $row['ticket_id'];
                R::store($item);
            }

            // Award loyalty points: floor(10% of total).
            $user = R::load('users', $userId);
            if ($user->id) {
                $user->points = (int) ($user->points ?? 0) + (int) floor($total * 0.10);
                R::store($user);
            }

            R::commit();
        } catch (\Throwable $e) {
            R::rollback();
            throw $e;
        }

        // Empty the cart and send the user to their profile to see the new order.
        Cart::clear();
        return $response
            ->withHeader('Location', $this->basePath . '/profile')
            ->withStatus(302);
    }
}
