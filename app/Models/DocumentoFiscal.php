<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DocumentoFiscal extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory;

    protected $table = 'documentos_fiscales';

    protected $fillable = [
        'pago_id', 'usuario_id', 'documento_referencia_id', 'tipo', 'serie', 'correlativo',
        'numero_control', 'codigo_generacion',
        'receptor_nombre', 'receptor_documento', 'receptor_direccion', 'receptor_email',
        'subtotal', 'descuento', 'iva', 'total', 'tasa_iva',
        'estado', 'sello_recepcion', 'contenido', 'motivo_anulacion', 'fecha_emision',
        'proveedor', 'estado_transmision', 'respuesta_proveedor', 'transmitido_en',
    ];

    protected function casts(): array
    {
        return [
            'correlativo' => 'integer',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
            'tasa_iva' => 'decimal:2',
            'contenido' => 'array',
            'fecha_emision' => 'datetime',
            'respuesta_proveedor' => 'array',
            'transmitido_en' => 'datetime',
        ];
    }

    public const TIPOS = [
        'FACTURA' => 'Factura de consumidor final',
        'CREDITO_FISCAL' => 'Comprobante de crédito fiscal',
        'NOTA_CREDITO' => 'Nota de crédito',
        'NOTA_DEBITO' => 'Nota de débito',
    ];

    /** Código de tipo de documento usado al componer el número de control. */
    public const CODIGOS_TIPO = [
        'FACTURA' => '01',
        'CREDITO_FISCAL' => '03',
        'NOTA_CREDITO' => '05',
        'NOTA_DEBITO' => '06',
    ];

    public const COLORES = [
        'BORRADOR' => 'secondary',
        'EMITIDO' => 'success',
        'ANULADO' => 'danger',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** Documento que esta nota de crédito o débito corrige. */
    public function documentoReferencia(): BelongsTo
    {
        return $this->belongsTo(self::class, 'documento_referencia_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(self::class, 'documento_referencia_id');
    }

    /** Facturas y comprobantes; excluye las notas de crédito y débito. */
    public function scopeVentas($query)
    {
        return $query->whereIn('tipo', ['FACTURA', 'CREDITO_FISCAL']);
    }

    /** Correlativo por tipo y serie desde una secuencia bloqueada. */
    public static function siguienteCorrelativo(string $tipo, string $serie): int
    {
        $serie = mb_strtoupper($serie);

        return Secuencia::siguiente("fiscal-{$tipo}-{$serie}", fn () => (int) static::query()
            ->where('tipo', $tipo)
            ->where('serie', $serie)
            ->max('correlativo'));
    }

    public function getEsNotaAttribute(): bool
    {
        return in_array($this->tipo, ['NOTA_CREDITO', 'NOTA_DEBITO'], true);
    }

    /** Número de control con el formato DTE-TT-SERIE-000000000000000. */
    public static function componerNumeroControl(string $tipo, string $serie, int $correlativo): string
    {
        return sprintf(
            'DTE-%s-%s-%015d',
            self::CODIGOS_TIPO[$tipo] ?? '01',
            str_pad(mb_strtoupper($serie), 4, '0', STR_PAD_LEFT),
            $correlativo
        );
    }

    public static function nuevoCodigoGeneracion(): string
    {
        return mb_strtoupper((string) Str::uuid());
    }

    /**
     * Desglosa un total que ya incluye IVA en base imponible e impuesto,
     * que es como se cobra en caja.
     */
    public static function desglosarIva(float $totalConIva, float $tasa): array
    {
        $factor = 1 + ($tasa / 100);
        $base = round($totalConIva / $factor, 2);

        return ['subtotal' => $base, 'iva' => round($totalConIva - $base, 2)];
    }

    public function getTipoLegibleAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getColorEstadoAttribute(): string
    {
        return self::COLORES[$this->estado] ?? 'secondary';
    }
}
