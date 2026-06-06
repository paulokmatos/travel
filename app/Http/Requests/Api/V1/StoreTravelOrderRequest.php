<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TravelOrder;
use App\Rules\ValidTravelDateRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreTravelOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', TravelOrder::class) ?? false;
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
        return [
            'destination' => ['required', 'string', 'min:2', 'max:255'],
            'departure_date' => ['required', 'date_format:Y-m-d', new ValidTravelDateRange],
            'return_date' => ['required', 'date_format:Y-m-d', new ValidTravelDateRange],
        ];
    }
}
