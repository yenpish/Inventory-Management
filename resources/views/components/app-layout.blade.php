@props(['title', 'description' => ''])
<!DOCTYPE html>
<html lang="en" data-sidebar="expanded">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Inventory Management</title>
    <script>
        try {
            document.documentElement.dataset.sidebar = localStorage.getItem('inventory.sidebar') === 'compact' ? 'compact' : 'expanded';
        } catch { document.documentElement.dataset.sidebar = 'expanded'; }
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3 focus:ring-2 focus:ring-emerald-600">Skip to content</a>
    <aside id="desktop-navigation" class="desktop-sidebar fixed inset-y-0 left-0 z-30 hidden flex-col bg-slate-950 lg:flex"><x-navigation /></aside>
    <dialog id="mobile-navigation" aria-label="Navigation" class="m-0 h-dvh max-h-none w-72 max-w-[85vw] border-0 bg-slate-950 p-0 text-white backdrop:bg-slate-950/60 lg:hidden">
        <div class="flex min-h-dvh flex-col">
            <form method="dialog" class="absolute top-2 right-2"><button aria-label="Close navigation" class="rounded-lg p-2 text-slate-500 hover:text-white"><x-icon name="close" /></button></form>
            <x-navigation :mobile="true" />
        </div>
    </dialog>
    <div class="application-shell flex min-h-dvh min-w-0 flex-col">
        <header class="sticky top-0 z-20 flex min-h-16 min-w-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-3 py-2 sm:px-6">
            <div class="flex min-w-0 items-center gap-2">
                <button type="button" data-open-navigation aria-controls="mobile-navigation" aria-expanded="false" aria-label="Open navigation" class="shrink-0 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"><x-icon name="menu" /></button>
                <h1 class="min-w-0 text-lg font-semibold tracking-tight text-slate-950">{{ $title }}</h1>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <span class="hidden text-xs text-slate-500 lg:block">View only</span>
                <details class="relative">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg p-1 focus-visible:outline-2 focus-visible:outline-emerald-600">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-800">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="hidden max-w-32 truncate text-sm font-medium sm:block">{{ auth()->user()->name }}</span>
                        <x-icon name="chevron" class="size-4 text-slate-500" /><span class="sr-only">Account menu</span>
                    </summary>
                    <div class="absolute right-0 mt-2 w-64 max-w-[calc(100vw-1.5rem)] rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                        <div class="border-b border-slate-100 px-3 py-2"><p class="break-words text-sm font-medium">{{ auth()->user()->name }}</p><p class="mt-1 break-all text-xs text-slate-500">{{ auth()->user()->email }}</p></div>
                        <form action="{{ route('logout') }}" method="POST" class="pt-1">
                            @csrf
                            <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-50"><x-icon name="logout" class="size-4" />Sign out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>
        <main id="main-content" tabindex="-1" class="mx-auto w-full min-w-0 max-w-[1600px] flex-1 px-3 py-5 sm:px-6 sm:py-6">
            @if($description)<p class="mb-5 max-w-3xl text-sm leading-relaxed text-slate-500">{{ $description }}</p>@endif
            {{ $slot }}
        </main>
    </div>
</body>
</html>
