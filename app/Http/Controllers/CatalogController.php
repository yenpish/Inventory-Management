<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    private function model(string $kind): Model
    {
        return match ($kind) {
            'categories' => new ItemCategory, 'locations' => new Location, 'items' => new Item
        };
    }

    public function create(string $kind): View
    {
        return $this->form($kind, $this->model($kind));
    }

    public function edit(string $kind, int $record): View
    {
        return $this->form($kind, $this->model($kind)->newQuery()->findOrFail($record));
    }

    private function form(string $kind, Model $record): View
    {
        return view('inventory.record-form', [
            'kind' => $kind, 'record' => $record,
            'hasStockHistory' => $record instanceof Item && $record->exists && ($record->stockBalances()->exists() || $record->stockMovements()->exists()),
            'categories' => ItemCategory::withTrashed()->where(function (Builder $query) use ($record): void {
                $query->whereNull('deleted_at')->orWhere('id', $record->getAttribute('item_category_id'));
            })->orderBy('name')->get(),
            'locations' => Location::withTrashed()->where(function (Builder $query) use ($record): void {
                $query->whereNull('deleted_at')->orWhere('id', $record->getAttribute('parent_id'));
            })->when($kind === 'locations' && $record->exists, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))->orderBy('name')->get(),
        ]);
    }

    public function store(CatalogRequest $request, string $kind): RedirectResponse
    {
        return $this->save($request, $kind);
    }

    public function update(CatalogRequest $request, string $kind, int $record): RedirectResponse
    {
        return $this->save($request, $kind, $record);
    }

    private function save(CatalogRequest $request, string $kind, ?int $id = null): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $kind, $id): void {
                $record = $id === null ? $this->model($kind) : $this->model($kind)->newQuery()->lockForUpdate()->findOrFail($id);
                $record->fill($request->validated());
                if ($record instanceof Item && $record->exists && $record->isDirty(['tracking_type', 'unit_of_measure']) && ($record->stockBalances()->exists() || $record->stockMovements()->exists())) {
                    throw ValidationException::withMessages(['tracking_type' => 'The item type and unit cannot change after stock has been recorded.']);
                }
                $record->save();
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['code' => 'This code is already in use, including archived records.']);
        }

        $label = match ($kind) {
            'categories' => 'Category', 'locations' => 'Location', 'items' => 'Item'
        };

        return to_route($kind.'.index')->with('status', $label.' “'.$request->validated('name').'” '.($id === null ? 'created.' : 'updated.'));
    }
}
