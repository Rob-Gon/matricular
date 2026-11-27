{{--
    A timestamp shown in the viewer's own time zone. The server stores and renders UTC; the browser
    reformats it with Intl, so a player in Canarias and one in Madrid each see their local time.
--}}
@props(['value', 'time' => true])

@php
    $utc = \Illuminate\Support\Carbon::parse($value)->utc();
    $options = $time
        ? "{ day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }"
        : "{ day: '2-digit', month: '2-digit', year: 'numeric' }";
@endphp

<time
    {{ $attributes }}
    datetime="{{ $utc->toIso8601ZuluString() }}"
    x-data
    x-text="new Intl.DateTimeFormat('es-ES', {{ $options }}).format(new Date($el.dateTime))"
>{{ $utc->format($time ? 'd/m/Y, H:i' : 'd/m/Y') }}{{ $time ? ' UTC' : '' }}</time>
