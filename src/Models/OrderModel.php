<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\BeanHelper;
use RedBeanPHP\R;

class OrderModel
{
    public function findByUser(int $userId): array
    {
        return BeanHelper::castBeanArray(R::find('orders', 'user_id = ? ORDER BY order_time DESC', [$userId]));
    }

    public function load(int $id): mixed
    {
        return BeanHelper::castBeanProperties(R::load('orders', $id));
    }

    public function create(float $totalPrice, int $userId): void
    {
        $bean = R::dispense('orders');
        $bean->total_price = $totalPrice;
        $bean->status      = 0;
        $bean->order_time  = date('Y-m-d H:i:s');
        $bean->user_id     = $userId;
        R::store($bean);
    }

    public function save(mixed $bean): void
    {
        R::store($bean);
    }

    public function delete(mixed $bean): void
    {
        R::trash($bean);
    }

    /**
     * All orders, newest first. Used by the admin events tab indirectly
     * and any future admin-orders listing.
     */
    public function findAll(): array
    {
        return BeanHelper::castBeanArray(
            R::findAll('orders', 'ORDER BY order_time DESC')
        );
    }

    /**
     * Site-wide revenue from paid orders only (status > 0). Drives the
     * "Total Revenue" admin card.
     */
    public function getTotalRevenue(): float
    {
        return (float) R::getCell(
            'SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status > 0'
        );
    }

    /**
     * Total dollars one user has spent on paid orders. Drives the
     * "Total Spent" stat on the profile page.
     */
    public function totalSpentByUser(int $userId): float
    {
        return (float) R::getCell(
            'SELECT COALESCE(SUM(total_price), 0)
               FROM orders
              WHERE user_id = ? AND status > 0',
            [$userId]
        );
    }

    /**
     * Number of distinct events the user has tickets for, via paid orders.
     * Powers the "Events Attended" stat on the profile page.
     */
    public function eventsAttendedByUser(int $userId): int
    {
        $sql = 'SELECT COUNT(DISTINCT t.event_id)
                  FROM order_items oi
                  JOIN orders o  ON o.id = oi.order_id
                  JOIN ticket t  ON t.id = oi.ticket_id
                 WHERE o.user_id = ? AND o.status > 0';
        return (int) R::getCell($sql, [$userId]);
    }
}
