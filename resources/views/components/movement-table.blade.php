@props(['movements', 'compact' => false])
<table @class(['data-table', 'wide-data-table' => ! $compact]) aria-label="Stock History">
    <thead><tr><th scope="col" @unless($compact) class="w-[17%]" @endunless>Date and time</th><th scope="col" @unless($compact) class="w-[25%]" @endunless>Item and location</th>@unless($compact)<th scope="col" class="w-[13%]">Transaction</th>@endunless<th scope="col" class="quantity-cell" @unless($compact) style="width: 20%" @endunless>Change</th>@unless($compact)<th scope="col" class="w-[25%]">Details</th>@endunless</tr></thead>
    <tbody>
        @foreach($movements as $movement)
            @php($transactionLabel = match($movement->type) { 'receipt' => 'Received', 'issue' => 'Issued', default => 'Adjusted' })
            <tr>
                <td data-label="Date and time"><div class="cell-content"><p>{{ $movement->occurred_at->timezone('Asia/Kuala_Lumpur')->format('d M Y') }}</p><p class="mt-1 text-xs text-slate-500">{{ $movement->occurred_at->timezone('Asia/Kuala_Lumpur')->format('H:i:s') }}</p>@unless($compact)<p class="mt-1 text-xs text-slate-500">#{{ str_pad((string)$movement->id, 6, '0', STR_PAD_LEFT) }}</p>@endunless</div></td>
                <td data-label="Item and location"><div class="cell-content"><p class="font-medium text-slate-800">{{ $movement->item->name }}</p><p class="mt-1 font-mono text-xs text-slate-500">{{ $movement->item->code }}</p><p class="mt-1 text-xs text-slate-500">{{ $movement->location->name }} · {{ $movement->location->code }}</p>@if($movement->item->trashed())<span class="mt-1 block text-xs text-amber-700">Archived item</span>@endif @if($movement->location->trashed())<span class="mt-1 block text-xs text-amber-700">Archived location</span>@endif @if($compact)<p class="mt-1 text-xs text-slate-500">{{ $transactionLabel }}</p>@endif</div></td>
                @unless($compact)<td data-label="Transaction"><div class="cell-content"><x-badge :tone="match($movement->type) { 'receipt' => 'emerald', 'issue' => 'amber', default => 'blue' }">{{ $transactionLabel }}</x-badge></div></td>@endunless
                <td data-label="Change" class="quantity-cell"><div class="cell-content"><p @class(['quantity-number font-mono text-sm font-medium', 'text-amber-700' => str_starts_with($movement->quantity_change, '-'), 'text-emerald-700' => ! str_starts_with($movement->quantity_change, '-')])>{{ str_starts_with($movement->quantity_change, '-') ? '' : '+' }}{{ \App\QuantityFormatter::format($movement->quantity_change) }}</p><p class="mt-1 text-xs text-slate-500">{{ $movement->item->unit_of_measure }}</p></div></td>
                @unless($compact)
                    <td data-label="Details"><div class="cell-content">
                        @if($movement->reference)<p class="text-xs font-medium">{{ $movement->reference }}</p>@endif
                        <details class="mt-1">
                            <summary class="cursor-pointer text-xs font-medium text-emerald-700 hover:text-emerald-900">Details<span class="sr-only"> for stock record {{ $movement->id }}</span></summary>
                            <dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                <div><dt class="text-slate-500">Before</dt><dd class="mt-0.5 font-mono">{{ \App\QuantityFormatter::format($movement->quantity_before) }}</dd></div>
                                <div><dt class="text-slate-500">After</dt><dd class="mt-0.5 font-mono">{{ \App\QuantityFormatter::format($movement->quantity_after) }}</dd></div>
                                <div class="col-span-2"><dt class="text-slate-500">Handled by</dt><dd class="mt-0.5">{{ $movement->user?->name ?? '—' }}</dd></div>
                                <div class="col-span-2"><dt class="text-slate-500">Reason</dt><dd class="mt-0.5 leading-relaxed">{{ $movement->reason ?? '—' }}</dd></div>
                            </dl>
                        </details>
                    </div></td>
                @endunless
            </tr>
        @endforeach
    </tbody>
</table>
