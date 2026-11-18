<x-app-layout title="Categories" description="Find the categories used to group your items.">
    <x-filters :reset-url="route('categories.index')" placeholder="Category name or code"><x-status-filter /></x-filters>
    <x-table-panel :records="$categories" label="Categories" empty-title="No categories found" empty-description="Categories will appear here when they are added.">
        <table class="data-table" aria-label="Categories">
            <thead><tr><th scope="col" class="w-[44%]">Category</th><th scope="col" class="w-[24%]">Code</th><th scope="col" class="w-[16%]">Items</th><th scope="col" class="w-[16%]">Status</th></tr></thead>
            <tbody>
                @foreach($categories as $category)
                    <tr>
                        <td data-label="Category"><div class="cell-content"><p class="font-medium text-slate-800">{{ $category->name }}</p>@if($category->description)<p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $category->description }}</p>@endif</div></td>
                        <td data-label="Code"><span class="cell-content font-mono text-xs">{{ $category->code }}</span></td>
                        <td data-label="Items"><a href="{{ route('items.index', ['category' => $category->id]) }}" class="cell-content font-medium text-emerald-700 hover:underline">{{ $category->items_count }}</a></td>
                        <td data-label="Status"><div class="cell-content"><x-status :record="$category" /></div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-table-panel>
</x-app-layout>
