<x-app-layout title="Stock History">
    <p class="mb-3 text-xs text-slate-500">Latest recorded first · Malaysia time</p>
    <x-filters :reset-url="route('movements.index')" placeholder="Item, reference or reason">
        <x-select-filter name="type" label="Transaction" :options="['receipt' => 'Received', 'issue' => 'Issued', 'adjustment' => 'Adjusted']" placeholder="All transactions" />
        <x-select-filter name="location" label="Location" :options="$locationOptions" placeholder="All locations" />
        <label class="filter-field block text-xs font-medium text-slate-600"><span class="mb-1.5 block">From date</span><input type="date" name="from" value="{{ request('from') }}" class="form-control"></label>
        <label class="filter-field block text-xs font-medium text-slate-600"><span class="mb-1.5 block">To date</span><input type="date" name="to" value="{{ request('to') }}" class="form-control"></label>
    </x-filters>
    <x-table-panel :records="$movements" label="Stock History" empty-title="No stock transactions found" empty-description="">
        <x-movement-table :movements="$movements" />
    </x-table-panel>
</x-app-layout>
