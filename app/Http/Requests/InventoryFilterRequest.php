<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['current', 'active', 'inactive', 'archived', 'all'])],
            'tracking_type' => ['nullable', Rule::in(['stock', 'asset'])],
            'category' => ['nullable', 'integer', Rule::exists('item_categories', 'id')],
            'location' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'item' => ['nullable', 'integer', Rule::exists('items', 'id')],
            'type' => ['nullable', Rule::in(['receipt', 'issue', 'adjustment'])],
            'balance' => ['nullable', Rule::in(['positive', 'zero'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), ['after_or_equal:from'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'q' => 'search text',
            'tracking_type' => 'item type',
            'type' => 'transaction',
            'balance' => 'stock level',
            'from' => 'from date',
            'to' => 'to date',
        ];
    }
}
