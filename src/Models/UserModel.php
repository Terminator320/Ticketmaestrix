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

    /**
     * Apply a partial update to a user row. Only known editable fields are
     * copied — silently ignores any other keys in $data so the form can
     * include extras (birthday, bio, etc.) without breaking.
     *
     * Returns the cast bean on success, or null if the id was not found.
     */
    public function update(int $id, array $data): ?\RedBeanPHP\OODBBean
    {
        $user = R::load('users', $id);
        if (!BeanHelper::isValidBean($user)) {
            return null;
        }

        if (array_key_exists('first_name', $data)) {
            $user->first_name = (string) $data['first_name'];
        }
        if (array_key_exists('last_name', $data)) {
            $user->last_name = (string) $data['last_name'];
        }
        if (array_key_exists('email', $data)) {
            $user->email = (string) $data['email'];
        }
        if (array_key_exists('phone_number', $data)) {
            $user->phone_number = (string) $data['phone_number'];
        }

        R::store($user);
        return BeanHelper::castBeanProperties($user);
    }

    /**
     * Number of non-admin users in the system. Drives the
     * "Total Customers" admin stat card.
     */
    public function customerCount(): int
    {
        return (int) R::getCell(
            "SELECT COUNT(*) FROM users WHERE role = 'user'"
        );
    }
}
