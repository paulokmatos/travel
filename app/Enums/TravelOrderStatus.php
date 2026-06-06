<?php

namespace App\Enums;

enum TravelOrderStatus: string
{
    case Solicitado = 'solicitado';
    case Aprovado = 'aprovado';
    case Cancelado = 'cancelado';

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
