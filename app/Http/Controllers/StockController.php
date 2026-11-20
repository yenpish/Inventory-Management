<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockRequest;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\QuantityFormatter;
use App\StockManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class StockController extends Controller
{
    public function create(string $operation): View
    {
        $items = Item::where('tracking_type', 'stock')->where('is_active', true)->orderBy('name')->get();
        $locations = Location::where('is_active', true)->orderBy('name')->get();
        $availability = StockBalance::whereIn('item_id', $items->modelKeys())
            ->whereIn('location_id', $locations->modelKeys())
            ->get(['item_id', 'location_id', 'quantity'])
            ->mapWithKeys(fn (StockBalance $balance): array => [$balance->item_id.':'.$balance->location_id => QuantityFormatter::format($balance->quantity)])
            ->all();

        return view('inventory.stock-form', [
            'operation' => $operation, 'submissionKey' => (string) Str::uuid(),
            'items' => $items, 'locations' => $locations, 'availability' => $availability,
        ]);
    }

    public function store(StockRequest $request, StockManager $manager, string $operation): RedirectResponse
    {
        $data = $request->validated();
        $method = $operation === 'receive' ? 'receive' : 'issue';
        $movement = $manager->$method(
            Item::withTrashed()->findOrFail($data['item_id']),
            Location::withTrashed()->findOrFail($data['location_id']),
            $data['quantity'], $request->user(), $data['reason'] ?? null, $data['reference'] ?? null,
            submissionKey: $data['submission_key'],
        );

        $unit = $movement->item->unit_of_measure;
        $quantity = QuantityFormatter::format(ltrim($movement->quantity_change, '-'));
        $balance = QuantityFormatter::format($movement->quantity_after);
        $message = $movement->wasRecentlyCreated
            ? ($operation === 'receive' ? 'Received ' : 'Issued ').$quantity.' '.$unit.' of '.$movement->item->name.' at '.$movement->location->name.'. Balance: '.$balance.' '.$unit.'.'
            : 'Already recorded. No stock changed.';

        return to_route('balances.index', ['q' => $movement->item->code, 'location' => $movement->location_id])
            ->with('status', $message);
    }
}
