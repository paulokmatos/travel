<?php

namespace App\Enums;

enum TravelOrderStatus: string
{
    case REQUESTED = 'solicitado';
    case APPROVED = 'aprovado';
    case CANCELED = 'cancelado';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
