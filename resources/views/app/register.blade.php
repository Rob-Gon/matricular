<x-layouts.app title="Crear cuenta" description="Crea tu cuenta de Matricular para jugar, entrar en la clasificación y desbloquear logros.">
    <div class="px-6 pt-4 pb-24">
        <div class="mx-auto flex max-w-xl flex-col gap-8">
            <h1 class="title-ink text-[clamp(3rem,8vw,4.5rem)]">Crear cuenta</h1>

            <form method="POST" action="{{ route('register') }}" class="paper surface-light flex flex-col gap-5 p-7">
                @csrf
                <x-field name="name" label="Nombre" hint="Es el que verán los demás en la clasificación." autocomplete="nickname" maxlength="255" required autofocus />
                <x-field name="email" label="Correo electrónico" type="email" autocomplete="email" required />
                <x-field name="password" label="Contraseña" type="password" autocomplete="new-password" required />
                <x-field name="password_confirmation" label="Repite la contraseña" type="password" autocomplete="new-password" required />

                <button type="submit" class="btn btn-primary self-start">Crear cuenta</button>
            </form>

            <p class="text-xl">¿Ya tienes cuenta? <a class="font-bold underline hover:text-mustard" href="{{ route('login.view') }}">Entra</a>.</p>
        </div>
    </div>
</x-layouts.app>
