<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\EventModel;
use App\Models\TicketModel;
use App\Models\VenueModel;

/**
 * Session-backed shopping cart.
 *
 * The cart lives in $_SESSION['cart'] as a [ticket_id => quantity] map.
 * Hydration joins ticket -> event -> venue at render time so the session
 * payload stays small and stale data is impossible.
 */
class Cart
{
    /** Add a ticket to the cart, or bump its quantity if already present. */
    public static function add(int $ticketId, int $qty = 1): void
    {
        if ($ticketId <= 0 || $qty <= 0) {
            return;
        }
        $cart = $_SESSION['cart'] ?? [];
        $cart[$ticketId] = ($cart[$ticketId] ?? 0) + $qty;
        $_SESSION['cart'] = $cart;
    }

    /** Remove a single ticket id entirely (regardless of quantity). */
    public static function remove(int $ticketId): void
    {
        if (!isset($_SESSION['cart'])) {
            return;
        }
        unset($_SESSION['cart'][$ticketId]);
    }

    /** Empty the cart completely. */
    public static function clear(): void
    {
        unset($_SESSION['cart']);
    }

    /** Raw [ticket_id => qty] map. */
    public static function items(): array
    {
        return $_SESSION['cart'] ?? [];
    }

    /** Total number of tickets across all lines (for navbar badge). */
    public static function count(): int
    {
        return array_sum(self::items());
    }

    /**
     * Return cart rows shaped for cart.html.twig:
     *   { ticket_id, name, image, date, price, quantity, total }
     *
     * Walks each ticket -> event -> venue at call time. Skips silently
     * when a ticket or its event no longer exists (it was deleted while
     * sitting in someone's cart).
     */
    public static function hydrate(
        TicketModel $tickets,
        EventModel  $events,
        VenueModel  $venues
    ): array {
        $rows = [];
        foreach (self::items() as $ticketId => $qty) {
            $ticket = $tickets->getById((int) $ticketId);
            if ($ticket === null) {
                continue;
            }
            $event = $events->getById((int) $ticket->event_id);
            if ($event === null) {
                continue;
            }
            $price = (float) $ticket->price;
            $rows[] = [
                'ticket_id' => (int) $ticket->id,
                'name'      => (string) $event->title,
                'image'     => (string) $event->event_image,
                'date'      => (string) $event->date,
                'price'     => $price,
                'quantity'  => (int) $qty,
                'total'     => $price * (int) $qty,
            ];
        }
        return $rows;
    }

    /** Sum of `total` across hydrated rows. */
    public static function subtotal(array $hydrated): float
    {
        $sum = 0.0;
        foreach ($hydrated as $row) {
            $sum += (float) $row['total'];
        }
        return $sum;
    }
}
