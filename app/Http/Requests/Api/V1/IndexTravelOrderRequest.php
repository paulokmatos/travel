<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TravelOrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexTravelOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'status' => ['sometimes', Rule::enum(TravelOrderStatus::class)],
            'destination' => ['sometimes', 'string', 'max:255'],
            'created_from' => ['sometimes', 'date_format:Y-m-d'],
            'created_to' => ['sometimes', 'date_format:Y-m-d'],
            'travel_from' => ['sometimes', 'date_format:Y-m-d'],
            'travel_to' => ['sometimes', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
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

                if ($this->filled(['created_from', 'created_to']) && $this->input('created_to') < $this->input('created_from')) {
                    $validator->errors()->add('created_to', 'The created to date must be after or equal to the created from date.');
                }

                if ($this->filled(['travel_from', 'travel_to']) && $this->input('travel_to') < $this->input('travel_from')) {
                    $validator->errors()->add('travel_to', 'The travel to date must be after or equal to the travel from date.');
                }
            },
        ];
    }
}
