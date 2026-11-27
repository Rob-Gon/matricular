@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'bag' => 'default'])

@php
    $error = $errors->getBag($bag)->first($name);
    $describedBy = trim(($hint ? $name.'-hint ' : '').($error ? $name.'-error' : ''));
@endphp

<div class="field">
    <label class="field-label" for="{{ $name }}">{{ $label }}</label>
    <input
        {{ $attributes->class('field-input') }}
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
    >
    @if ($hint)
        <p id="{{ $name }}-hint" class="text-lg text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $name }}-error" class="field-error">{{ $error }}</p>
    @endif
</div>
