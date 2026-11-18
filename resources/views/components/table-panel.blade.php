@props(['records', 'label', 'emptyTitle' => 'No results found', 'emptyDescription' => 'Try another search or clear the filters.'])
@php
    $hasFilters = count(array_filter(request()->only(['q', 'category', 'tracking_type', 'location', 'item', 'balance', 'type', 'from', 'to']), fn ($value) => filled($value))) > 0
        || in_array(request('status'), ['active', 'inactive', 'archived', 'all'], true);
@endphp
<section class="min-w-0 rounded-xl border border-slate-200 bg-white" aria-label="{{ $label }}">
    @if($records->isEmpty())
        <x-empty-state :title="$hasFilters ? 'No results found' : $emptyTitle" :description="$hasFilters ? 'Try another search or clear the filters.' : $emptyDescription" />
    @else
        <div class="table-viewport">{{ $slot }}</div>
        <div class="min-w-0 border-t border-slate-100 px-4 py-3">
            @if($records->hasPages()){{ $records->onEachSide(1)->links() }}@else<p class="text-xs text-slate-500">Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ number_format($records->total()) }}</p>@endif
        </div>
    @endif
</section>
