<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Cita extends Model
{
    use Auditable, HasFactory;

    protected $table = 'citas';

    protected $fillable = [
        'token', 'paciente_id', 'doctor_id', 'tratamiento_id',
        'fecha', 'hora', 'estado', 'origen', 'motivo', 'observacion',
        'recordatorio_enviado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'recordatorio_enviado_en' => 'datetime',
        ];
    }

    public const ESTADOS = ['PENDIENTE', 'CONFIRMADA', 'EN_CURSO', 'COMPLETADA', 'CANCELADA'];

    /** Color Tabler asociado a cada estado, usado por badges y gráficas. */
    public const COLORES_ESTADO = [
        'PENDIENTE' => 'warning',
        'CONFIRMADA' => 'azure',
        'EN_CURSO' => 'orange',
        'COMPLETADA' => 'success',
        'CANCELADA' => 'danger',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cita) {
            $cita->token ??= strtoupper(Str::random(12));
        });
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function tratamiento(): BelongsTo
    {
        return $this->belongsTo(Tratamiento::class, 'tratamiento_id');
    }

    public function historial(): HasOne
    {
        return $this->hasOne(HistorialClinico::class, 'cita_id');
    }

    public function odontograma(): HasOne
    {
        return $this->hasOne(Odontograma::class, 'cita_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'cita_id');
    }

    public function estudios(): HasMany
    {
        return $this->hasMany(EstudioImagen::class, 'cita_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoClinico::class, 'cita_id');
    }

    public function getColorEstadoAttribute(): string
    {
        return self::COLORES_ESTADO[$this->estado] ?? 'secondary';
    }

    public function getEstadoLegibleAttribute(): string
    {
        return ucfirst(mb_strtolower(str_replace('_', ' ', $this->estado)));
    }

    public function getFechaHoraAttribute(): string
    {
        return $this->fecha->format('d/m/Y').' '.substr((string) $this->hora, 0, 5);
    }

    /** Un recibo cubre la cita cuando ya existe un pago no anulado. */
    public function getEstaPagadaAttribute(): bool
    {
        return $this->pagos->where('estado', 'COMPLETADO')->isNotEmpty();
    }

    public function scopeDelDia($query, ?string $fecha = null)
    {
        return $query->whereDate('fecha', $fecha ?? now()->toDateString());
    }

    public function scopeVigentes($query)
    {
        return $query->whereNotIn('estado', ['CANCELADA']);
    }

    public function scopeDelPaciente($query, Paciente|int $paciente)
    {
        return $query->where('paciente_id', $paciente instanceof Paciente ? $paciente->id : $paciente);
    }

    public function listaEspera(): HasOne
    {
        return $this->hasOne(ListaEspera::class, 'cita_id');
    }

    /** Minutos que ocupa la cita: la duración del tratamiento o, si no, el intervalo de agenda. */
    public function getDuracionMinutosAttribute(): int
    {
        $duracion = (int) ($this->tratamiento?->duracion ?? 0);

        return $duracion > 0 ? $duracion : max(5, (int) Ajuste::actual()->minutos_intervalo_cita);
    }

    public function getInicioAttribute(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fecha->toDateString().' '.substr((string) $this->hora, 0, 5));
    }

    public function getFinAttribute(): CarbonImmutable
    {
        return $this->inicio->addMinutes($this->duracion_minutos);
    }

    /** Todavía no ocurrió y no está cerrada. */
    public function getEsFuturaAttribute(): bool
    {
        return in_array($this->estado, ['PENDIENTE', 'CONFIRMADA'], true) && $this->inicio->isFuture();
    }

    /** El paciente puede cancelar desde el portal con la anticipación mínima configurada. */
    public function getCancelablePorPacienteAttribute(): bool
    {
        $minimo = max(1, (int) Ajuste::actual()->reservas_minimo_horas);

        return $this->es_futura && $this->inicio->greaterThan(now()->addHours($minimo));
    }

    /** Enlace de WhatsApp con el mensaje de confirmación prellenado. */
    public function getWhatsappUrlAttribute(): ?string
    {
        $numero = $this->paciente?->whatsapp_numero;

        if (! $numero) {
            return null;
        }

        $clinica = Ajuste::actual()->nombre;
        $texto = "Hola {$this->paciente->nombres}, te escribimos de {$clinica}. "
            ."Tu cita es el {$this->fecha_hora} con {$this->doctor?->nombre_profesional}. ¿Nos confirmas tu asistencia?";

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($texto);
    }
}
