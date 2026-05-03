<?php

declare(strict_types=1);

namespace App\Models;

use RedBeanPHP\R;

class UserModel
{
    public function findAll(): array
    {
        return R::findAll('users');
    }

    public function load(int $id): mixed
    {
        return R::load('users', $id);
    }

    public function findByEmail(string $email): mixed
    {
        return R::findOne('users', 'email = ?', [$email]);
    }

    public function create(string $firstName, string $lastName, string $email,
                           string $password, string $phoneNumber, string $role = 'user'): void
    {
        $bean = R::dispense('users');
        $bean->first_name   = $firstName;
        $bean->last_name    = $lastName;
        $bean->email        = $email;
        $bean->password     = password_hash($password, PASSWORD_DEFAULT);
        $bean->phone_number = $phoneNumber;
        $bean->role         = $role;
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
