@props(['name', 'label', 'value' => '', 'required' => false, 'hint' => '', 'placeholder' => ''])
<div class="min-w-0">
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}
        @if($required)
            <x-required-mark />
        @else
            <span class="font-normal text-slate-500">(optional)</span>
        @endif
    </label>
    <input id="{{ $name }}" name="{{ $name }}" value="{{ old($name, $value) }}" maxlength="255" placeholder="{{ $placeholder }}"
        @required($required)
        @if($hint || $errors->has($name)) aria-describedby="{{ $name }}-feedback" @endif
        @if($errors->has($name)) aria-invalid="true" @endif
        {{ $attributes->class(['form-control mt-1']) }}>
    @if($hint || $errors->has($name))
        <div id="{{ $name }}-feedback" class="mt-1 text-xs leading-relaxed">
            @if($hint)
                <p class="text-slate-500">{{ $hint }}</p>
            @endif
            @error($name)
                <p class="mt-1 text-red-700">{{ $message }}</p>
            @enderror
        </div>
    @endif
</div>
