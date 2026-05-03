<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class EventModel
{
    public function findAll(): array
    {
        return R::findAll('events', 'ORDER BY date ASC');
    }

    public function load(int $id): mixed
    {
        return R::load('events', $id);
    }

    public function findByCategory(int $categoryId): array
    {
        return R::find('events', 'category_id = ? ORDER BY date ASC', [$categoryId]);
    }

    public function create(string $title, string $description, string $date,
                           int $venueId, int $categoryId, string $eventImage): void
    {
        $bean = R::dispense('events');
        $bean->title       = $title;
        $bean->description = $description;
        $bean->date        = $date;
        $bean->venue_id    = $venueId;
        $bean->category_id = $categoryId;
        $bean->event_image = $eventImage;
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
