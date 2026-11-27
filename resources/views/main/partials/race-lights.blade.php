{{-- Traffic-light start over the whole screen: every car on the start line, three red lamps, then green. --}}
<div
    x-show="lights !== null"
    x-cloak
    x-transition:leave.opacity.duration.400ms
    role="timer"
    :aria-label="lightsWord === '¡Ya!' ? 'Salida' : 'Salida en ' + lightsWord"
    class="grain vignette fixed inset-0 z-40 overflow-hidden bg-stage"
>
    <div aria-hidden="true" class="absolute inset-y-0 left-1/2 w-[min(760px,100vw)] -translate-x-1/2 border-x-8 border-ink bg-asphalt">
        <div class="absolute inset-x-0 top-[25rem] h-9 border-y-4 border-ink bg-[repeating-linear-gradient(90deg,#FBF8EF_0_36px,#000_36px_72px)]"></div>
        <div class="absolute inset-x-0 top-[27.25rem] h-9 border-b-4 border-ink bg-[repeating-linear-gradient(90deg,#000_0_36px,#FBF8EF_36px_72px)]"></div>
    </div>

    <ol aria-label="Parrilla" class="relative mx-auto flex max-w-[760px] flex-wrap justify-center gap-y-6 px-6 pt-[31rem] pb-16">
        <template x-for="(row, i) in standings" :key="row.id">
            <li class="flex w-1/4 flex-col items-center gap-1.5" :class="i % 2 ? 'translate-y-10' : ''">
                <svg aria-hidden="true" viewBox="0 0 64 96" class="h-16 w-11">
                    <rect x="4" y="12" width="10" height="20" rx="4" fill="#000000" /><rect x="50" y="12" width="10" height="20" rx="4" fill="#000000" />
                    <rect x="4" y="64" width="10" height="20" rx="4" fill="#000000" /><rect x="50" y="64" width="10" height="20" rx="4" fill="#000000" />
                    <rect x="9" y="4" width="46" height="88" rx="18" :fill="row.color" stroke="#000000" stroke-width="5" />
                    <rect x="16" y="22" width="32" height="18" rx="6" fill="#9FC4BE" stroke="#000000" stroke-width="4" />
                    <rect x="18" y="62" width="28" height="12" rx="4" fill="#9FC4BE" stroke="#000000" stroke-width="4" />
                </svg>
                <span class="max-w-full truncate text-lg font-black text-enamel [text-shadow:2px_2px_0_#000]" x-text="row.name"></span>
            </li>
        </template>
    </ol>

    <div class="absolute top-6 left-1/2 flex -translate-x-1/2 flex-col items-center gap-3.5">
        <div class="flex flex-col gap-3 rounded-[34px] border-[6px] border-ink bg-ink px-4 py-4 shadow-[0_0_0_6px_#3A3A36,12px_12px_0_6px_#000]">
            <template x-for="lamp in [1, 2, 3]" :key="lamp">
                <span
                    class="block size-16 rounded-full border-[5px] border-[#1A1A18] sm:size-20"
                    :class="lampOn(lamp) ? 'lamp-on' : ''"
                    :style="lampStyle(lamp)"
                ></span>
            </template>
        </div>
        <span class="title-ink text-6xl leading-none" x-text="lightsWord"></span>
    </div>
</div>
