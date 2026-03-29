<?php

namespace Webkul\Commercial\Enums;

enum AccountProductStatus: string
{
    case LEAD = 'lead';
    case PROSPECT = 'prospect';
    case OPPORTUNITY = 'opportunity';
    case CUSTOMER = 'customer';
    case INACTIVE_CUSTOMER = 'inactive_customer';
    case FORMER_CUSTOMER = 'former_customer';
    case LOST = 'lost';

    /**
     * Returns the human-readable label for the status.
     */
    public function label(): string
    {
        return match($this) {
            self::LEAD             => 'Lead',
            self::PROSPECT         => 'Prospect',
            self::OPPORTUNITY      => 'Oportunidade',
            self::CUSTOMER         => 'Cliente',
            self::INACTIVE_CUSTOMER => 'Cliente Inativo',
            self::FORMER_CUSTOMER  => 'Ex-Cliente',
            self::LOST             => 'Perdido',
        };
    }

    /**
     * Returns whether the entity currently has an active commercial relationship.
     */
    public function isActive(): bool
    {
        return $this === self::CUSTOMER;
    }

    /**
     * Returns all status values as an array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
