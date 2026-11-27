@extends('layouts.master-layout')

@section('title', 'Logros')

@section('content')
<div class="container pt-5 pb-5 mb-5">
    <h1 class="text-center mb-2">Logros</h1>
    <p class="text-center text-light mb-5">
        Has desbloqueado {{ $unlocked->count() }} de {{ $achievements->count() }}
    </p>

    @if($achievements->isEmpty())
        <p class="text-center text-light">Todavía no hay logros disponibles.</p>
    @else
        <ul class="row list-unstyled g-4">
            @foreach($achievements as $achievement)
                @php
                    $owned = $unlocked->get($achievement->id);
                    $concealed = $achievement->hidden && ! $owned;
                @endphp
                <li class="col-md-6 col-lg-4">
                    <article class="card h-100 {{ $owned ? 'border-warning' : 'opacity-75' }}">
                        <div class="card-body">
                            <h2 class="h5 card-title">
                                <span class="visually-hidden">{{ $owned ? 'Desbloqueado:' : 'Bloqueado:' }}</span>
                                {{ $concealed ? '???' : $achievement->name }}
                            </h2>
                            <p class="card-text">
                                {{ $concealed ? 'Logro oculto. Sigue jugando para descubrirlo.' : $achievement->description }}
                            </p>
                            @if($owned)
                                <p class="card-text small text-light mb-0">
                                    Desbloqueado el {{ \Illuminate\Support\Carbon::parse($owned->pivot->unlocked_at)->format('d/m/Y H:i') }}
                                </p>
                            @endif
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
