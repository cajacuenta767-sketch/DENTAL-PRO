<?php

namespace App\Mail;

use App\Models\Ajuste;
use App\Models\Usuario;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Código de un solo uso para el segundo factor. Se envía de forma
 * síncrona: el usuario lo está esperando en la pantalla de acceso.
 */
class CodigoAccesoMail extends Mailable
{
    public function __construct(
        public Usuario $usuario,
        public string $codigo,
        public int $minutos = 10,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu código de acceso a '.Ajuste::actual()->nombre);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.codigo-acceso',
            with: [
                'usuario' => $this->usuario,
                'codigo' => $this->codigo,
                'minutos' => $this->minutos,
                'clinica' => Ajuste::actual(),
            ],
        );
    }
}
