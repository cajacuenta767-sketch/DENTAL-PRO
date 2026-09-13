@if (session('exito'))
    <div class="alert alert-success alert-dismissible" role="alert" data-auto-cerrar>
        <div class="d-flex">
            <div class="me-2"><i class="ti ti-circle-check fs-2"></i></div>
            <div>{{ session('exito') }}</div>
        </div>
        <a class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></a>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible" role="alert">
        <div class="d-flex">
            <div class="me-2"><i class="ti ti-alert-circle fs-2"></i></div>
            <div>{{ session('error') }}</div>
        </div>
        <a class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></a>
    </div>
@endif

@if (session('aviso'))
    <div class="alert alert-warning alert-dismissible" role="alert">
        <div class="d-flex">
            <div class="me-2"><i class="ti ti-alert-triangle fs-2"></i></div>
            <div>{{ session('aviso') }}</div>
        </div>
        <a class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></a>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible" role="alert">
        <h4 class="alert-title">Revisa los datos ingresados</h4>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <a class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></a>
    </div>
@endif
