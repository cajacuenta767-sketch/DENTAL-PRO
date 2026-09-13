<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AjusteController extends Controller
{
    public function edit(): View
    {
        return view('admin.ajustes.edit', ['ajuste' => Ajuste::actual()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $ajuste = Ajuste::actual();

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'divisa' => ['required', 'string', 'max:10'],
            'simbolo_divisa' => ['required', 'string', 'max:5'],
            'nit' => ['nullable', 'string', 'max:30'],
            'web' => ['nullable', 'url', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'minutos_intervalo_cita' => ['required', 'integer', 'min:5', 'max:180'],
            'horas_recordatorio' => ['required', 'integer', 'min:1', 'max:168'],
            'terminos_recibo' => ['nullable', 'string', 'max:2000'],
            'logo_archivo' => ['nullable', 'image', 'max:2048'],
        ], [], [
            'minutos_intervalo_cita' => 'intervalo entre citas',
            'horas_recordatorio' => 'horas de anticipación del recordatorio',
        ]);

        if ($request->hasFile('logo_archivo')) {
            if ($ajuste->logo) {
                Storage::disk('public')->delete($ajuste->logo);
            }
            $datos['logo'] = $request->file('logo_archivo')->store('clinica', 'public');
        }

        $ajuste->update(collect($datos)->except('logo_archivo')->all());

        return back()->with('exito', 'Los ajustes de la clínica fueron guardados.');
    }
}
