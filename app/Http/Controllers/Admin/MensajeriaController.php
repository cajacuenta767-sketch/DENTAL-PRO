<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Services\MensajeriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Envío de un mensaje de prueba por WhatsApp o SMS desde Ajustes.
 * Ruta esperada: POST admin/ajustes/mensajeria/probar (admin.ajustes.mensajeria.probar, permiso ajustes.editar).
 */
class MensajeriaController extends Controller
{
    public function probar(Request $request, MensajeriaService $mensajeria): RedirectResponse
    {
        $datos = $request->validate([
            'numero' => ['required', 'string', 'max:30'],
            'canal' => ['required', Rule::in(['whatsapp', 'sms'])],
        ], [], ['numero' => 'número de destino', 'canal' => 'canal']);

        $clinica = Ajuste::actual()->nombre;
        $texto = "Mensaje de prueba de {$clinica}: la mensajería por ".strtoupper($datos['canal']).' está funcionando.';

        $resultado = $datos['canal'] === 'sms'
            ? $mensajeria->enviarSms($datos['numero'], $texto)
            : $mensajeria->enviarWhatsapp($datos['numero'], $texto);

        $destino = MensajeriaService::normalizarNumero($datos['numero']) ?: $datos['numero'];
        $proveedor = MensajeriaService::nombreProveedor();

        if (! $resultado->exito) {
            return back()->withInput()->with('error', "No se pudo enviar el mensaje de prueba a {$destino}: {$resultado->error}");
        }

        return back()->with('exito', "Mensaje de prueba enviado a {$destino} por {$datos['canal']} (proveedor {$proveedor})."
            .($resultado->idExterno ? " Referencia: {$resultado->idExterno}." : ''));
    }
}
