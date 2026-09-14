<?php

/*
|--------------------------------------------------------------------------
| Evolución clínica
|--------------------------------------------------------------------------
|
| Plantillas de nota de consulta y catálogo de anestésicos que usa el
| formulario de historia clínica. Cada plantilla rellena los campos vacíos
| del formulario; el profesional siempre puede corregir el texto.
|
*/

return [

    'plantillas' => [
        'control' => [
            'etiqueta' => 'Control / revisión periódica',
            'motivo_consulta' => 'Control odontológico periódico.',
            'sintomas' => 'Paciente asintomático.',
            'diagnostico' => 'Sin hallazgos patológicos nuevos. Dentición y tejidos blandos en condiciones estables.',
            'tratamiento_realizado' => 'Examen clínico intraoral y extraoral. Revisión de odontograma y refuerzo de técnica de higiene oral.',
            'prescripcion_receta' => '',
            'observaciones' => 'Se recomienda control en 6 meses.',
        ],
        'profilaxis' => [
            'etiqueta' => 'Profilaxis dental',
            'motivo_consulta' => 'Limpieza dental de rutina.',
            'sintomas' => 'Refiere sangrado ocasional al cepillado.',
            'diagnostico' => 'Placa bacteriana y cálculo supragingival generalizado. Gingivitis leve asociada a placa.',
            'tratamiento_realizado' => 'Profilaxis con destartraje supragingival por ultrasonido, pulido con pasta profiláctica y aplicación tópica de flúor.',
            'prescripcion_receta' => '',
            'observaciones' => 'Instrucción de higiene oral: técnica de Bass modificada, uso de hilo dental diario. Control en 6 meses.',
        ],
        'obturacion' => [
            'etiqueta' => 'Obturación (restauración)',
            'motivo_consulta' => 'Caries dental / restauración de pieza dentaria.',
            'sintomas' => 'Sensibilidad al frío y a los dulces en la pieza afectada.',
            'diagnostico' => 'Caries dentinaria en pieza ___ (cara ___).',
            'tratamiento_realizado' => 'Anestesia local, aislamiento, eliminación de tejido cariado, grabado ácido, adhesivo y obturación con resina compuesta. Control oclusal y pulido.',
            'prescripcion_receta' => '',
            'observaciones' => 'Evitar masticar del lado tratado hasta que pase el efecto anestésico. Puede presentar sensibilidad transitoria.',
        ],
        'endodoncia' => [
            'etiqueta' => 'Endodoncia',
            'motivo_consulta' => 'Dolor dental intenso / tratamiento de conducto.',
            'sintomas' => 'Dolor espontáneo, pulsátil, que aumenta con el calor y en decúbito.',
            'diagnostico' => 'Pulpitis irreversible sintomática en pieza ___.',
            'tratamiento_realizado' => 'Anestesia local, aislamiento absoluto, apertura cameral, conductometría, instrumentación biomecánica, irrigación con hipoclorito de sodio y obturación temporal / definitiva de conductos.',
            'prescripcion_receta' => 'Ibuprofeno 400 mg cada 8 horas por 3 días en caso de dolor.',
            'observaciones' => 'Evitar masticar con la pieza hasta la restauración definitiva. Control en 7 días.',
        ],
        'exodoncia' => [
            'etiqueta' => 'Exodoncia',
            'motivo_consulta' => 'Extracción de pieza dentaria.',
            'sintomas' => 'Dolor y movilidad en pieza afectada.',
            'diagnostico' => 'Resto radicular / pieza ___ con destrucción coronaria no restaurable.',
            'tratamiento_realizado' => 'Anestesia local, sindesmotomía, luxación y avulsión de la pieza. Curetaje alveolar, hemostasia con gasa estéril y sutura.',
            'prescripcion_receta' => 'Amoxicilina 500 mg cada 8 horas por 7 días. Ibuprofeno 400 mg cada 8 horas por 3 días.',
            'observaciones' => 'Morder la gasa 30 minutos, no escupir ni enjuagar por 24 horas, dieta blanda y fría, no fumar. Control y retiro de puntos en 7 días.',
        ],
        'urgencia_dolor' => [
            'etiqueta' => 'Urgencia por dolor',
            'motivo_consulta' => 'Consulta de urgencia por dolor dental agudo.',
            'sintomas' => 'Dolor intenso, continuo, de inicio hace ___ horas/días, que no cede con analgésicos.',
            'diagnostico' => 'Dolor odontogénico agudo en pieza ___. Se descarta / confirma absceso.',
            'tratamiento_realizado' => 'Evaluación clínica y radiográfica. Eliminación de la causa (apertura cameral / drenaje / medicación intraconducto) y alivio oclusal.',
            'prescripcion_receta' => 'Ibuprofeno 400 mg cada 8 horas por 3 días. Paracetamol 500 mg cada 8 horas si persiste el dolor.',
            'observaciones' => 'Programar tratamiento definitivo. Acudir de inmediato ante fiebre o aumento de la inflamación.',
        ],
        'ortodoncia_control' => [
            'etiqueta' => 'Control de ortodoncia',
            'motivo_consulta' => 'Control mensual de ortodoncia.',
            'sintomas' => 'Molestias leves posteriores a la activación anterior.',
            'diagnostico' => 'Tratamiento ortodóncico en curso. Evolución favorable.',
            'tratamiento_realizado' => 'Revisión de aparatología, cambio de ligaduras y activación de arcos. Verificación de higiene y brackets despegados.',
            'prescripcion_receta' => '',
            'observaciones' => 'Evitar alimentos duros y pegajosos. Próximo control en 4 semanas.',
        ],
    ],

    'anestesicos' => [
        'Lidocaína 2 % con epinefrina 1:100000',
        'Mepivacaína 3 %',
        'Articaína 4 % con epinefrina 1:100000',
        'Prilocaína 3 %',
        'Sin anestesia',
    ],

    /** Campos de la nota que rellena una plantilla. */
    'campos' => ['motivo_consulta', 'sintomas', 'diagnostico', 'tratamiento_realizado', 'prescripcion_receta', 'observaciones'],

];
