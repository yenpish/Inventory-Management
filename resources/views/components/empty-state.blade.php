@props(['title' => 'No results found', 'description' => 'Try another search or clear the filters.', 'icon' => 'box'])
<div class="flex min-w-0 flex-col items-center px-4 py-10 text-center">
    <x-icon :name="$icon" class="mb-3 size-7 text-slate-300" />
    <h2 class="text-sm font-semibold text-slate-800">{{ $title }}</h2>
    <p class="mt-1.5 max-w-sm text-sm leading-relaxed text-slate-500">{{ $description }}</p>
</div>
