<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\BeanHelper;
use RedBeanPHP\R;

class OrderItemModel
{
    public function findByOrder(int $orderId): array
    {
        return BeanHelper::castBeanArray(R::find('orderitem', 'order_id = ?', [$orderId]));
    }

    public function load(int $id): mixed
    {
        return BeanHelper::castBeanProperties(R::load('orderitem', $id));
    }

    public function create(int $quantity, int $orderId, int $ticketId): void
    {
        $bean = R::dispense('orderitem');
        $bean->quantity  = $quantity;
        $bean->order_id  = $orderId;
        $bean->ticket_id = $ticketId;
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
     * Total tickets sold across all paid orders. Drives the
     * "Tickets Sold" admin stat card.
     */
    public function totalQuantitySold(): int
    {
        $sql = 'SELECT COALESCE(SUM(oi.quantity), 0)
                  FROM orderitem oi
                  JOIN orders o ON o.id = oi.order_id
                 WHERE o.status > 0';
        return (int) R::getCell($sql);
    }
}
