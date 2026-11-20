<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · Inventory Management</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-white font-sans text-slate-800 antialiased">
    <div class="flex min-h-dvh min-w-0">
        <aside class="hidden min-w-0 flex-col justify-between bg-slate-950 p-10 text-white lg:flex lg:w-5/12 xl:p-12">
            <div class="flex items-center gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-400 text-slate-950"><x-icon name="box" class="size-6" /></span><span class="text-base font-semibold">Inventory Management</span></div>
            <div class="my-6 max-w-sm">
                <h2 class="text-3xl font-semibold leading-tight tracking-tight">Your inventory records</h2>
                <p class="mt-4 text-sm leading-relaxed text-slate-400">Find items, check current stock and review stock history.</p>
                <ul class="mt-8 space-y-4 text-sm text-slate-300">
                    <li class="flex items-center gap-3"><x-icon name="box" class="size-4 text-emerald-400" />Items and categories</li>
                    <li class="flex items-center gap-3"><x-icon name="location" class="size-4 text-emerald-400" />Storage locations</li>
                    <li class="flex items-center gap-3"><x-icon name="history" class="size-4 text-emerald-400" />Current stock and stock history</li>
                </ul>
            </div>
            <p class="text-xs text-slate-500">For authorised staff.</p>
        </aside>
        <main class="flex w-full min-w-0 flex-col items-center justify-center px-5 py-8 sm:px-10 lg:w-7/12">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex items-center gap-3 lg:hidden"><span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-950 text-emerald-400"><x-icon name="box" /></span><span class="text-sm font-semibold">Inventory Management</span></div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Sign in</h1>

                <form method="POST" action="{{ route('login.store') }}" class="mt-5 flex flex-col gap-4">
                    @csrf
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium">Email address <x-required-mark /></label>
                        <input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" maxlength="255" required autofocus class="form-control" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror placeholder="you@company.com">
                        @error('email')<p id="email-error" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium">Password <x-required-mark /></label>
                        <div class="relative">
                            <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="255" class="form-control pr-16" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <button type="button" data-password-toggle aria-controls="password" aria-pressed="false" class="absolute inset-y-0 right-0 rounded-r-lg px-3 text-xs font-semibold text-slate-500 hover:text-slate-900">Show</button>
                        </div>
                        @error('password')<p id="password-error" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="button-primary mt-1 w-full">Sign in</button>
                </form>
                <p class="mt-6 text-center text-xs leading-relaxed text-slate-500">For access, contact your administrator.</p>
            </div>
        </main>
    </div>
</body>
</html>
