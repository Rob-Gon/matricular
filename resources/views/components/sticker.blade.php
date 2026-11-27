{{-- Achievement sticker (ITV-style disc). Locked stickers are drawn as an empty dashed outline. --}}
@props(['locked' => false, 'tilt' => -8])

<span
    aria-hidden="true"
    {{ $attributes->class([
        'flex size-24 shrink-0 items-center justify-center rounded-full border-[5px]',
        'border-ink bg-mustard shadow-hard-sm' => ! $locked,
        'border-dashed border-muted bg-transparent' => $locked,
    ]) }}
    style="transform: rotate({{ $tilt }}deg)"
>
    <span class="flex size-[74px] items-center justify-center rounded-full border-[3px] border-dashed {{ $locked ? 'border-muted' : 'border-ink' }}">
        @if ($locked)
            <svg viewBox="0 0 24 24" class="size-9" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
        @else
            <svg viewBox="0 0 24 24" class="size-9" fill="none" stroke="#000000" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z" /><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3" /></svg>
        @endif
    </span>
</span>
