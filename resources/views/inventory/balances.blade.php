<x-app-layout title="Current Stock">
    @can('manage-inventory')<div class="mb-4 flex flex-wrap gap-3"><a class="button-primary" href="{{ route('stock.create', ['operation' => 'receive']) }}">Receive Stock</a><a class="button-secondary" href="{{ route('stock.create', ['operation' => 'issue']) }}">Issue Stock</a></div>@endcan
    <x-filters :reset-url="route('balances.index')" placeholder="Item or location">
        <x-select-filter name="location" label="Location" :options="$locationOptions" placeholder="All locations" />
        <x-select-filter name="balance" label="Stock level" :options="['positive' => 'In stock', 'zero' => 'Out of stock']" placeholder="All stock levels" />
    </x-filters>
    <x-table-panel :records="$balances" label="Current Stock" empty-title="No stock found" empty-description="Balances appear after the first receipt.">
        <table class="data-table" aria-label="Current Stock">
            <thead><tr><th scope="col" class="w-[36%]">Item</th><th scope="col" class="w-[32%]">Location</th><th scope="col" class="quantity-cell w-[32%]">Quantity</th></tr></thead>
            <tbody>
                @foreach($balances as $balance)
                    <tr>
                        <td data-label="Item"><div class="cell-content"><p class="font-medium text-slate-800">{{ $balance->item->name }}</p><p class="mt-1 font-mono text-xs text-slate-500">{{ $balance->item->code }}</p>@if($balance->item->trashed())<p class="mt-1 text-xs text-amber-700">Archived item</p>@elseif(!$balance->item->is_active)<p class="mt-1 text-xs text-amber-700">Inactive item</p>@endif</div></td>
                        <td data-label="Location"><div class="cell-content"><p>{{ $balance->location->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $balance->location->code }}</p>@if($balance->location->trashed())<p class="mt-1 text-xs text-amber-700">Archived location</p>@elseif(!$balance->location->is_active)<p class="mt-1 text-xs text-amber-700">Inactive location</p>@endif</div></td>
                        <td data-label="Quantity" class="quantity-cell"><div class="cell-content"><p class="quantity-number font-mono text-sm font-semibold text-slate-900">{{ \App\QuantityFormatter::format($balance->quantity) }}</p><p class="mt-1 text-xs text-slate-500">{{ $balance->item->unit_of_measure }}</p>@if($balance->quantity === '0.0000')<span class="mt-1 block text-xs text-slate-500">Out of stock</span>@endif</div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-table-panel>
</x-app-layout>
