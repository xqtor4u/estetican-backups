@extends('layouts.app')

@section('content')
<h1>Editar Cliente</h1>
<form action="{{ route('clients.update', $client) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="mb-3">
        <label>Nombre:</label>
        <input type="text" name="first_name" value="{{ $client->first_name }}" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Apellido:</label>
        <input type="text" name="last_name" value="{{ $client->last_name }}" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Email:</label>
        <input type="email" name="email" value="{{ $client->email }}" class="form-control" required>
    </div>

    <h4>Agregar Direcciones</h4>
    <div id="addresses">
        <!-- Campos dinámicos para nuevas direcciones -->
    </div>
    <button type="button" class="btn btn-secondary" onclick="addAddress()">Agregar Dirección</button>

    <h4>Agregar Teléfonos</h4>
    <div id="phones">
        <!-- Campos dinámicos para nuevos teléfonos -->
    </div>
    <button type="button" class="btn btn-secondary" onclick="addPhone()">Agregar Teléfono</button>

    <br><br>
    <button type="submit" class="btn btn-success">Actualizar</button>
    <a href="{{ route('clients.index') }}" class="btn btn-secondary">Cancelar</a>
</form>

<script>
let addressIndex = 0;
let phoneIndex = 0;

function addAddress() {
    const html = `
        <div class="address-item mb-3 border p-3">
            <div class="mb-2">
                <label>Tipo:</label>
                <select name="addresses[${addressIndex}][type]" class="form-control" required>
                    <option value="home">Casa</option>
                    <option value="work">Trabajo</option>
                </select>
            </div>
            <div class="mb-2">
                <label>Calle:</label>
                <input type="text" name="addresses[${addressIndex}][street]" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Colonia:</label>
                <input type="text" name="addresses[${addressIndex}][colonia]" class="form-control">
            </div>
            <div class="mb-2">
                <label>Ciudad:</label>
                <input type="text" name="addresses[${addressIndex}][city]" class="form-control" required>
            </div>
            <div class="mb-2">
                <label>Estado:</label>
                <input type="text" name="addresses[${addressIndex}][state]" class="form-control">
            </div>
            <div class="mb-2">
                <label>País:</label>
                <input type="text" name="addresses[${addressIndex}][country]" class="form-control" value="México" required>
            </div>
        </div>
    `;
    document.getElementById('addresses').insertAdjacentHTML('beforeend', html);
    addressIndex++;
}

function addPhone() {
    const html = `
        <div class="phone-item mb-3 border p-3">
            <div class="mb-2">
                <label>Tipo:</label>
                <select name="phones[${phoneIndex}][type]" class="form-control" required>
                    <option value="mobile">Móvil</option>
                    <option value="fixed">Fijo</option>
                </select>
            </div>
            <div class="mb-2">
                <label>Número:</label>
                <input type="text" name="phones[${phoneIndex}][number]" class="form-control" required>
            </div>
        </div>
    `;
    document.getElementById('phones').insertAdjacentHTML('beforeend', html);
    phoneIndex++;
}
</script>
@endsection