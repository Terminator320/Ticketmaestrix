<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\CategoryModel;
use App\Models\EventModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\TicketModel;
use App\Models\UserModel;
use App\Models\VenueModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

/**
 * Admin landing page. Aggregates site-wide stats, lists all events for
 * management, and exposes a "create event" form populated from real
 * categories and venues.
 */
class AdminController
{
    public function __construct(
        private Environment     $twig,
        private UserModel       $userModel,
        private EventModel      $eventModel,
        private OrderModel      $orderModel,
        private OrderItemModel  $orderItemModel,
        private CategoryModel   $categoryModel,
        private VenueModel      $venueModel,
        private TicketModel     $ticketModel,
        private string          $basePath,
    ) {}

    /**
     * GET /admin — admin-only dashboard with revenue/tickets/events/customers
     * cards, an event list, and a create form.
     */
    public function showAdminDashboard(Request $request, Response $response): Response
    {
        // Hard guard: non-admins (and logged-out users) get bounced.
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }

        // Aggregate site-wide stats (one COUNT/SUM each).
        $stats = [
            'revenue'       => $this->orderModel->getTotalRevenue(),
            'tickets_sold'  => $this->orderItemModel->totalQuantitySold(),
            'active_events' => $this->eventModel->countActive(),
            'customers'     => $this->userModel->customerCount(),
        ];

        // Full event list for the "My Events" tab — admin manages everything.
        $events = $this->eventModel->hydrate(
            $this->eventModel->getAll(),
            $this->venueModel,
            $this->ticketModel
        );

        $html = $this->twig->render('admin/admin_dashboard.html.twig', [
            'base_path'     => $this->basePath,
            'current_route' => 'admin',
            'admin_user'    => Auth::user(),
            'stats'         => $stats,
            'events'        => $events,
            'categories'    => $this->categoryModel->getAll(),
            'venues'        => $this->venueModel->getAll(),
        ]);

        $response->getBody()->write($html);
        return $response;
    }
}
