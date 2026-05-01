<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;
use App\Helpers\BeanHelper;

class UserModel
{
    public function findAll(): array
    {
        return BeanHelper::castBeanArray(R::findAll('users'));
    }

    public function load(int $id): mixed
    {
        return R::load('users', $id);
    }

    public function findByEmail(string $email): mixed
    {
        return R::findOne('users', 'email = ?', [$email]);
    }

    public function create(array $data): \RedBeanPHP\OODBBean
    {
        $bean = R::dispense('users');
        $bean->first_name   = $data['first_name'];
        $bean->last_name    = $data['last_name'];
        $bean->email        = $data['email'];
        $bean->password     = password_hash($data['password'], PASSWORD_DEFAULT);
        $bean->phone_number = $data['phone_number'];
        $bean->role         = $data['role'] ?? 'user';
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

    public function update(int $id, array $data): ?\RedBeanPHP\OODBBean
    {
        $user = R::load('users', $id);
        if (!BeanHelper::isValidBean($user)) {
            return null;
        }

        

        return BeanHelper::castBeanProperties($user);
    }
}
