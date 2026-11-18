<x-app-layout title="Items" description="Find items by name or code. Stock items are tracked by quantity; assets are tracked individually.">
    <x-filters :reset-url="route('items.index')" placeholder="Item name or code">
        <x-select-filter name="category" label="Category" :options="$categoryOptions" placeholder="All categories" />
        <x-select-filter name="tracking_type" label="Item type" :options="['stock' => 'Stock', 'asset' => 'Asset']" placeholder="All types" />
        <x-status-filter />
    </x-filters>
    <x-table-panel :records="$items" label="Items" empty-title="No items found" empty-description="Items will appear here when they are added.">
        <table class="data-table" aria-label="Items">
            <thead><tr><th scope="col" class="w-[34%]">Item</th><th scope="col" class="w-[24%]">Category</th><th scope="col" class="w-[14%]">Item type</th><th scope="col" class="w-[12%]">Unit</th><th scope="col" class="w-[16%]">Status</th></tr></thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td data-label="Item"><div class="cell-content"><p class="font-medium text-slate-800">{{ $item->name }}</p><p class="mt-1 font-mono text-xs text-slate-500">{{ $item->code }}</p>@if($item->description)<p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $item->description }}</p>@endif</div></td>
                        <td data-label="Category"><div class="cell-content">{{ $item->category->name }}@if($item->category->trashed())<p class="mt-1 text-xs text-amber-700">Archived category</p>@endif</div></td>
                        <td data-label="Item type"><div class="cell-content"><x-badge :tone="$item->tracking_type === 'stock' ? 'blue' : 'slate'">{{ ucfirst($item->tracking_type) }}</x-badge></div></td>
                        <td data-label="Unit"><span class="cell-content text-xs">{{ $item->unit_of_measure }}</span></td>
                        <td data-label="Status"><div class="cell-content"><x-status :record="$item" /></div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-table-panel>
</x-app-layout>
