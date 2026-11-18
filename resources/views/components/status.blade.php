@props(['record'])
@if ($record->trashed())
    <x-badge>Archived</x-badge>
@elseif ($record->is_active)
    <x-badge tone="emerald"><span class="size-1.5 rounded-full bg-emerald-500"></span>Active</x-badge>
@else
    <x-badge tone="amber">Inactive</x-badge>
@endif
