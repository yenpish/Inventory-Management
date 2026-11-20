@props(['mobile' => false])
@php
    $navigation = [
        ['Dashboard', 'dashboard', 'dashboard'],
        ['Items', 'items.index', 'box'],
        ['Categories', 'categories.index', 'categories'],
        ['Locations', 'locations.index', 'location'],
        ['Current Stock', 'balances.index', 'balances'],
        ['Stock History', 'movements.index', 'history'],
    ];
@endphp
<a href="{{ route('dashboard') }}" aria-label="Inventory Management home" class="sidebar-brand flex items-center gap-3 px-4 py-5 text-white">
    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-400 text-slate-950"><x-icon name="box" /></span>
    <span class="sidebar-label text-sm font-semibold">Inventory Management</span>
</a>
<nav aria-label="Main navigation" class="flex flex-col gap-1 px-3 py-2">
    @foreach ($navigation as [$label, $route, $icon])
        @php
            $isCurrent = request()->routeIs($route)
                || ($route === 'balances.index' && request()->routeIs('stock.*'))
                || (request()->routeIs('catalog.*') && $route === request()->route('kind').'.index');
        @endphp
        <a href="{{ route($route) }}" aria-label="{{ $label }}" @unless($mobile) aria-describedby="nav-tip-{{ $loop->index }}" @endunless
            @if($isCurrent) aria-current="page" @endif
            @class(['sidebar-link relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300',
                'bg-emerald-400/10 text-emerald-300 ring-1 ring-inset ring-emerald-400/15' => $isCurrent,
                'text-slate-400 hover:bg-white/5 hover:text-white' => ! $isCurrent])>
            <x-icon :name="$icon" /><span class="sidebar-label">{{ $label }}</span>
            @unless($mobile)<span id="nav-tip-{{ $loop->index }}" role="tooltip" class="sidebar-tooltip">{{ $label }}</span>@endunless
        </a>
    @endforeach
</nav>
@unless($mobile)
    <div class="mt-auto border-t border-white/10 p-3">
        <button type="button" data-sidebar-toggle aria-controls="desktop-navigation" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar" class="sidebar-toggle flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-400 hover:bg-white/5 hover:text-white focus-visible:outline-2 focus-visible:outline-emerald-300">
            <svg class="sidebar-toggle-icon size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M14 7l-5 5 5 5M3 4h18v16H3z" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <span class="sidebar-label" data-sidebar-toggle-label>Collapse sidebar</span>
        </button>
    </div>
@endunless
