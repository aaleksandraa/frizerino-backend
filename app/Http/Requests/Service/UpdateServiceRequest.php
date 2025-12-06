<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\BaseRequest;

class UpdateServiceRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->id === $this->salon->owner_id || $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'sometimes|integer|min:5|max:480',
            'price' => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'category' => 'sometimes|string|max:255',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'exists:staff,id',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
