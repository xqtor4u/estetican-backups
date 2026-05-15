@php
    $screenDebugId = 'OprRolNew';
    use App\Support\Pages\OperatorRolesPage;

    $page = OperatorRolesPage::create();
    $breadcrumbs = $page['breadcrumbs'];
@endphp
@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <h5 class="mb-0">Dar de alta tipo de operador</h5>
                        <p class="text-body-secondary small mb-0">Captura la información base o clona uno existente.</p>
                    </div>
                    <div class="col-md-7">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-layers text-primary"></i></span>
                            <select class="form-select border-start-0" onchange="if(this.value) window.location.href='{{ route('operator-roles.create') }}?copy_from=' + this.value">
                                <option value="">¿Deseas copiar los datos de otro tipo?</option>
                                @foreach($existingRoles as $existing)
                                    <option value="{{ $existing->id }}" @selected(isset($copySource) && $copySource->id == $existing->id)>
                                        {{ $existing->name }} ({{ $existing->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form action="{{ route('operator-roles.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo ?? url()->previous()) }}">
                    @include('operator-roles.partials.form', ['submitLabel' => 'Guardar tipo'])
                </form>
            </div>
        </div>
    </div>
</div>
@endsection