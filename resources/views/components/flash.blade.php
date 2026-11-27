@php
    $messages = array_filter([
        'ok' => session('success'),
        'fail' => session('error'),
    ]);
@endphp

@foreach ($messages as $type => $message)
    <div x-data="{ shown: true }" x-show="shown" class="mx-auto w-full max-w-6xl px-6 pb-4">
        <div
            class="paper surface-light flex items-center gap-4 px-5 py-3"
            role="{{ $type === 'fail' ? 'alert' : 'status' }}"
        >
            <x-sign :type="$type" class="size-11" />
            <p class="flex-1 text-xl font-bold">{{ $message }}</p>
            <button type="button" class="btn btn-sm" @click="shown = false">Cerrar</button>
        </div>
    </div>
@endforeach
