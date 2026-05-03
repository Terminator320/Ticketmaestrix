<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class CategoryModel
{
    public function findAll(): array
    {
        return R::findAll('categories', 'ORDER BY name ASC');
    }

    public function load(int $id): mixed
    {
        return R::load('categories', $id);
    }

    public function create(string $name): void
    {
        $bean = R::dispense('categories');
        $bean->name = $name;
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
