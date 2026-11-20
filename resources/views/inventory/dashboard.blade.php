<x-app-layout title="Dashboard">
    <div class="mb-4 grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Items', $metrics['items'], 'box', 'Stock: '.$stockItems.' · Asset: '.$assetItems],
            ['Categories', $metrics['categories'], 'categories', 'Not archived'],
            ['Locations', $metrics['locations'], 'location', 'Not archived'],
            ['Stock transactions', $metrics['movements'], 'history', 'Recorded movements'],
        ] as [$label, $value, $icon, $hint])
            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-2"><h2 class="text-xs font-medium text-slate-500">{{ $label }}</h2><x-icon :name="$icon" class="size-4 text-slate-500" /></div>
                <p class="mt-2 text-2xl font-semibold text-slate-950 tabular-nums">{{ number_format($value) }}</p><p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
            </section>
        @endforeach
    </div>
    <div class="grid min-w-0 gap-4 xl:grid-cols-3">
        <section class="min-w-0 rounded-xl border border-slate-200 bg-white xl:col-span-2">
            <div class="flex min-w-0 flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold">Recent transactions</h2><a href="{{ route('movements.index') }}" class="text-xs font-medium text-emerald-700 hover:underline">View stock history</a></div>
            @if($recentMovements->isEmpty())
                <x-empty-state title="No stock transactions yet" description="" icon="history" />
            @else
                <div class="table-viewport"><x-movement-table :movements="$recentMovements" :compact="true" /></div>
            @endif
        </section>
        <div class="flex min-w-0 flex-col gap-4">
            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-sm font-semibold">Current Stock</h2>
                <p class="mt-1 text-xs text-slate-500">By item and location.</p>
                <dl class="my-4 flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-2"><dt class="text-sm text-slate-500">With stock</dt><dd class="font-semibold tabular-nums">{{ number_format($positiveBalances) }}</dd></div>
                    <div class="flex items-center justify-between gap-2"><dt class="text-sm text-slate-500">Out of stock</dt><dd class="font-semibold tabular-nums">{{ number_format($zeroBalances) }}</dd></div>
                </dl>
                <a href="{{ route('balances.index') }}" class="text-xs font-medium text-emerald-700 hover:underline">View current stock →</a>
            </section>
            <section class="min-w-0 rounded-xl border border-slate-200 bg-white">
                <h2 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Items by category</h2>
                @if($categoryOverview->isEmpty())
                    <x-empty-state title="No categories yet" description="" icon="categories" />
                @else
                    <ul class="divide-y divide-slate-100 px-4">
                        @foreach($categoryOverview as $category)
                            <li class="flex min-w-0 items-center justify-between gap-3 py-3 text-sm"><a href="{{ route('items.index', ['category' => $category->id]) }}" class="min-w-0 break-words text-slate-600 hover:text-emerald-700">{{ $category->name }}</a><span class="shrink-0 text-xs tabular-nums text-slate-500">{{ $category->items_count }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
