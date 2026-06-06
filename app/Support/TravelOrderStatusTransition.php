<?php

namespace App\Support;

use App\Enums\TravelOrderStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TravelOrderStatusTransition
{
    public function canTransition(TravelOrderStatus $from, TravelOrderStatus $to): bool
    {
        if ($from !== TravelOrderStatus::Solicitado) {
            return false;
        }

        return in_array($to, [TravelOrderStatus::Aprovado, TravelOrderStatus::Cancelado], true);
    }

    public function assertCanTransition(TravelOrderStatus $from, TravelOrderStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new ConflictHttpException('Only requested travel orders can be approved or canceled.');
        }
    }
}
