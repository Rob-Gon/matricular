{{-- One plate of the trip receipt: what was played on it, answer times and words that also solved it. --}}
@php
    $chip = 'rounded-full border-[3px] border-ink px-4 py-1.5 text-[1.375rem] font-bold';
    $tag = 'rounded-lg border-2 border-ink bg-mustard px-2.5 py-0.5 text-base font-black';
@endphp

<li class="grid gap-4 border-t-[3px] border-dashed border-ink py-4 sm:grid-cols-[13rem_minmax(0,1fr)] sm:items-start sm:gap-5">
    <x-plate :letters="$entry['plate']" :digits="$entry['digits']" scale="0.45" />
    <div class="flex flex-col gap-2.5">
        <div class="flex flex-wrap items-center gap-2.5">
            @foreach ($entry['attempts'] as $attempt)
                <span @class([
                    $chip,
                    'bg-enamel' => $attempt->valid,
                    'bg-signal text-white line-through' => ! $attempt->valid && ! $attempt->isRepeated(),
                    'bg-enamel text-muted' => $attempt->isRepeated(),
                ])>
                    <span class="sr-only">{{ $attempt->valid ? 'Válida:' : 'No valió:' }}</span>
                    {{ $attempt->display() }}
                    @if ($attempt->valid && $attempt->elapsed_ms !== null)
                        <span class="text-base">{{ $seconds($attempt->elapsed_ms) }}</span>
                    @endif
                </span>
                @if ($attempt->is($report['fastest']))
                    <span class="{{ $tag }}">La más rápida</span>
                @endif
                @if ($attempt->is($report['longest']))
                    <span class="{{ $tag }}">La más larga</span>
                @endif
            @endforeach
            @if ($entry['skipped'])
                <span class="{{ $chip }} bg-celluloid">Pasamatrícula</span>
                <span class="text-xl font-bold">−{{ \App\Models\Run::POINTS_PENALTY }}</span>
            @endif
        </div>
        @if ($entry['examples'] !== [])
            <p class="text-xl leading-snug">
                {{ $entry['skipped'] && $entry['attempts'] === [] ? 'Podías haber hecho' : 'También valían' }}: {{ implode(', ', $entry['examples']) }}
            </p>
        @endif
    </div>
</li>
