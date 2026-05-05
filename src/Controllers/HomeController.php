<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Cart;
use App\Models\CategoryModel;
use App\Models\EventModel;
use App\Models\OrderItemModel;
use App\Models\TicketModel;
use App\Models\VenueModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

/**
 * Public-facing landing pages: home grid, events-near-you map, and the
 * shopping cart. None of these require login, but cart checkout (handled
 * by CartController) does.
 */
class HomeController
{
    public function __construct(
        private Environment     $twig,
        private EventModel      $eventModel,
        private CategoryModel   $categoryModel,
        private TicketModel     $ticketModel,
        private VenueModel      $venueModel,
        private OrderItemModel  $orderItemModel,
        private string          $basePath,
    ) {}

    /**
     * Home page: featured upcoming events on top, the rest in a grid below,
     * dynamic category pills, and live counts in the hero badges.
     */
    public function index(Request $request, Response $response): Response
    {
        // Pull all upcoming events once, then split into featured (first 3)
        // and the remainder. One DB hit instead of two with overlap risk.
        $upcoming = $this->eventModel->getUpcoming();
        $featured = array_slice($upcoming, 0, 3);
        $rest     = array_slice($upcoming, 3);

        // Hydrate both lists with venue + min_price so the cards have
        // everything they need without lookups in Twig.
        $featured = $this->eventModel->hydrate($featured, $this->venueModel, $this->ticketModel);
        $rest     = $this->eventModel->hydrate($rest,     $this->venueModel, $this->ticketModel);

        $html = $this->twig->render('home/home.html.twig', [
            'base_path'     => $this->basePath,
            'current_route' => 'home',
            'featured'      => $featured,
            'events'        => $rest,
            'categories'    => $this->categoryModel->getAll(),
            'live_count'    => $this->eventModel->countActive(),
            'on_sale_count' => count($featured),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Cart page: hydrates the session cart at render time so prices and
     * names are always fresh.
     */
    public function showCart(Request $request, Response $response): Response
    {
        Cart::checkExpiry();

        $cart     = Cart::hydrate($this->ticketModel, $this->eventModel, $this->venueModel);
        $subtotal = Cart::subtotal($cart);

        $userId      = Auth::userId();
        $userPoints  = 0;
        $maxDiscount = 0;

        if ($userId) {
            $user = Auth::user();
            $userPoints  = (int) ($user->points ?? 0);
            $maxDiscount = (int) floor($subtotal * 100);
        }

        $html = $this->twig->render('home/cart.html.twig', [
            'base_path'         => $this->basePath,
            'current_route'     => 'cart',
            'cart'              => $cart,
            'subtotal'          => $subtotal,
            'total'             => $subtotal,
            'points_earned'     => (int) floor($subtotal * 0.10),
            'user_points'       => $userPoints,
            'max_discount'      => min($userPoints, $maxDiscount),
            'seconds_remaining' => Cart::secondsRemaining(),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Map page: shows upcoming events in the sidebar list with their
     * venue + cheapest ticket price.
     */
    public function showMap(Request $request, Response $response): Response
    {
        $events = $this->eventModel->hydrate(
            $this->eventModel->getUpcoming(),
            $this->venueModel,
            $this->ticketModel
        );

        $html = $this->twig->render('home/map.html.twig', [
            'base_path'     => $this->basePath,
            'current_route' => 'map',
            'events'        => $events,
        ]);

        $response->getBody()->write($html);
        return $response;
    }
}
