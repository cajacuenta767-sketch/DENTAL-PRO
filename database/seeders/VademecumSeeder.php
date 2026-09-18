<?php

namespace Database\Seeders;

use App\Models\MedicamentoVademecum;
use Illuminate\Database\Seeder;

class VademecumSeeder extends Seeder
{
    public function run(): void
    {
        $farmacos = [
            [
                'principio_activo' => 'Amoxicilina',
                'nombre_comercial' => 'Amoxil / Clamoxyl',
                'presentacion' => 'Cápsulas / Comprimidos',
                'concentracion' => '500 mg',
                'familia' => 'PENICILINA',
                'posologia_adulto' => '1 cápsula cada 8 horas por 7 días.',
                'posologia_pediatrica' => '40-50 mg/kg/día dividido en 3 tomas por 7 días.',
                'contraindicaciones' => 'Alergia a penicilinas o betalactámicos, mononucleosis infecciosa.',
                'advertencias' => 'Verificar antecedentes de hipersensibilidad antes de prescribir.',
            ],
            [
                'principio_activo' => 'Amoxicilina + Ácido Clavulánico',
                'nombre_comercial' => 'Augmentin / Curam',
                'presentacion' => 'Comprimidos recubiertos',
                'concentracion' => '875/125 mg',
                'familia' => 'PENICILINA',
                'posologia_adulto' => '1 comprimido cada 12 horas por 7 días, ingerir al inicio de las comidas.',
                'posologia_pediatrica' => '40-45 mg/kg/día dividido cada 12 horas.',
                'contraindicaciones' => 'Alergia a penicilinas, antecedentes de ictericia/disfunción hepática por amoxicilina-clavulánico.',
                'advertencias' => 'Uso reservado para infecciones odontogénicas moderadas a severas.',
            ],
            [
                'principio_activo' => 'Azitromicina',
                'nombre_comercial' => 'Zithromax / Trex',
                'presentacion' => 'Comprimidos',
                'concentracion' => '500 mg',
                'familia' => 'MACROLIDO',
                'posologia_adulto' => '500 mg cada 24 horas por 3 días (o profilaxis 500 mg 1 hora antes).',
                'posologia_pediatrica' => '10 mg/kg/día una vez al día por 3 días.',
                'contraindicaciones' => 'Hipersensibilidad a macrólidos, insuficiencia hepática grave.',
                'advertencias' => 'Alternativa de primera línea en pacientes con ALERGIA A PENICILINA.',
            ],
            [
                'principio_activo' => 'Clindamicina',
                'nombre_comercial' => 'Dalacin C',
                'presentacion' => 'Cápsulas',
                'concentracion' => '300 mg',
                'familia' => 'LINCOSAMIDA',
                'posologia_adulto' => '300 mg cada 6 u 8 horas por 7 días con abundante agua.',
                'posologia_pediatrica' => '10-25 mg/kg/día divididos en 3 a 4 tomas.',
                'contraindicaciones' => 'Hipersensibilidad a clindamicina o lincomicina, colitis pseudomembranosa.',
                'advertencias' => 'Excelente penetración ósea. Alternativa estándar en alérgicos a penicilina.',
            ],
            [
                'principio_activo' => 'Ibuprofeno',
                'nombre_comercial' => 'Advil / Motrin',
                'presentacion' => 'Comprimidos recubiertos',
                'concentracion' => '600 mg',
                'familia' => 'AINE',
                'posologia_adulto' => '600 mg cada 8 horas con alimentos por 3 a 5 días según dolor.',
                'posologia_pediatrica' => '5-10 mg/kg cada 6-8 horas (máximo 40 mg/kg/día).',
                'contraindicaciones' => 'Úlcera gastroduodenal activa, insuficiencia renal severa, tercer trimestre de embarazo.',
                'advertencias' => 'No superar 2400 mg diarios.',
            ],
            [
                'principio_activo' => 'Ketorolaco',
                'nombre_comercial' => 'Dolgenal / Toradol',
                'presentacion' => 'Comprimidos sublinguales',
                'concentracion' => '10 mg',
                'familia' => 'AINE',
                'posologia_adulto' => '1 comprimido sublingual cada 8 horas (máximo 40 mg/día, no más de 5 días).',
                'posologia_pediatrica' => 'No recomendado en menores de 16 años.',
                'contraindicaciones' => 'Hemorragia digestiva, anticoagulación activa, insuficiencia renal.',
                'advertencias' => 'Potente efecto analgésico antiinflamatorio post-quirúrgico agudo.',
            ],
            [
                'principio_activo' => 'Paracetamol',
                'nombre_comercial' => 'Tylenol / Panadol',
                'presentacion' => 'Comprimidos',
                'concentracion' => '1000 mg',
                'familia' => 'ANALGESICO',
                'posologia_adulto' => '500-1000 mg cada 6-8 horas según dolor (máximo 4000 mg/día).',
                'posologia_pediatrica' => '10-15 mg/kg cada 4-6 horas.',
                'contraindicaciones' => 'Insuficiencia hepatocelular grave, hipersensibilidad.',
                'advertencias' => 'Seguro en embarazo, lactancia y pacientes con gastritis o anticoagulados.',
            ],
            [
                'principio_activo' => 'Dexametasona',
                'nombre_comercial' => 'Decadron',
                'presentacion' => 'Comprimidos / Ampollas',
                'concentracion' => '4 mg',
                'familia' => 'CORTICOIDE',
                'posologia_adulto' => '4-8 mg pre-quirúrgico 1 hora antes o dosis única matutina.',
                'posologia_pediatrica' => 'Según indicación pediátrica estricta.',
                'contraindicaciones' => 'Infección micótica sistémica, úlcera péptica activa, diabetes descompensada.',
                'advertencias' => 'Reduce drásticamente el edema y trismus post-extracción de terceros molares.',
            ],
            [
                'principio_activo' => 'Clorhexidina',
                'nombre_comercial' => 'Periogard / Cariax',
                'presentacion' => 'Enjuague bucal (colutorio)',
                'concentracion' => '0.12 %',
                'familia' => 'ANTISEPTICO',
                'posologia_adulto' => 'Enjuague con 15 ml puro durante 60 segundos cada 12 horas por 10 a 14 días.',
                'posologia_pediatrica' => 'Uso supervisado en mayores de 6 años.',
                'contraindicaciones' => 'Hipersensibilidad a clorhexidina.',
                'advertencias' => 'El uso prolongado (>14 días) puede causar tinción dental reversible y alteración del gusto.',
            ],
        ];

        foreach ($farmacos as $farmaco) {
            MedicamentoVademecum::updateOrCreate(
                ['principio_activo' => $farmaco['principio_activo'], 'concentracion' => $farmaco['concentracion']],
                $farmaco
            );
        }
    }
}
