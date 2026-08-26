@props(['name', 'value' => null, 'label', 'rows' => 3, 'required' => false, 'help' => null])

<x-form.field :name="$name" :label="$label" :required="$required" :help="$help" :class="$attributes->get('class')">
    <textarea id="{{ $name }}"
              name="{{ $name }}"
              rows="{{ $rows }}"
              @if ($required) required @endif
              {{ $attributes->except('class')->merge(['class' => 'form-input-base'.($errors->has($name) ? ' border-rose-400' : '')]) }}>{{ old($name, $value) }}</textarea>
</x-form.field>
