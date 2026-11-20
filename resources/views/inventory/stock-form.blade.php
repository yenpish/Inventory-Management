<x-app-layout :title="$operation === 'receive' ? 'Receive Stock' : 'Issue Stock'">
    <div class="mx-auto w-full max-w-3xl">
        <form data-submit-once data-stock-form data-availability="{{ json_encode($availability) }}" method="POST" action="{{ route('stock.store', ['operation' => $operation]) }}"
            class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            @csrf
            <input type="hidden" name="submission_key" value="{{ old('submission_key', $submissionKey) }}">
            <x-form-errors />
            @if($items->isEmpty() || $locations->isEmpty())
                <div role="status" class="space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p>An active stock item and location are needed.</p>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @if($items->isEmpty())
                            <a class="font-medium underline" href="{{ route('catalog.create', ['kind' => 'items']) }}">Add stock item</a>
                        @endif
                        @if($locations->isEmpty())
                            <a class="font-medium underline" href="{{ route('catalog.create', ['kind' => 'locations']) }}">Add location</a>
                        @endif
                    </div>
                </div>
            @endif
            <div class="grid items-start gap-x-5 gap-y-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="item_id" class="block text-sm font-medium">Stock item <x-required-mark /></label>
                    <select id="item_id" name="item_id" required class="form-control mt-1" @if($errors->has('item_id')) aria-invalid="true" @endif>
                        <option value="">Choose an item</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" data-unit="{{ $item->unit_of_measure }}" @selected((string) old('item_id') === (string) $item->id)>{{ $item->code }} — {{ $item->name }} ({{ $item->unit_of_measure }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label for="location_id" class="block text-sm font-medium">Location <x-required-mark /></label>
                    <select id="location_id" name="location_id" required class="form-control mt-1" @if($errors->has('location_id')) aria-invalid="true" @endif>
                        <option value="">Choose a location</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->code }} — {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex min-w-0 flex-wrap items-center justify-between gap-2 border-y border-slate-100 py-2 text-xs sm:col-span-2">
                    <output data-stock-available aria-live="polite" title="At page load; checked again on save." class="font-medium break-words">Available: —</output>
                    <a href="{{ route('balances.index') }}" class="font-medium text-emerald-700 underline">Current Stock</a>
                    <noscript><span>Check Current Stock before issuing.</span></noscript>
                </div>
                <div class="min-w-0">
                    <label for="quantity" class="block text-sm font-medium">Quantity <x-required-mark /></label>
                    <input id="quantity" name="quantity" type="text" inputmode="decimal" required maxlength="19" value="{{ old('quantity') }}" placeholder="e.g. 2.25" aria-describedby="quantity-help" @if($errors->has('quantity')) aria-invalid="true" @endif class="form-control mt-1">
                    <p id="quantity-help" class="mt-1 text-xs leading-relaxed text-slate-500">Up to 4 decimal places.</p>
                </div>
                <x-record-input name="reference" label="Reference" placeholder="e.g. PO-2026-014" hint="Delivery or request number." />
            </div>
            <div>
                <label for="reason" class="block text-sm font-medium">Reason or notes <span class="font-normal text-slate-500">(optional)</span></label>
                <textarea id="reason" name="reason" rows="2" maxlength="5000" class="form-control mt-1">{{ old('reason') }}</textarea>
            </div>
            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">

                <div class="flex flex-wrap gap-3">
                    <button class="button-primary disabled:opacity-50" @disabled($items->isEmpty() || $locations->isEmpty())>{{ $operation === 'receive' ? 'Receive stock' : 'Issue stock' }}</button>
                    <a href="{{ route('balances.index') }}" class="button-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
