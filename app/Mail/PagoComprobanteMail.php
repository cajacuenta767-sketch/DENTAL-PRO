<?php

namespace App\Mail;

use App\Models\Ajuste;
use App\Models\Pago;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PagoComprobanteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Pago $pago,
        public ?string $pdf = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Comprobante '.$this->pago->codigo_recibo.' · '.Ajuste::actual()->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.pago-comprobante',
            with: [
                'pago' => $this->pago->loadMissing(['paciente', 'doctor', 'detalles']),
                'clinica' => Ajuste::actual(),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if (! $this->pdf) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->pdf, $this->pago->codigo_recibo.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
