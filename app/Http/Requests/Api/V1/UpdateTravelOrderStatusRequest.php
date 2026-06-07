<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTravelOrderStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $travelOrder = $this->route('travel_order');

        return $travelOrder instanceof TravelOrder
            && ($this->user()?->can('updateStatus', $travelOrder) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(TravelOrderStatus::class)->only([
                    TravelOrderStatus::APPROVED,
                    TravelOrderStatus::CANCELED,
                ]),
            ],
        ];
    }
}
