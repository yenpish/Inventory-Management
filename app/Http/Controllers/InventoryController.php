<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryFilterRequest;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class InventoryController extends Controller
{
    public function dashboard(): View
    {
        return view('inventory.dashboard', [
            'metrics' => [
                'items' => Item::count(),
                'categories' => ItemCategory::count(),
                'locations' => Location::count(),
                'movements' => StockMovement::count(),
            ],
            'stockItems' => Item::where('tracking_type', 'stock')->count(),
            'assetItems' => Item::where('tracking_type', 'asset')->count(),
            'positiveBalances' => StockBalance::where('quantity', '>', 0)->count(),
            'zeroBalances' => StockBalance::where('quantity', 0)->count(),
            'recentMovements' => StockMovement::with(['item', 'location', 'user'])->latest('id')->limit(5)->get(),
            'categoryOverview' => ItemCategory::withCount('items')->orderByDesc('items_count')->orderBy('name')->limit(5)->get(),
        ]);
    }

    public function items(InventoryFilterRequest $request): View
    {
        $query = $this->applyStatus(Item::query(), $request->validated('status') ?? 'current');
        $search = trim($request->validated('q') ?? '');
        $query->with('category')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when($request->validated('category'), fn (Builder $query, string $id) => $query->where('item_category_id', $id))
            ->when($request->validated('tracking_type'), fn (Builder $query, string $type) => $query->where('tracking_type', $type));

        return view('inventory.items', [
            'items' => $query->orderBy('code')->orderBy('id')->paginate(20)->withQueryString(),
            'categoryOptions' => ItemCategory::withTrashed()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function categories(InventoryFilterRequest $request): View
    {
        $query = $this->applyStatus(ItemCategory::query(), $request->validated('status') ?? 'current');
        $search = trim($request->validated('q') ?? '');
        $query->withCount('items')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')));

        return view('inventory.categories', [
            'categories' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function locations(InventoryFilterRequest $request): View
    {
        $query = $this->applyStatus(Location::query(), $request->validated('status') ?? 'current');
        $search = trim($request->validated('q') ?? '');
        $query->with('parent')->withCount('children')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')));

        return view('inventory.locations', [
            'locations' => $query->orderBy('code')->orderBy('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function balances(InventoryFilterRequest $request): View
    {
        $query = StockBalance::with(['item', 'location']);
        $search = trim($request->validated('q') ?? '');
        $query->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->whereHas('item', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->orWhereHas('location', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))))
            ->when($request->validated('location'), fn (Builder $query, string $id) => $query->where('location_id', $id))
            ->when($request->validated('item'), fn (Builder $query, string $id) => $query->where('item_id', $id))
            ->when($request->validated('balance') === 'positive', fn (Builder $query) => $query->where('quantity', '>', 0))
            ->when($request->validated('balance') === 'zero', fn (Builder $query) => $query->where('quantity', 0));

        return view('inventory.balances', [
            'balances' => $query->orderBy('item_id')->orderBy('location_id')->paginate(20)->withQueryString(),
            'locationOptions' => Location::withTrashed()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function movements(InventoryFilterRequest $request): View
    {
        $query = StockMovement::with(['item', 'location', 'user']);
        $search = trim($request->validated('q') ?? '');
        $query->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('reference', 'like', '%'.$search.'%')->orWhere('reason', 'like', '%'.$search.'%')
            ->orWhereHas('item', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))))
            ->when($request->validated('type'), fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($request->validated('location'), fn (Builder $query, string $id) => $query->where('location_id', $id))
            ->when($request->validated('item'), fn (Builder $query, string $id) => $query->where('item_id', $id))
            ->when($request->validated('from'), fn (Builder $query, string $date) => $query->where(
                'occurred_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Kuala_Lumpur')->utc(),
            ))
            ->when($request->validated('to'), fn (Builder $query, string $date) => $query->where(
                'occurred_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Kuala_Lumpur')->addDay()->utc(),
            ));

        return view('inventory.movements', [
            'movements' => $query->latest('id')->paginate(20)->withQueryString(),
            'locationOptions' => Location::withTrashed()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    private function applyStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'archived' => $query->onlyTrashed(),
            'all' => $query->withTrashed(),
            default => $query,
        };
    }
}
