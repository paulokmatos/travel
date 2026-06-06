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

    public function test_it_does_not_fail_when_a_companion_date_is_missing(): void
    {
        $messages = $this->failuresFor(
            attribute: 'departure_date',
            value: '2026-07-10',
            data: ['departure_date' => '2026-07-10'],
        );

        $this->assertSame([], $messages);
    }

    public function test_it_reports_full_payload_range_errors_on_return_date_only(): void
    {
        $messages = $this->failuresFor(
            attribute: 'departure_date',
            value: '2026-07-10',
            data: [
                'departure_date' => '2026-07-10',
                'return_date' => '2026-07-09',
            ],
        );

        $this->assertSame([], $messages);
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

    public function test_it_uses_existing_departure_date_for_partial_return_date_updates(): void
    {
        $travelOrder = new TravelOrder([
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-20',
        ]);

        $messages = $this->failuresFor(
            attribute: 'return_date',
            value: '2026-07-09',
            data: ['return_date' => '2026-07-09'],
            travelOrder: $travelOrder,
        );

        $this->assertSame(['The return date must be after or equal to the departure date.'], $messages);
    }

    public function test_it_ignores_malformed_dates_so_format_rules_can_report_them(): void
    {
        $messages = [
            ...$this->failuresFor(
                attribute: 'return_date',
                value: '2026-07-09-extra',
                data: [
                    'departure_date' => '2026-07-10',
                    'return_date' => '2026-07-09-extra',
                ],
            ),
            ...$this->failuresFor(
                attribute: 'return_date',
                value: 'extra-2026-07-09',
                data: [
                    'departure_date' => '2026-07-10',
                    'return_date' => 'extra-2026-07-09',
                ],
            ),
        ];

        $this->assertSame([], $messages);
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
