<x-app-layout title="Locations">
    @can('manage-inventory')<div class="mb-4"><a class="button-primary" href="{{ route('catalog.create', ['kind' => 'locations']) }}">Add location</a></div>@endcan
    <x-filters :reset-url="route('locations.index')" placeholder="Location name or code"><x-status-filter /></x-filters>
    <x-table-panel :records="$locations" label="Locations" empty-title="No locations found" empty-description="">
        <table class="data-table" aria-label="Locations">
            <thead><tr><th scope="col" class="w-[32%]">Location</th><th scope="col" class="w-[16%]">Code</th><th scope="col" class="w-[24%]">Part of</th><th scope="col" class="w-[12%]">Sub-locations</th><th scope="col" class="w-[16%]">Status</th></tr></thead>
            <tbody>
                @foreach($locations as $location)
                    <tr>
                        <td data-label="Location">
                            <div class="cell-content">
                                <p class="font-medium text-slate-800">{{ $location->name }}</p>
                                @can('manage-inventory')
                                    @unless($location->trashed())
                                        <a class="mt-1 inline-block text-sm font-medium text-emerald-700 underline" href="{{ route('catalog.edit', ['kind' => 'locations', 'record' => $location->id]) }}">
                                            Edit<span class="sr-only"> {{ $location->name }}</span>
                                        </a>
                                    @endunless
                                @endcan
                                @if($location->description)
                                    <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $location->description }}</p>
                                @endif
                            </div>
                        </td>
                        <td data-label="Code"><span class="cell-content font-mono text-xs">{{ $location->code }}</span></td>
                        <td data-label="Part of"><div class="cell-content">{{ $location->parent?->name ?? 'Top-level location' }}@if($location->parent?->trashed())<p class="mt-1 text-xs text-amber-700">Parent location archived</p>@endif</div></td>
                        <td data-label="Sub-locations"><span class="cell-content tabular-nums">{{ $location->children_count }}</span></td>
                        <td data-label="Status"><div class="cell-content"><x-status :record="$location" /></div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-table-panel>
</x-app-layout>
