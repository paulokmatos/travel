<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TravelOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTravelOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $travelOrder = $this->route('travel_order');

        return $travelOrder instanceof TravelOrder
            && ($this->user()?->can('update', $travelOrder) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destination' => ['sometimes', 'required', 'string', 'max:255'],
            'departure_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'return_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $fields = ['destination', 'departure_date', 'return_date'];

                if (! collect($fields)->contains(fn (string $field): bool => $this->has($field))) {
                    $validator->errors()->add('travel_order', 'At least one travel order field must be provided.');

                    return;
                }

                $travelOrder = $this->route('travel_order');

                if (! $travelOrder instanceof TravelOrder) {
                    return;
                }

                $departureDate = $this->input('departure_date', $travelOrder->departure_date->toDateString());
                $returnDate = $this->input('return_date', $travelOrder->return_date->toDateString());

                if ($returnDate < $departureDate) {
                    $validator->errors()->add('return_date', 'The return date must be after or equal to the departure date.');
                }
            },
        ];
    }
}
