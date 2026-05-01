<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\BeanHelper;
use RedBeanPHP\R;

class TicketModel
{
    public function findByEvent(int $eventId): array
    {
        return BeanHelper::castBeanArray(R::find('ticket', 'event_id = ? ORDER BY `row`, seat', [$eventId]));
    }

    public function load(int $id): mixed
    {
        return BeanHelper::castBeanProperties(R::load('ticket', $id));
    }

    public function create(float $price, string $seat, string $row, int $eventId): void
    {
        $bean = R::dispense('ticket');
        $bean->price    = $price;
        $bean->seat     = $seat;
        $bean->row      = $row;
        $bean->event_id = $eventId;
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
