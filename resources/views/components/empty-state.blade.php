@props(['title' => 'No results found', 'description' => 'Try another search or clear the filters.', 'icon' => 'box'])
<div class="flex min-w-0 flex-col items-center px-4 py-6 text-center">
    <x-icon :name="$icon" class="mb-2 size-6 text-slate-300" />
    <h2 class="text-sm font-semibold text-slate-800">{{ $title }}</h2>
    @if($description)
        <p class="mt-1 max-w-sm text-sm leading-relaxed text-slate-500">{{ $description }}</p>
    @endif
</div>
