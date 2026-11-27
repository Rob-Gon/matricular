{{-- Live scoreboard of a race; updated by the "race" Alpine component from the lobby channel. --}}
<section aria-labelledby="marcador" class="paper order-last flex w-full max-w-[600px] flex-col gap-2.5 px-4 py-4 xl:order-none xl:col-start-1 xl:row-start-1 xl:max-w-[300px] xl:self-start xl:justify-self-start">
    <h2 id="marcador" class="font-display text-xl">En directo</h2>
    <ol class="flex max-h-[min(26rem,45dvh)] flex-col gap-1.5 overflow-y-auto">
        <template x-for="row in standings" :key="row.id">
            <li class="grid grid-cols-[1.75rem_1.25rem_minmax(0,1fr)_auto] items-center gap-2 rounded-[10px] px-2 py-1 text-xl" :class="row.id === me ? 'bg-[#F4D98A]' : ''">
                <span class="font-black" x-text="row.position + '.º'"></span>
                <span aria-hidden="true" class="size-[18px] rounded-full border-[3px] border-ink" :style="{ background: row.color }"></span>
                <span class="truncate font-bold" x-text="row.name + (row.id === me ? ' (tú)' : '')"></span>
                <span class="font-black tabular-nums" x-text="row.score"></span>
            </li>
        </template>
    </ol>
</section>
