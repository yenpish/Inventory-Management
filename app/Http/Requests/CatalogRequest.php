<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $kind = $this->route('kind');
        $model = match ($kind) {
            'categories' => new ItemCategory, 'locations' => new Location, default => new Item
        };
        $record = $this->route('record');
        $rules = [
            'code' => ['required', 'string', 'max:255', Rule::unique($model->getTable(), 'code')->ignore($record)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ];
        if ($kind === 'items') {
            $rules += ['item_category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')->where(function (Builder $query) use ($record): void {
                $current = $record ? Item::findOrFail($record)->item_category_id : null;
                $query->where(function (Builder $query) use ($current): void {
                    $query->whereNull('deleted_at')->orWhere('id', $current);
                });
            })], 'tracking_type' => ['required', Rule::in(['stock', 'asset'])], 'unit_of_measure' => ['required', 'string', 'max:255']];
        }
        if ($kind === 'locations') {
            $rules['parent_id'] = ['nullable', 'integer', Rule::exists('locations', 'id')->where(function (Builder $query) use ($record): void {
                $current = $record ? Location::findOrFail($record)->parent_id : null;
                $query->where(function (Builder $query) use ($current): void {
                    $query->whereNull('deleted_at')->orWhere('id', $current);
                });
            })];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['item_category_id' => 'category', 'parent_id' => 'parent location', 'is_active' => 'active status', 'tracking_type' => 'item type', 'unit_of_measure' => 'unit of measure'];
    }
}
