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

    // --- ADDED FOR ADMIN CRUD ---
    public function getAllAdmins(): array
    {
        // Finds all users where the role is 'admin'
        return BeanHelper::castBeanArray(R::findAll('users', 'role = ?', ['admin']));
    }

    public function findByEmail(string $email): mixed
    {
        return R::findOne('users', 'email = ?', [$email]);
    }

    public function create(array $data): mixed
    {
        $bean = R::dispense('users');
        $bean->first_name   = $data['first_name'];
        $bean->last_name    = $data['last_name'];
        $bean->email        = $data['email'];
        $bean->password     = password_hash($data['password'], PASSWORD_DEFAULT);
        $bean->role         = $data['role'] ?? 'user';
        $bean->totp_secret  = $data['totp_secret'] ?? null;
        $bean->points       = 0;
        R::store($bean);
        return BeanHelper::castBeanProperties($bean);
    }

    public function save(mixed $bean): void
    {
        R::store($bean);
    }

    // --- UPDATED FOR CONVENIENCE ---
    public function deleteById(int $id): void
    {
        $user = R::load('users', $id);
        if ($user->id) {
            R::trash($user);
        }
    }

    /**
     * Updated to handle password changes and role management.
     */
    public function update(int $id, array $data): mixed
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
        
        
        // ADDED: Handle password updates specifically for the Admin Edit form
        if (!empty($data['password'])) {
            $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        R::store($user);
        return BeanHelper::castBeanProperties($user);
    }

    public function delete(int $id): void
{
    // R::load finds the 'user' bean by its primary key ID
    $user = R::load('users', $id);
    
    // If the user exists (id > 0), delete it from the database
    if ($user->id) {
        R::trash($user);
    }
}

    public function customerCount(): int
    {
        return (int) R::getCell(
            "SELECT COUNT(*) FROM users WHERE role = 'user'"
        );
    }
}