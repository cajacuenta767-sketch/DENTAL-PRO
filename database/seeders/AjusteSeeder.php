<?php

namespace Database\Seeders;

use App\Models\Ajuste;
use Illuminate\Database\Seeder;

class AjusteSeeder extends Seeder
{
    public function run(): void
    {
        Ajuste::updateOrCreate(['id' => 1], [
            'nombre' => 'Clínica Dental Salud & Estética',
            'descripcion' => 'Odontología integral con tecnología de punta',
            'direccion' => 'Av. Ballivián #1234, Zona Central · La Paz, Bolivia',
            'telefono' => '+591 2 2456789',
            'email' => 'contacto@saludyestetica.bo',
            'divisa' => env('CLINICA_MONEDA', 'BOB'),
            'simbolo_divisa' => 'Bs',
            'nit' => '1023456789',
            'web' => 'https://saludyestetica.bo',
            'whatsapp' => '+591 70012345',
            'minutos_intervalo_cita' => 30,
            'horas_recordatorio' => 24,
            'terminos_recibo' => 'Este comprobante no constituye factura fiscal. '
                .'Conserve el documento para cualquier reclamo dentro de los 30 días posteriores a la atención.',
        ]);
    }
}
