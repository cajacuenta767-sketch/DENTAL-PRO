<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Contador por clave (recibos, presupuestos, folios, correlativos fiscales)
 * protegido con bloqueo de fila: dos emisiones simultáneas nunca obtienen
 * el mismo número.
 */
class Secuencia extends Model
{
    protected $table = 'secuencias';

    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['clave', 'valor'];

    /**
     * Devuelve el siguiente valor de la secuencia. Si la clave aún no existe
     * se inicializa con $inicial() (normalmente el máximo ya registrado), de
     * modo que los datos previos a esta tabla conservan su numeración.
     */
    public static function siguiente(string $clave, ?Closure $inicial = null): int
    {
        $avanzar = function () use ($clave, $inicial): int {
            $fila = static::query()->lockForUpdate()->find($clave);

            if (! $fila) {
                try {
                    $fila = static::query()->create([
                        'clave' => $clave,
                        'valor' => max(0, (int) ($inicial ? $inicial() : 0)),
                    ]);
                } catch (QueryException) {
                    // Otra conexión la creó en el mismo instante: reintenta con bloqueo.
                    $fila = static::query()->lockForUpdate()->findOrFail($clave);
                }
            }

            $fila->valor = (int) $fila->valor + 1;
            $fila->save();

            return (int) $fila->valor;
        };

        return DB::transactionLevel() > 0 ? $avanzar() : DB::transaction($avanzar);
    }
}
