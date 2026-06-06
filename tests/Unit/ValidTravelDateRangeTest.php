<?php

namespace Tests\Unit;

use App\Models\TravelOrder;
use App\Rules\ValidTravelDateRange;
use PHPUnit\Framework\TestCase;

class ValidTravelDateRangeTest extends TestCase
{
    public function test_it_allows_return_date_equal_to_or_after_departure_date(): void
    {
        $messages = $this->failuresFor(
            attribute: 'return_date',
            value: '2026-07-10',
            data: [
                'departure_date' => '2026-07-10',
                'return_date' => '2026-07-10',
            ],
        );

        $this->assertSame([], $messages);
    }

    public function test_it_rejects_return_date_before_departure_date(): void
    {
        $messages = $this->failuresFor(
            attribute: 'return_date',
            value: '2026-07-09',
            data: [
                'departure_date' => '2026-07-10',
                'return_date' => '2026-07-09',
            ],
        );

        $this->assertSame(['The return date must be after or equal to the departure date.'], $messages);
    }

    public function test_it_uses_existing_travel_order_dates_for_partial_updates(): void
    {
        $travelOrder = new TravelOrder([
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-20',
        ]);

        $messages = $this->failuresFor(
            attribute: 'departure_date',
            value: '2026-07-21',
            data: ['departure_date' => '2026-07-21'],
            travelOrder: $travelOrder,
        );

        $this->assertSame(['The return date must be after or equal to the departure date.'], $messages);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function failuresFor(string $attribute, mixed $value, array $data, ?TravelOrder $travelOrder = null): array
    {
        $messages = [];
        $rule = (new ValidTravelDateRange($travelOrder))->setData($data);

        $rule->validate($attribute, $value, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        return $messages;
    }
}
