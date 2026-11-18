@props(['tone' => 'slate'])
@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200/70',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200/70',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200/70',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset', $tones[$tone] ?? $tones['slate']]) }}>{{ $slot }}</span>
