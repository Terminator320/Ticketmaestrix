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
 * Admin landing page. Aggregates site-wide stats, lists all events,
 * and manages administrative users.
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
     * GET /admin — The main dashboard
     * Displays stats, events, and the admin management list.
     */
    public function showAdminDashboard(Request $request, Response $response): Response
    {
        // Hard guard: non-admins get bounced.
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }

        // 1. Aggregate site-wide stats
        $stats = [
            'revenue'       => $this->orderModel->getTotalRevenue(),
            'tickets_sold'  => $this->orderItemModel->totalQuantitySold(),
            'active_events' => $this->eventModel->countActive(),
            'customers'     => $this->userModel->customerCount(),
        ];

        // 2. Fetch and hydrate events for the "My Events" tab
        $events = $this->eventModel->hydrate(
            $this->eventModel->getAll(),
            $this->venueModel,
            $this->ticketModel
        );

        // 3. Fetch all admins for the "Manage Admins" tab
        $admins = $this->userModel->getAllAdmins();

        $html = $this->twig->render('admin/admin_dashboard.html.twig', [
            'base_path'     => $this->basePath,
            'current_route' => 'admin',
            'admin_user'    => Auth::user(),
            'stats'         => $stats,
            'events'        => $events,
            'admins'        => $admins,
            'categories'    => $this->categoryModel->getAll(),
            'venues'        => $this->venueModel->getAll(),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function createAdmin(Request $request, Response $response): Response
    {
        if (!Auth::isAdmin()) {
            return $response->withHeader('Location', $this->basePath . '/admin')->withStatus(302);
        }

        $data = $request->getParsedBody();

        $this->userModel->create([
            'first_name'   => $data['first_name'] ?? '',
            'last_name'    => $data['last_name'] ?? '',
            'email'        => $data['email'] ?? '',
            'password'     => $data['password'] ?? '',
            'role'         => 'admin',
        ]);

        return $response->withHeader('Location', $this->basePath . '/admin')->withStatus(302);
    }

    /**
     * POST /admin/users/{id}/edit — Process the admin update via AJAX
     */
    public function updateAdmin(Request $request, Response $response, array $args): Response
    {
        if ($redirect = Auth::requireAdmin($response, $this->basePath)) {
            return $redirect;
        }

        $adminId = (int)$args['id'];
        $data = $request->getParsedBody();

        $updateData = [
            'first_name'   => $data['first_name'] ?? '',
            'last_name'    => $data['last_name'] ?? '',
            'email'        => $data['email'] ?? '',
        ];

        // Only update password if a new one was provided
        if (!empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        $this->userModel->update($adminId, $updateData);

        return $response->withHeader('Location', $this->basePath . '/admin')->withStatus(302);
    }

    public function deleteAdmin(Request $request, Response $response, array $args): Response
    {
        if (!Auth::isAdmin() || (int)$args['id'] === Auth::user()->id) {
            return $response->withHeader('Location', $this->basePath . '/admin')->withStatus(302);
        }

        $this->userModel->delete((int)$args['id']);

        return $response->withHeader('Location', $this->basePath . '/admin')->withStatus(302);
    }
}