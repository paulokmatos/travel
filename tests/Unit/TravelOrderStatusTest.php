<?php

namespace Tests\Unit;

use App\Enums\TravelOrderStatus;
use PHPUnit\Framework\TestCase;

class TravelOrderStatusTest extends TestCase
{
    public function test_cases_use_english_uppercase_names_and_portuguese_values(): void
    {
        $cases = [];

        foreach (TravelOrderStatus::cases() as $status) {
            $cases[$status->name] = $status->value;
        }

        $this->assertSame([
            'REQUESTED' => 'solicitado',
            'APPROVED' => 'aprovado',
            'CANCELED' => 'cancelado',
        ], $cases);
    }
}
