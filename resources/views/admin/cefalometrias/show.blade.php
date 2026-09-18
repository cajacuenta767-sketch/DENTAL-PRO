@extends('layouts.admin')

@section('pretitulo', 'Ortodoncia')
@section('titulo', 'Estudio Cefalométrico · '.$paciente->nombre_completo)
@section('subtitulo', 'Fecha: '.$cefalometria->fecha->format('d/m/Y').' · Análisis: '.$cefalometria->tipo_analisis)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.cefalometrias.index', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
        <a href="{{ route('admin.cefalometrias.pdf', $cefalometria) }}" target="_blank" class="btn btn-outline-danger">
            <i class="ti ti-file-type-pdf me-1"></i>Imprimir PDF
        </a>
        <a href="{{ route('admin.cefalometrias.edit', $cefalometria) }}" class="btn btn-primary">
            <i class="ti ti-edit me-1"></i>Editar
        </a>
    </div>
@endsection

@section('contenido')
@php $medidas = $cefalometria->medidas ?? []; @endphp

<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Clasificación Esquelética" :valor="$cefalometria->diagnostico_esqueletico ?: 'Sin clasificar'"
               icono="ti ti-scan" color="{{ $cefalometria->diagnostico_esqueletico === 'CLASE_I' ? 'success' : 'warning' }}"
               :pie="'Ángulo ANB: '.($medidas['ANB'] ?? '—').'°'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Patrón de Crecimiento" :valor="\App\Models\TrazadoCefalometrico::PATRONES[$cefalometria->patron_crecimiento] ?? ($cefalometria->patron_crecimiento ?: '—')"
               icono="ti ti-ruler-measure" color="azure"
               :pie="'GoGn-SN: '.($medidas['GoGn_SN'] ?? '—').'°'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Posición Maxilar (SNA)" :valor="($medidas['SNA'] ?? '—').'°'"
               icono="ti ti-angle" color="indigo"
               :pie="'Norma: 82.0°'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Posición Mandibular (SNB)" :valor="($medidas['SNB'] ?? '—').'°'"
               icono="ti ti-angle" color="teal"
               :pie="'Norma: 80.0°'" />
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-table me-2"></i>Valores Cefalométricos Comparados</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Parámetro</th>
                            <th>Norma Poblacional</th>
                            <th>Valor del Paciente</th>
                            <th>Desviación Clínica</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>SNA</strong> (Maxilar a base de cráneo)</td>
                            <td>82.0° ± 2°</td>
                            <td class="fw-bold">{{ $medidas['SNA'] ?? '—' }}°</td>
                            <td>
                                @if (isset($medidas['SNA']))
                                    @if ($medidas['SNA'] > 84)<span class="badge bg-danger-lt">Prognatismo maxilar</span>
                                    @elseif ($medidas['SNA'] < 80)<span class="badge bg-warning-lt">Retrognatismo maxilar</span>
                                    @else<span class="badge bg-success-lt">Normoposición</span>@endif
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>SNB</strong> (Mandíbula a base de cráneo)</td>
                            <td>80.0° ± 2°</td>
                            <td class="fw-bold">{{ $medidas['SNB'] ?? '—' }}°</td>
                            <td>
                                @if (isset($medidas['SNB']))
                                    @if ($medidas['SNB'] > 82)<span class="badge bg-danger-lt">Prognatismo mandibular</span>
                                    @elseif ($medidas['SNB'] < 78)<span class="badge bg-warning-lt">Retrognatismo mandibular</span>
                                    @else<span class="badge bg-success-lt">Normoposición</span>@endif
                                @endif
                            </td>
                        </tr>
                        <tr class="table-primary-lt">
                            <td><strong>ANB</strong> (Relación sagital bases apicales)</td>
                            <td><strong>2.0° ± 2°</strong></td>
                            <td class="fw-bold fs-3 text-primary">{{ $medidas['ANB'] ?? '—' }}°</td>
                            <td>
                                @if (isset($medidas['ANB']))
                                    @if ($medidas['ANB'] > 4.0)<span class="badge bg-warning">Clase II Esquelética</span>
                                    @elseif ($medidas['ANB'] < 0.0)<span class="badge bg-danger">Clase III Esquelética</span>
                                    @else<span class="badge bg-success">Clase I Esquelética</span>@endif
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>GoGn-SN</strong> (Eje facial de crecimiento)</td>
                            <td>32.0° ± 3°</td>
                            <td class="fw-bold">{{ $medidas['GoGn_SN'] ?? '—' }}°</td>
                            <td>
                                @if (isset($medidas['GoGn_SN']))
                                    @if ($medidas['GoGn_SN'] > 35)<span class="badge bg-orange-lt">Dolicofacial</span>
                                    @elseif ($medidas['GoGn_SN'] < 29)<span class="badge bg-purple-lt">Braquifacial</span>
                                    @else<span class="badge bg-success-lt">Mesofacial</span>@endif
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td><strong>1-NA</strong> (Incisivo Sup a línea NA)</td>
                            <td>22.0° / 4.0 mm</td>
                            <td>{{ $medidas['UI_NA_deg'] ?? '—' }}° · {{ $medidas['UI_NA_mm'] ?? '—' }} mm</td>
                            <td class="text-secondary small">Protrusión / inclinación superior</td>
                        </tr>
                        <tr>
                            <td><strong>1-NB</strong> (Incisivo Inf a línea NB)</td>
                            <td>25.0° / 4.0 mm</td>
                            <td>{{ $medidas['LI_NB_deg'] ?? '—' }}° · {{ $medidas['LI_NB_mm'] ?? '—' }} mm</td>
                            <td class="text-secondary small">Protrusión / inclinación inferior</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-notes me-2"></i>Informe Ortodóntico</h3></div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-secondary small text-uppercase">Interpretación Clínica</div>
                    <div class="mt-1">{!! nl2br(e($cefalometria->interpretacion ?: 'Sin interpretación clínica redactada.')) !!}</div>
                </div>
                <div class="border-top pt-3">
                    <div class="text-secondary small text-uppercase">Plan de Tratamiento</div>
                    <div class="mt-1">{!! nl2br(e($cefalometria->plan_tratamiento ?: 'Sin plan de tratamiento registrado.')) !!}</div>
                </div>
            </div>
        </div>

        @if ($cefalometria->imagen_radiografia)
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-photo me-2"></i>Telerradiografía Adjunta</h3></div>
                <div class="card-body text-center p-2">
                    <img src="{{ asset('storage/'.$cefalometria->imagen_radiografia) }}" alt="Cefalometría" class="img-fluid rounded border">
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
