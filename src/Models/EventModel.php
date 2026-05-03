<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\BeanHelper;
use RedBeanPHP\R;

class EventModel
{
    public function findAll(): array
    {
        return BeanHelper::castBeanArray(R::findAll('events', 'ORDER BY date ASC'));
    }

    public function getAll(): array
    {
        return $this->findAll();
    }

    public function load(int $id): mixed
    {
        return BeanHelper::castBeanProperties(R::load('events', $id));
    }

    public function getById(int $id): mixed
    {
        $bean = R::load('events', $id);
        return BeanHelper::isValidBean($bean) ? BeanHelper::castBeanProperties($bean) : null;
    }

    public function findByCategory(int $categoryId): array
    {
        return BeanHelper::castBeanArray(R::find('events', 'category_id = ? ORDER BY date ASC', [$categoryId]));
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

    /**
     * All future-or-now events ordered by date.
     *
     * Used by the home page "All Events" grid and the map sidebar.
     */
    public function getUpcoming(): array
    {
        return BeanHelper::castBeanArray(
            R::find('events', 'date >= NOW() ORDER BY date ASC')
        );
    }

    /**
     * The next $limit upcoming events. Powers the "Tickets On Sale"
     * featured row on the home page.
     */
    public function getUpcomingFeatured(int $limit = 3): array
    {
        return BeanHelper::castBeanArray(
            R::find('events', 'date >= NOW() ORDER BY date ASC LIMIT ?', [$limit])
        );
    }

    /**
     * Count of events whose date is today or later. Drives the
     * "Live Events" hero stat and the admin "Active Events" card.
     */
    public function countActive(): int
    {
        return (int) R::getCell('SELECT COUNT(*) FROM events WHERE date >= NOW()');
    }

    /**
     * Decorate event beans with venue_name, venue_address, and min_price
     * so listing templates have everything they need without per-row
     * lookups in Twig. Returns a new array; does not mutate the input.
     */
    public function hydrate(array $events, VenueModel $venues, TicketModel $tickets): array
    {
        $out = [];
        foreach ($events as $event) {
            $venue = $venues->getById((int) ($event->venue_id ?? 0));
            $event->venue_name    = $venue ? (string) $venue->name : '';
            $event->venue_address = $venue ? (string) $venue->address : '';
            $event->min_price     = $tickets->minPriceForEvent((int) $event->id);
            $out[] = $event;
        }
        return $out;
    }


public function getPaginated($limit, $offset) {
    return R::findAll('events', ' LIMIT ? OFFSET ? ', [$limit, $offset]);
}

public function countAll() {
    return R::count('events');
}
}
