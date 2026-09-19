<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitacion extends Model
{
    protected $table = 'invitaciones';

    protected $fillable = ['clinica_id', 'creada_por', 'usada_por', 'email', 'rol', 'codigo_hash', 'codigo_cifrado', 'codigo_visible', 'usos_maximos', 'usos', 'vence_en', 'usada_en', 'activa', 'datos'];

    protected $hidden = ['codigo_hash', 'codigo_cifrado'];

    protected function casts(): array
    {
        return ['codigo_cifrado' => 'encrypted', 'vence_en' => 'datetime', 'usada_en' => 'datetime', 'activa' => 'boolean', 'datos' => 'array'];
    }

    public function clinica(): BelongsTo
    {
        return $this->belongsTo(Clinica::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creada_por');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usada_por');
    }

    public static function generarCodigo(string $prefijo = 'INV'): string
    {
        return $prefijo.'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }

    public static function hashCodigo(string $codigo): string
    {
        return hash('sha256', Str::upper(preg_replace('/\s+/', '', trim($codigo))));
    }

    public function estaDisponible(): bool
    {
        return $this->activa && $this->usos < $this->usos_maximos && (! $this->vence_en || $this->vence_en->isFuture());
    }
}
