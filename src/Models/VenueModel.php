<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\BeanHelper;
use RedBeanPHP\R;

class VenueModel
{
    public function findAll(): array
    {
        return BeanHelper::castBeanArray(R::findAll('venue', 'ORDER BY name ASC'));
    }

    public function load(int $id): mixed
    {
        return BeanHelper::castBeanProperties(R::load('venue', $id));
    }

    public function create(string $name, string $description, string $imageUrl,
                           string $address, int $capacity): void
    {
        $bean = R::dispense('venue');
        $bean->name        = $name;
        $bean->description = $description;
        $bean->image_url   = $imageUrl;
        $bean->address     = $address;
        $bean->capacity    = $capacity;
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
