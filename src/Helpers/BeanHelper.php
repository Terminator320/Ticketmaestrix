<?php

declare(strict_types=1);

namespace App\Helpers;

class BeanHelper
{
    /**
     * Convert bean properties to proper types
     */
    public static function castBeanProperties(\RedBeanPHP\OODBBean $bean): \RedBeanPHP\OODBBean
    {
        // Cast ID to integer
        if (isset($bean->id)) {
            $bean->id = (int) $bean->id;
        }
        
        // Cast common integer fields used across models
        $intFields = [
            'category_id',
            'venue_id',
            'user_id',
            'order_id',
            'ticket_id',
            'event_id',
            'quantity',
            'capacity',
        ];
        
        foreach ($intFields as $field) {
            if (isset($bean->$field)) {
                $bean->$field = (int) $bean->$field;
            }
        }
        
        return $bean;
    }
    
    /**
     * Cast array of beans
     */
    public static function castBeanArray(array $beans): array
    {
        return array_map([self::class, 'castBeanProperties'], $beans);
    }
    
    /**
     * Safe integer casting from mixed input
     */
    public static function toInt(mixed $value): int
    {
        return (int) $value;
    }
    
    /**
     * Check if bean exists and has valid ID
     */
    public static function isValidBean(\RedBeanPHP\OODBBean $bean): bool
    {
        return isset($bean->id) && $bean->id > 0;
    }
}
