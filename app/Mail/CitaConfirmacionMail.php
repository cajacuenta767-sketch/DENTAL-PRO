<?php

namespace App\Mail;

use App\Models\Ajuste;
use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaConfirmacionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cita $cita,
        public bool $esRecordatorio = false,
    ) {}

    public function envelope(): Envelope
    {
        $clinica = Ajuste::actual()->nombre;

        return new Envelope(
            subject: $this->esRecordatorio
                ? "Recordatorio de tu cita en {$clinica}"
                : "Confirmación de tu cita en {$clinica}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.cita-confirmacion',
            with: [
                'cita' => $this->cita->loadMissing(['paciente', 'doctor.especialidad', 'tratamiento']),
                'clinica' => Ajuste::actual(),
                'esRecordatorio' => $this->esRecordatorio,
            ],
        );
    }
}
