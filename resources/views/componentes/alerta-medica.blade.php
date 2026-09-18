@props(['paciente'])

@if ($paciente && $paciente->tiene_alertas_medicas)
    <div class="alert alert-danger shadow-sm border-2 border-danger mb-3" role="alert">
        <div class="d-flex align-items-start gap-2">
            <div class="text-danger flex-shrink-0 mt-1">
                <i class="ti ti-alert-octagon fs-1 animate__animated animate__pulse animate__infinite"></i>
            </div>
            <div class="flex-grow-1">
                <h4 class="alert-title text-danger fw-bold d-flex align-items-center gap-2 mb-1">
                    <span>¡ATENCIÓN! ALERTAS MÉDICAS DEL PACIENTE</span>
                    <span class="badge bg-danger text-white">REVISIÓN OBLIGATORIA</span>
                </h4>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach ($paciente->lista_alertas as $alerta)
                        <span class="badge bg-{{ $alerta['color'] }} text-white p-2 d-inline-flex align-items-center gap-1 fs-4">
                            <i class="ti {{ $alerta['icono'] }} fs-3"></i>
                            <strong>{{ $alerta['texto'] }}</strong>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif