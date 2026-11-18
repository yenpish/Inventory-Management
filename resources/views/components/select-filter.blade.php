@props(['name', 'label', 'options', 'placeholder' => 'All', 'value' => null])
<label class="filter-field block text-xs font-medium text-slate-600">
    <span class="mb-1.5 block">{{ $label }}</span>
    <select name="{{ $name }}" class="form-control">
        @if($placeholder !== false)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $key => $text)
            <option value="{{ $key }}" @selected((string)($value ?? request($name, '')) === (string)$key)>{{ $text }}</option>
        @endforeach
    </select>
</label>
