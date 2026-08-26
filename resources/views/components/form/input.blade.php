@props(['name', 'value' => null, 'type' => 'text', 'label', 'required' => false, 'help' => null])

<x-form.field :name="$name" :label="$label" :required="$required" :help="$help" :class="$attributes->get('class')">
    <input id="{{ $name }}"
           name="{{ $name }}"
           type="{{ $type }}"
           value="{{ old($name, $value) }}"
           @if ($required) required @endif
           {{ $attributes->except('class')->merge(['class' => 'form-input-base'.($errors->has($name) ? ' border-rose-400' : '')]) }}>
</x-form.field>
