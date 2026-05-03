<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class OrderModel
{
    public function findByUser(int $userId): array
    {
        return R::find('orders', 'user_id = ? ORDER BY order_time DESC', [$userId]);
    }

    public function load(int $id): mixed
    {
        return R::load('orders', $id);
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
}
