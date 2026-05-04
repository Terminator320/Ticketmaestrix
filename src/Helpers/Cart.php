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

        // Wipe a stale cart before adding so the old items don't carry over.
        self::checkExpiry();

        $wasEmpty = empty($_SESSION['cart'] ?? []);
        $cart = $_SESSION['cart'] ?? [];
        $cart[$ticketId] = ($cart[$ticketId] ?? 0) + $qty;
        $_SESSION['cart'] = $cart;

        // Start the 5-minute window on the very first ticket added.
        if ($wasEmpty) {
            $_SESSION['cart_expires_at'] = time() + 300;
        }
    }

    /** Remove a single ticket id entirely (regardless of quantity). */
    public static function remove(int $ticketId): void
    {
        if (!isset($_SESSION['cart'])) {
            return;
        }
        unset($_SESSION['cart'][$ticketId]);
    }

    /** Empty the cart and its expiry timestamp completely. */
    public static function clear(): void
    {
        unset($_SESSION['cart']);
        unset($_SESSION['cart_expires_at']);
    }

    /** Raw [ticket_id => qty] map. */
    public static function items(): array
    {
        return $_SESSION['cart'] ?? [];
    }

    /** Returns true if a cart expiry is set and has passed. */
    public static function isExpired(): bool
    {
        return isset($_SESSION['cart_expires_at']) && time() > $_SESSION['cart_expires_at'];
    }

    /**
     * Clears the cart if the 5-minute window has expired.
     * Call this at the top of any method that reads or writes cart state.
     */
    public static function checkExpiry(): void
    {
        if (self::isExpired()) {
            self::clear();
        }
    }

    /** Seconds until the cart expires. Returns 0 if no expiry is set or already past. */
    public static function secondsRemaining(): int
    {
        return max(0, (int) (($_SESSION['cart_expires_at'] ?? 0) - time()));
    }

    /** Total number of tickets across all lines (for navbar badge). */
    public static function count(): int
    {
        self::checkExpiry();
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
        self::checkExpiry();
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
