@props(['name', 'options' => [], 'selected' => null, 'label', 'placeholder' => null, 'required' => false, 'help' => null])

<x-form.field :name="$name" :label="$label" :required="$required" :help="$help" :class="$attributes->get('class')">
    <select id="{{ $name }}"
            name="{{ $name }}"
            @if ($required) required @endif
            {{ $attributes->except('class')->merge(['class' => 'form-input-base'.($errors->has($name) ? ' border-rose-400' : '')]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $selected) === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
</x-form.field>
