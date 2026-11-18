@props(['resetUrl', 'placeholder' => 'Name or code'])
<form action="{{ $resetUrl }}" method="GET" role="search" class="mb-4 flex min-w-0 flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-3 sm:p-4">
    <label class="search-field block text-xs font-medium text-slate-600">
        <span class="mb-1.5 block">Search</span>
        <span class="relative block"><x-icon name="search" class="pointer-events-none absolute top-3 left-3 size-4 text-slate-400" /><input type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="{{ $placeholder }}" class="form-control pl-9" /></span>
    </label>
    {{ $slot }}
    <div class="flex min-w-0 flex-wrap gap-2">
        <button type="submit" class="button-primary">Apply</button>
        <a href="{{ $resetUrl }}" class="button-secondary">Clear filters</a>
    </div>
    @if($errors->any())
        <div role="alert" class="w-full rounded-lg bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
</form>
