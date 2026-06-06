<?php

namespace App\Rules;

use App\Models\TravelOrder;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTravelDateRange implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(private readonly ?TravelOrder $travelOrder = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $departureDate = $this->dateValue('departure_date', $attribute, $value);
        $returnDate = $this->dateValue('return_date', $attribute, $value);

        if ($departureDate === null || $returnDate === null) {
            return;
        }

        if ($returnDate >= $departureDate) {
            return;
        }

        if ($attribute === 'return_date' || ! array_key_exists('return_date', $this->data)) {
            $fail('The return date must be after or equal to the departure date.');
        }
    }

    private function dateValue(string $field, string $attribute, mixed $value): ?string
    {
        $rawValue = $field === $attribute ? $value : ($this->data[$field] ?? null);

        if ($rawValue === null && $this->travelOrder !== null) {
            $rawValue = match ($field) {
                'departure_date' => $this->travelOrder->departure_date->toDateString(),
                'return_date' => $this->travelOrder->return_date->toDateString(),
                default => null,
            };
        }

        if (! is_string($rawValue) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawValue) !== 1) {
            return null;
        }

        return $rawValue;
    }
}
