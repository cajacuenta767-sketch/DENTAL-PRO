{{-- Selector de vigencia compartido por emitir, renovar y reactivar. --}}
<div class="mb-3">
    <label class="form-label required">Vigencia</label>
    <div class="row g-2">
        <div class="col-12">
            <label class="form-check">
                <input type="radio" name="duracion" value="dias" class="form-check-input" @checked(old('duracion', 'dias') === 'dias')>
                <span class="form-check-label d-flex align-items-center gap-2">
                    <input type="number" name="dias" class="form-control form-control-sm" style="width: 6rem;"
                           min="1" max="3650" value="{{ old('dias', $dias) }}"> días desde hoy
                </span>
            </label>
        </div>
        <div class="col-12">
            <label class="form-check">
                <input type="radio" name="duracion" value="fecha" class="form-check-input" @checked(old('duracion') === 'fecha')>
                <span class="form-check-label d-flex align-items-center gap-2">
                    hasta el <input type="date" name="hasta" class="form-control form-control-sm" style="width: 11rem;" value="{{ old('hasta') }}">
                </span>
            </label>
        </div>
        <div class="col-12">
            <label class="form-check">
                <input type="radio" name="duracion" value="vitalicia" class="form-check-input" @checked(old('duracion') === 'vitalicia')>
                <span class="form-check-label">Vitalicia (sin vencimiento)</span>
            </label>
        </div>
    </div>
</div>
