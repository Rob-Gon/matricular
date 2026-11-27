{{--
    Achievement sticker, drawn like an ITV windscreen sticker: a coloured disc with a dashed inner ring
    and the achievement's own artwork (`icon`, from config/achievements.php). Locked stickers are an empty
    dashed outline with a padlock. Platinum gets its own metal, a light halo and slowly turning rays.
--}}
@props(['icon' => 'trophy', 'locked' => false, 'tilt' => -8])

@php
    // Families share a colour: general (mustard), vowels (plate blue), parts of speech (glass), hidden (signal red).
    $tone = match ($icon) {
        'bat', 'wand' => 'plate',
        'noun', 'adjective', 'verb' => 'glass',
        'mirror', 'thread', 'pothole', 'dog', 'mouse' => 'signal',
        'trophy' => 'platinum',
        default => 'mustard',
    };
    $platinum = $tone === 'platinum' && ! $locked;
    $disc = match (true) {
        $locked => 'border-dashed border-muted bg-transparent',
        $tone === 'plate' => 'border-ink bg-plate text-enamel shadow-hard-sm',
        $tone === 'glass' => 'border-ink bg-glass text-ink shadow-hard-sm',
        $tone === 'signal' => 'border-ink bg-signal text-enamel shadow-hard-sm',
        $platinum => 'sticker-platinum border-ink text-ink shadow-hard-sm',
        default => 'border-ink bg-mustard text-ink shadow-hard-sm',
    };
@endphp

<span aria-hidden="true" {{ $attributes->class(['relative isolate inline-flex size-24 shrink-0']) }}>
    @if ($platinum)
        <span class="sticker-halo"></span>
    @endif
    <span class="flex size-full items-center justify-center rounded-full border-[5px] {{ $disc }}" style="transform: rotate({{ $tilt }}deg)">
        <span class="flex size-[74px] items-center justify-center rounded-full border-[3px] border-dashed {{ $locked ? 'border-muted' : 'border-current' }}">
            <svg viewBox="0 0 24 24" class="size-11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                @if ($locked)
                    <rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" />
                @else
                    @switch($icon)
                        @case('flag')
                            <path d="M5 21V3" /><path d="M5 4h13l-2.5 4.5L18 13H5" /><path d="M9 4v9M13 4v9M5 8.5h11" stroke-width="1.4" />
                            @break
                        @case('road')
                            <path d="M8 21L10.5 3M16 21L13.5 3" /><path d="M12 19v-2.5M12 13v-2M12 8V6.5" />
                            @break
                        @case('speedometer')
                            <path d="M3.5 17a8.5 8.5 0 1 1 17 0" /><path d="M12 17l4.5-6" /><circle cx="12" cy="17" r="1.6" fill="currentColor" /><path d="M6 13l1 .6M12 8.5v1.2M18 13l-1 .6" />
                            @break
                        @case('plate')
                            <rect x="2.5" y="7.5" width="19" height="9" rx="1.8" /><path d="M6.5 7.5v9" /><path d="M10 14.5l1.2-5 1.2 5M14.5 9.5h3l-3 5h3" stroke-width="1.6" />
                            @break
                        @case('noun')
                            <path d="M12 2.8l8 4.4v9.6L12 21.2l-8-4.4V7.2z" /><path d="M12 12l8-4.6M12 12v9.2M12 12L4 7.4" />
                            @break
                        @case('adjective')
                            <path d="M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.4z" /><path d="M18.5 15.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" stroke-width="1.4" />
                            @break
                        @case('verb')
                            <path d="M13 2L4.5 13.5h6.5L10 22l9-12h-6.5z" />
                            @break
                        @case('bat')
                            <path d="M12 9.5c-.8-1.6-1.6-2.3-2.6-2.3C6.6 7.2 6 10 6 10s-1.2-1.8-3.5-1.5c1.2 3.2 3.4 6.2 6.3 6.2 1.4 0 2.4.6 3.2 2 .8-1.4 1.8-2 3.2-2 2.9 0 5.1-3 6.3-6.2C19.2 8.2 18 10 18 10s-.6-2.8-3.4-2.8c-1 0-1.8.7-2.6 2.3z" /><path d="M10.8 8.4l-.4-1.6M13.2 8.4l.4-1.6" />
                            @break
                        @case('wand')
                            <path d="M4 20L15 9" stroke-width="2.6" /><path d="M13.5 10.5l1.5 1.5" /><path d="M17 2.5v3M15.5 4h3M20.5 7.5v2M19.5 8.5h2M19 13.5v1.6M18.2 14.3h1.6" />
                            @break
                        @case('clock')
                            <circle cx="12" cy="13" r="8" /><path d="M12 9v4l2.8 2" /><path d="M9.5 2.5h5M12 2.5V5" />
                            @break
                        @case('grass')
                            <path d="M3 20.5h18" /><path d="M5.5 20.5c.3-3.8 1.4-6.6 3.4-8.6M9.5 20.5c0-5 .9-9.1 3-12.5M14 20.5c.2-4 1.3-6.9 3.4-8.9M18.5 20.5c0-2.4-.6-4.4-1.8-6" />
                            @break
                        @case('mirror')
                            <path d="M12 3v18" stroke-dasharray="2 2.4" /><path d="M9.5 7.5L4.5 12l5 4.5M14.5 7.5l5 4.5-5 4.5" />
                            @break
                        @case('thread')
                            <circle cx="6" cy="6" r="2.6" /><circle cx="12" cy="12" r="2.6" /><circle cx="18" cy="18" r="2.6" /><path d="M7.9 7.9l2.2 2.2M13.9 13.9l2.2 2.2" />
                            @break
                        @case('pothole')
                            <path d="M3 6.5l6 6 4-4 8 8" /><path d="M21 12.5v4h-4" />
                            @break
                        @case('dog')
                            <path d="M6.2 9.5L4 4.5l4.5 2.5h7L20 4.5l-2.2 5" /><path d="M6.2 9.5c-.4 1-.7 2.1-.7 3.3 0 4 2.9 7.2 6.5 7.2s6.5-3.2 6.5-7.2c0-1.2-.3-2.3-.7-3.3" /><path d="M10.3 16.2h3.4L12 18z" fill="currentColor" /><circle cx="9.3" cy="12.2" r=".9" fill="currentColor" /><circle cx="14.7" cy="12.2" r=".9" fill="currentColor" />
                            @break
                        @case('mouse')
                            <circle cx="7" cy="7.5" r="3.2" /><circle cx="17" cy="7.5" r="3.2" /><path d="M5.5 13.5c0-3 2.9-5 6.5-5s6.5 2 6.5 5c0 3.6-3.3 7-6.5 7s-6.5-3.4-6.5-7z" /><circle cx="12" cy="17.6" r="1" fill="currentColor" /><path d="M9.6 14h.01M14.4 14h.01" stroke-width="2.6" />
                            @break
                        @default
                            <path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z" /><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3" />
                    @endswitch
                @endif
            </svg>
        </span>
    </span>
</span>
