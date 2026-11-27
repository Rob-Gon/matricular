@extends('layouts.master-layout')

@section('title', 'Mi Perfil')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mt-5">
                <div class="card-header">
                    <h3 class="text-center">Mi Perfil</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}" id="profile-form">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre:</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ auth()->user()->name }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" class="form-control" id="email" name="email" value="{{ auth()->user()->email }}" required>
                        </div>

                        <hr class="my-4">

                        <h4 class="mb-3">Cambiar Contraseña</h4>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Contraseña Actual:</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nueva Contraseña:</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>

                        <div class="mb-3">
                            <label for="new_password_confirmation" class="form-label">Confirmar Nueva Contraseña:</label>
                            <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation">
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
                        </div>
                    </form>
                    <div class="d-grid mt-3">
                        <form method="POST" action="{{ route('profile.delete') }}" id="delete-profile-form"> 
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-danger w-100" onclick="confirmDeleteProfile()">Eliminar Cuenta</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 