<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\BeanHelper;
use RedBeanPHP\R;

class OrderItemModel
{
    public function findByOrder(int $orderId): array
    {
        return BeanHelper::castBeanArray(R::find('order_items', 'order_id = ?', [$orderId]));
    }

    public function load(int $id): mixed
    {
        return BeanHelper::castBeanProperties(R::load('order_items', $id));
    }

    public function create(int $quantity, int $orderId, int $ticketId): void
    {
        $bean = R::dispense('order_items');
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
}
