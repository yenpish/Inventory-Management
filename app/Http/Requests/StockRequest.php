<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'quantity' => ['required', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'submission_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['item_id' => 'stock item', 'location_id' => 'location', 'submission_key' => 'submission'];
    }
}
