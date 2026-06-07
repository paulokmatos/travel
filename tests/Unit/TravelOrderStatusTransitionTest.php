<?php

namespace Tests\Unit;

use App\Enums\TravelOrderStatus;
use App\Support\TravelOrderStatusTransition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TravelOrderStatusTransitionTest extends TestCase
{
    public function test_requested_orders_can_be_approved_or_canceled(): void
    {
        $transition = new TravelOrderStatusTransition;

        $this->assertTrue($transition->canTransition(TravelOrderStatus::REQUESTED, TravelOrderStatus::APPROVED));
        $this->assertTrue($transition->canTransition(TravelOrderStatus::REQUESTED, TravelOrderStatus::CANCELED));
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transitions_raise_conflict(TravelOrderStatus $from, TravelOrderStatus $to): void
    {
        $transition = new TravelOrderStatusTransition;

        $this->assertFalse($transition->canTransition($from, $to));

        $this->expectException(ConflictHttpException::class);

        $transition->assertCanTransition($from, $to);
    }

    /**
     * @return array<string, array{0: TravelOrderStatus, 1: TravelOrderStatus}>
     */
    public static function invalidTransitions(): array
    {
        return [
            'requested to requested' => [TravelOrderStatus::REQUESTED, TravelOrderStatus::REQUESTED],
            'approved to canceled' => [TravelOrderStatus::APPROVED, TravelOrderStatus::CANCELED],
            'canceled to approved' => [TravelOrderStatus::CANCELED, TravelOrderStatus::APPROVED],
        ];
    }
}
