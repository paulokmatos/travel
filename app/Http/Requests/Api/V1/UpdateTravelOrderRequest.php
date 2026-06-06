<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TravelOrder;
use App\Rules\ValidTravelDateRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('destination'))) {
            $this->merge([
                'destination' => Str::squish($this->input('destination')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $travelOrder = $this->route('travel_order');
        $travelOrder = $travelOrder instanceof TravelOrder ? $travelOrder : null;

        return [
            'destination' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'departure_date' => ['sometimes', 'required', 'date_format:Y-m-d', new ValidTravelDateRange($travelOrder)],
            'return_date' => ['sometimes', 'required', 'date_format:Y-m-d', new ValidTravelDateRange($travelOrder)],
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
            },
        ];
    }
}
