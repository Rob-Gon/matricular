<x-layouts.app title="Perfil" noindex>
    <div class="px-6 pt-4 pb-24">
        <div
            class="mx-auto flex max-w-xl flex-col gap-8"
            x-data
            x-init="@if ($errors->deleteAccount->any()) $nextTick(() => $refs.deleteDialog.showModal()) @endif"
        >
            <h1 class="title-ink text-[clamp(3rem,8vw,4.5rem)]">Perfil</h1>

            <form method="POST" action="{{ route('profile.update') }}" class="paper surface-light flex flex-col gap-5 p-7">
                @csrf
                @method('PUT')
                <h2 class="font-display text-2xl">Tus datos</h2>
                <x-field name="name" label="Nombre" :value="auth()->user()->name" autocomplete="nickname" maxlength="255" required />
                <x-field name="email" label="Correo electrónico" type="email" :value="auth()->user()->email" autocomplete="email" required />

                <h2 class="mt-3 font-display text-2xl">Cambiar contraseña</h2>
                <p class="text-lg text-muted">Déjalo en blanco para mantener la actual.</p>
                <x-field name="current_password" label="Contraseña actual" type="password" autocomplete="current-password" />
                <x-field name="new_password" label="Contraseña nueva" type="password" autocomplete="new-password" />
                <x-field name="new_password_confirmation" label="Repite la contraseña nueva" type="password" autocomplete="new-password" />

                <button type="submit" class="btn btn-primary self-start">Guardar cambios</button>
            </form>

            <section aria-labelledby="borrar" class="paper surface-light flex flex-col gap-4 p-7">
                <h2 id="borrar" class="font-display text-2xl">Eliminar cuenta</h2>
                <p class="text-xl">Se borran tu cuenta, tus partidas y tus logros. No se puede deshacer.</p>
                <button type="button" class="btn btn-danger self-start" @click="$refs.deleteDialog.showModal()">Eliminar cuenta</button>
            </section>

            <dialog x-ref="deleteDialog" aria-labelledby="borrar-titulo" class="paper surface-light m-auto w-[min(32rem,calc(100%-2rem))] p-7 backdrop:bg-ink/60">
                <form method="POST" action="{{ route('profile.delete') }}" class="flex flex-col gap-5">
                    @csrf
                    @method('DELETE')
                    <h2 id="borrar-titulo" class="font-display text-2xl">¿Eliminar tu cuenta?</h2>
                    <p class="text-xl">Escribe tu contraseña para confirmarlo.</p>
                    <x-field name="password" label="Contraseña" type="password" bag="deleteAccount" autocomplete="current-password" required />
                    <div class="flex flex-wrap gap-4">
                        <button type="submit" class="btn btn-danger">Eliminar cuenta</button>
                        <button type="button" class="btn" @click="$refs.deleteDialog.close()">Cancelar</button>
                    </div>
                </form>
            </dialog>
        </div>
    </div>
</x-layouts.app>
