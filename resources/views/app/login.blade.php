<x-layouts.app title="Entrar" description="Entra en Matricular para jugar y guardar tus puntuaciones y logros.">
    <div class="px-6 pt-4 pb-24">
        <div class="mx-auto flex max-w-xl flex-col gap-8">
            <h1 class="title-ink text-[clamp(3rem,8vw,4.5rem)]">Entrar</h1>

            <form method="POST" action="{{ route('login') }}" class="paper surface-light flex flex-col gap-5 p-7">
                @csrf
                <x-field name="email" label="Correo electrónico" type="email" autocomplete="email" required autofocus />
                <x-field name="password" label="Contraseña" type="password" autocomplete="current-password" required />

                <label class="flex min-h-11 items-center gap-3 text-xl font-bold">
                    <input type="checkbox" name="remember" value="1" class="size-6 accent-plate" @checked(old('remember'))>
                    Recordarme en este dispositivo
                </label>

                <button type="submit" class="btn btn-primary self-start">Entrar</button>
            </form>

            <p class="text-xl">¿Aún no tienes cuenta? <a class="font-bold underline hover:text-mustard" href="{{ route('register.view') }}">Crea una</a>.</p>
        </div>
    </div>
</x-layouts.app>
