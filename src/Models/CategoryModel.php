<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\BeanHelper;
use RedBeanPHP\R;

class CategoryModel
{
    public function findAll(): array
    {
        return BeanHelper::castBeanArray(R::findAll('categories', 'ORDER BY name ASC'));
    }

    public function getAll(): array
    {
        return $this->findAll();
    }

    public function load(int $id): mixed
    {
        return BeanHelper::castBeanProperties(R::load('categories', $id));
    }

    public function getById(int $id): mixed
    {
        $bean = R::load('categories', $id);
        return BeanHelper::isValidBean($bean) ? BeanHelper::castBeanProperties($bean) : null;
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
