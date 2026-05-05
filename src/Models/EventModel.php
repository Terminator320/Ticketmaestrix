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
                           int $venueId, int $categoryId, string $eventImage): mixed
    {
        $bean = R::dispense('events');
        $bean->title       = $title;
        $bean->description = $description;
        $bean->date        = $date;
        $bean->venue_id    = $venueId;
        $bean->category_id = $categoryId;
        $bean->event_image = $eventImage;
        R::store($bean);
        return BeanHelper::castBeanProperties($bean);
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
     * Decorate event beans with venue_name, venue_address, category_name, and min_price
     * so listing templates have everything they need without per-row
     * lookups in Twig. Returns a new array of stdClass objects with extra properties.
     * 
     * Note: Returns stdClass objects instead of modifying frozen beans directly.
     */
    public function hydrate(array $events, VenueModel $venues, TicketModel $tickets, ?CategoryModel $categories = null): array
    {
        $out = [];
        foreach ($events as $event) {
            // Create a new stdClass to hold all properties (avoids frozen bean issues)
            $hydrated = new \stdClass();
            
            // Copy all existing bean properties
            foreach ($event as $key => $value) {
                $hydrated->$key = $value;
            }
            
            // Add venue information
            $venue = $venues->getById((int) ($hydrated->venue_id ?? 0));
            $hydrated->venue_name    = $venue ? (string) $venue->name : '';
            $hydrated->venue_address = $venue ? (string) $venue->address : '';
            
            // Add ticket pricing
            $hydrated->min_price = $tickets->minPriceForEvent((int) ($hydrated->id ?? 0));
            
            // Add category name if category model is provided
            if ($categories !== null && isset($hydrated->category_id)) {
                $category = $categories->getById((int) $hydrated->category_id);
                $hydrated->category = $category ? (string) $category->name : 'Uncategorized';
            } elseif (!isset($hydrated->category)) {
                $hydrated->category = 'Uncategorized';
            }
            
            $out[] = $hydrated;
        }
        return $out;
    }


public function getPaginated($limit, $offset) {
    return R::findAll('events', ' LIMIT ? OFFSET ? ', [$limit, $offset]);
}

public function countAll() {
    return R::count('events');
}

/**
 * Search and filter events by query string, category, and venue.
 * Returns paginated results.
 */
public function search(array $filters, int $limit, int $offset): array
{
    $where = [];
    $bindings = [];
    
    // Search by query string (title or description)
    if (!empty($filters['q'])) {
        $searchTerm = '%' . $filters['q'] . '%';
        $where[] = '(title LIKE ? OR description LIKE ?)';
        $bindings[] = $searchTerm;
        $bindings[] = $searchTerm;
    }
    
    // Filter by category
    if (!empty($filters['category'])) {
        $where[] = 'category_id = ?';
        $bindings[] = (int) $filters['category'];
    }
    
    // Filter by venue
    if (!empty($filters['venue'])) {
        $where[] = 'venue_id = ?';
        $bindings[] = (int) $filters['venue'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT * FROM events 
              $whereClause 
              ORDER BY date ASC 
              LIMIT ? OFFSET ?";
    
    $bindings[] = $limit;
    $bindings[] = $offset;
    
    $rawResults = R::getAll($query, $bindings);
    
    // Convert raw arrays to stdClass objects for compatibility with hydrate()
    $results = [];
    foreach ($rawResults as $row) {
        $obj = new \stdClass();
        foreach ($row as $key => $value) {
            $obj->$key = $value;
        }
        $results[] = $obj;
    }
    
    return $results;
}

/**
 * Count events matching the search criteria.
 */
public function countSearch(array $filters): int
{
    $where = [];
    $bindings = [];
    
    // Search by query string (title or description)
    if (!empty($filters['q'])) {
        $searchTerm = '%' . $filters['q'] . '%';
        $where[] = '(title LIKE ? OR description LIKE ?)';
        $bindings[] = $searchTerm;
        $bindings[] = $searchTerm;
    }
    
    // Filter by category
    if (!empty($filters['category'])) {
        $where[] = 'category_id = ?';
        $bindings[] = (int) $filters['category'];
    }
    
    // Filter by venue
    if (!empty($filters['venue'])) {
        $where[] = 'venue_id = ?';
        $bindings[] = (int) $filters['venue'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT COUNT(*) FROM events $whereClause";
    
    return (int) R::getCell($query, $bindings);
}
}
