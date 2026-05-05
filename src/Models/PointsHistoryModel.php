<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class PointsHistoryModel
{
    public function addPoints(int $userId, int $amount, string $description, ?int $orderId = null): void
    {
        $bean = R::dispense('pointshistory');
        $bean->user_id     = $userId;
        $bean->order_id    = $orderId;
        $bean->amount      = $amount;
        $bean->description = $description;
        $bean->created_at  = date('Y-m-d H:i:s');
        R::store($bean);
    }

    public function findByUser(int $userId, int $limit = 50): array
    {
        return R::findAll(
            'pointshistory',
            'user_id = ? ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public function getTotal(int $userId): int
    {
        return (int) R::getCell(
            'SELECT COALESCE(SUM(amount), 0) FROM pointshistory WHERE user_id = ?',
            [$userId]
        );
    }
}
