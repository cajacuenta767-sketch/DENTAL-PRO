<?php

namespace App\Rules;

use App\Models\Cita;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La cita enlazada a una consulta, receta, estudio, presupuesto o recibo
 * tiene que ser del mismo paciente. Sin esta comprobación un identificador
 * manipulado colgaría el registro de la agenda de otra persona.
 */
class CitaDelPaciente implements ValidationRule
{
    public function __construct(private readonly int|string|null $pacienteId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $pacienteId = Cita::whereKey($value)->value('paciente_id');

        if ($pacienteId === null || (int) $pacienteId !== (int) $this->pacienteId) {
            $fail('La cita seleccionada no pertenece a este paciente.');
        }
    }
}
