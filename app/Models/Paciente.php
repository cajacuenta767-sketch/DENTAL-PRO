<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Paciente extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'pacientes';

    protected $fillable = [
        'clinica_id',
        'usuario_id', 'aseguradora_id', 'numero_afiliado',
        'nombres', 'apellidos', 'tipo_documento', 'numero_documento',
        'fecha_nacimiento', 'genero', 'direccion', 'telefono', 'email',
        'grupo_sanguineo', 'alergias', 'enfermedades', 'medicamentos', 'habitos',
        'antecedentes', 'contacto_emergencia', 'telefono_emergencia',
        'observaciones', 'fotografia', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function aseguradora(): BelongsTo
    {
        return $this->belongsTo(Aseguradora::class, 'aseguradora_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'paciente_id');
    }

    public function estudios(): HasMany
    {
        return $this->hasMany(EstudioImagen::class, 'paciente_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoClinico::class, 'paciente_id');
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class, 'paciente_id');
    }

    public function historiales(): HasMany
    {
        return $this->hasMany(HistorialClinico::class, 'paciente_id');
    }

    public function odontogramas(): HasMany
    {
        return $this->hasMany(Odontograma::class, 'paciente_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'paciente_id');
    }

    public function periodontogramas(): HasMany
    {
        return $this->hasMany(Periodontograma::class, 'paciente_id');
    }

    public function conductometrias(): HasMany
    {
        return $this->hasMany(Conductometria::class, 'paciente_id');
    }

    public function cefalometrias(): HasMany
    {
        return $this->hasMany(TrazadoCefalometrico::class, 'paciente_id');
    }

    public function implantes(): HasMany
    {
        return $this->hasMany(ImplantePaciente::class, 'paciente_id');
    }

    public function listaEspera(): HasMany
    {
        return $this->hasMany(ListaEspera::class, 'paciente_id');
    }

    /** Disco privado para la fotografía: nunca se sirve sin sesión. */
    public const DISCO_FOTOS = 'local';

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    /** URL autenticada de la fotografía (o null si no tiene). */
    public function getFotoUrlAttribute(): ?string
    {
        return $this->fotografia ? route('admin.pacientes.foto', $this) : null;
    }

    /** Teléfono en formato internacional sin símbolos, listo para wa.me. */
    public function getWhatsappNumeroAttribute(): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $this->telefono);

        return strlen($digitos) >= 8 ? $digitos : null;
    }

    public function getWhatsappUrlAttribute(): ?string
    {
        return $this->whatsapp_numero ? 'https://wa.me/'.$this->whatsapp_numero : null;
    }

    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento?->age;
    }

    /** Saldo pendiente sumando todos los recibos no anulados. */
    public function getSaldoPendienteAttribute(): float
    {
        return (float) $this->pagos()->where('estado', '!=', 'ANULADO')->sum('monto_saldo');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function ordenesLaboratorio(): HasMany
    {
        return $this->hasMany(OrdenLaboratorio::class, 'paciente_id');
    }

    /** Indica si el paciente tiene alertas médicas críticas que requieren atención especial. */
    public function getTieneAlertasMedicasAttribute(): bool
    {
        return filled($this->alergias) || filled($this->enfermedades) || filled($this->medicamentos);
    }

    /** Desglosa las alertas médicas en tarjetas / badges con icono y nivel de severidad. */
    public function getListaAlertasAttribute(): array
    {
        $alertas = [];

        if (filled($this->alergias)) {
            $alergiasTexto = mb_strtolower((string) $this->alergias);

            if (str_contains($alergiasTexto, 'penicil') || str_contains($alergiasTexto, 'amoxi') || str_contains($alergiasTexto, 'betalact')) {
                $alertas[] = [
                    'tipo' => 'ALERGIA_PENICILINA',
                    'texto' => 'Alergia a Penicilinas / Betalactámicos',
                    'color' => 'danger',
                    'icono' => 'ti-alert-octagon',
                    'critica' => true,
                ];
            }

            if (str_contains($alergiasTexto, 'latex') || str_contains($alergiasTexto, 'látex')) {
                $alertas[] = [
                    'tipo' => 'ALERGIA_LATEX',
                    'texto' => 'Alergia al Látex (Usar nitrilo y dique sin látex)',
                    'color' => 'danger',
                    'icono' => 'ti-hand-off',
                    'critica' => true,
                ];
            }

            if (str_contains($alergiasTexto, 'anest')) {
                $alertas[] = [
                    'tipo' => 'ALERGIA_ANESTESICO',
                    'texto' => 'Alergia / Reacción a Anestésicos locales',
                    'color' => 'danger',
                    'icono' => 'ti-needle',
                    'critica' => true,
                ];
            }

            if (empty($alertas)) {
                $alertas[] = [
                    'tipo' => 'ALERGIA_GENERAL',
                    'texto' => "Alergia: {$this->alergias}",
                    'color' => 'warning',
                    'icono' => 'ti-shield-alert',
                    'critica' => false,
                ];
            }
        }

        if (filled($this->enfermedades)) {
            $enfTexto = mb_strtolower((string) $this->enfermedades);

            if (str_contains($enfTexto, 'diabet')) {
                $alertas[] = [
                    'tipo' => 'DIABETES',
                    'texto' => 'Diabetes (Monitorear glucemia y cicatrización)',
                    'color' => 'warning',
                    'icono' => 'ti-droplet',
                    'critica' => false,
                ];
            }

            if (str_contains($enfTexto, 'hipertens') || str_contains($enfTexto, 'presion') || str_contains($enfTexto, 'presión')) {
                $alertas[] = [
                    'tipo' => 'HIPERTENSION',
                    'texto' => 'Hipertensión Arterial (Evitar vasoconstrictor excesivo)',
                    'color' => 'warning',
                    'icono' => 'ti-heart-rate-monitor',
                    'critica' => false,
                ];
            }

            if (str_contains($enfTexto, 'anticoagul') || str_contains($enfTexto, 'hemofil') || str_contains($enfTexto, 'sangr')) {
                $alertas[] = [
                    'tipo' => 'ANTICOAGULANTE',
                    'texto' => 'Riesgo de Hemorragia / Trastorno de coagulación',
                    'color' => 'danger',
                    'icono' => 'ti-droplet-filled',
                    'critica' => true,
                ];
            }
        }

        if (filled($this->medicamentos)) {
            $medTexto = mb_strtolower((string) $this->medicamentos);

            $tieneAnticoag = str_contains($medTexto, 'anticoagul') || str_contains($medTexto, 'warfar')
                || str_contains($medTexto, 'sintrom') || str_contains($medTexto, 'aspirin')
                || str_contains($medTexto, 'eliquis') || str_contains($medTexto, 'xarelto')
                || str_contains($medTexto, 'clopidogrel');

            if ($tieneAnticoag && !in_array('ANTICOAGULANTE', array_column($alertas, 'tipo'), true)) {
                $alertas[] = [
                    'tipo' => 'ANTICOAGULANTE',
                    'texto' => 'Medicación Anticoagulante / Antiagregante (Riesgo quirúrgico)',
                    'color' => 'danger',
                    'icono' => 'ti-droplet-filled',
                    'critica' => true,
                ];
            }

            if (str_contains($medTexto, 'alendron') || str_contains($medTexto, 'bifosfon') || str_contains($medTexto, 'zoledron') || str_contains($medTexto, 'prolia')) {
                $alertas[] = [
                    'tipo' => 'BIFOSFONATOS',
                    'texto' => 'Bifosfonatos / Antirresortivos (Riesgo de osteonecrosis maxilar)',
                    'color' => 'danger',
                    'icono' => 'ti-bone',
                    'critica' => true,
                ];
            }
        }

        return $alertas;
    }

    public function scopeBuscar($query, ?string $termino)
    {
        if (blank($termino)) {
            return $query;
        }

        $t = '%'.mb_strtolower($termino).'%';

        return $query->where(function ($q) use ($t) {
            $q->whereRaw('LOWER(nombres) LIKE ?', [$t])
                ->orWhereRaw('LOWER(apellidos) LIKE ?', [$t])
                ->orWhereRaw('LOWER(numero_documento) LIKE ?', [$t])
                ->orWhereRaw('LOWER(COALESCE(telefono, \'\')) LIKE ?', [$t])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$t]);
        });
    }
}
