@php
    $label = ['categories' => 'category', 'locations' => 'location', 'items' => 'item'][$kind];
    $codeExample = ['categories' => 'STATIONERY', 'locations' => 'STORE-01', 'items' => 'PAPER-A4'][$kind];
@endphp
<x-app-layout :title="($record->exists ? 'Edit ' : 'Add ').$label">
    <div class="mx-auto w-full max-w-3xl">
        <form method="POST" action="{{ $record->exists ? route('catalog.update', ['kind' => $kind, 'record' => $record->id]) : route('catalog.store', ['kind' => $kind]) }}"
            class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            @csrf
            @if($record->exists)
                @method('PUT')
            @endif
            <x-form-errors />
            <div class="grid items-start gap-x-5 gap-y-4 sm:grid-cols-2">
                <x-record-input name="code" label="Code" :value="$record->code" :placeholder="'e.g. '.$codeExample" hint="Unique code, saved in capitals." required />
                <x-record-input name="name" label="Name" :value="$record->name" required />

                @if($kind === 'items')
                    <div class="min-w-0">
                        <label for="item_category_id" class="block text-sm font-medium">Category <x-required-mark /></label>
                        <select id="item_category_id" name="item_category_id" required class="form-control mt-1" aria-describedby="category-help" @if($errors->has('item_category_id')) aria-invalid="true" @endif>
                            <option value="">Choose a category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('item_category_id', $record->item_category_id) === (string) $category->id)>{{ $category->name }}{{ $category->trashed() ? ' (archived)' : '' }}</option>
                            @endforeach
                        </select>
                        <p id="category-help" class="mt-1 text-xs text-slate-500">
                            @if($categories->isEmpty())
                                Add a category first.
                            @endif
                            <a href="{{ route('catalog.create', ['kind' => 'categories']) }}" class="font-medium text-emerald-700 underline">Add category</a>
                        </p>
                    </div>
                    <div class="min-w-0">
                        <label for="tracking_type" class="block text-sm font-medium">Item type <x-required-mark /></label>
                        <select id="tracking_type" name="tracking_type" required class="form-control mt-1" aria-describedby="tracking-help">
                            <option value="stock" @selected(old('tracking_type', $record->tracking_type ?? 'stock') === 'stock')>Stock</option>
                            <option value="asset" @selected(old('tracking_type', $record->tracking_type ?? 'stock') === 'asset')>Asset</option>
                        </select>
                        <p id="tracking-help" class="mt-1 text-xs leading-relaxed text-slate-500">Stock: quantities (paper). Asset: individual equipment (laptop).</p>
                    </div>
                    <x-record-input name="unit_of_measure" label="Unit of measure" :value="$record->unit_of_measure" placeholder="e.g. box, metre, piece" hint="Unit used for quantities." required />
                    @if($record->exists && $hasStockHistory)
                        <p class="self-center text-xs leading-relaxed text-slate-500">Type and unit are fixed once stock is recorded.</p>
                    @endif
                @endif

                @if($kind === 'locations')
                    <div class="min-w-0 sm:col-span-2">
                        <label for="parent_id" class="block text-sm font-medium">Parent location <span class="font-normal text-slate-500">(optional)</span></label>
                        <select id="parent_id" name="parent_id" class="form-control mt-1" aria-describedby="parent-help">
                            <option value="">No parent location</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('parent_id', $record->parent_id) === (string) $location->id)>{{ $location->name }}{{ $location->trashed() ? ' (archived)' : '' }}</option>
                            @endforeach
                        </select>
                        <p id="parent-help" class="mt-1 text-xs leading-relaxed text-slate-500">Choose a containing location, or leave blank for top-level.</p>
                    </div>
                @endif
            </div>
            <div>
                <label for="description" class="block text-sm font-medium">Description <span class="font-normal text-slate-500">(optional)</span></label>
                <textarea id="description" name="description" maxlength="5000" rows="2" class="form-control mt-1">{{ old('description', $record->description) }}</textarea>
            </div>
            <div class="pt-1">
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $record->exists ? $record->is_active : true)) class="size-4 accent-emerald-700"> Active</label>
            </div>
            <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                <button class="button-primary" @disabled($kind === 'items' && $categories->isEmpty())>Save {{ $label }}</button>
                <a class="button-secondary" href="{{ route($kind.'.index') }}">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
