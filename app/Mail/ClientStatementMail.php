<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * [Maquette X3] Envoi du relevé client par email, PDF attaché.
 */
class ClientStatementMail extends Mailable
{
    public function __construct(
        public Client $client,
        public string $dateFrom,
        public string $dateTo,
        public int    $soldeFinal,
        public string $pdfBinary,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Relevé de compte — ' . $this->client->name
                . ' (' . \Carbon\Carbon::parse($this->dateFrom)->format('d/m/Y')
                . ' au ' . \Carbon\Carbon::parse($this->dateTo)->format('d/m/Y') . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Bonjour,</p>'
                . '<p>Veuillez trouver ci-joint votre relevé de compte pour la période du '
                . \Carbon\Carbon::parse($this->dateFrom)->format('d/m/Y') . ' au '
                . \Carbon\Carbon::parse($this->dateTo)->format('d/m/Y') . '.</p>'
                . '<p>Solde au ' . \Carbon\Carbon::parse($this->dateTo)->format('d/m/Y') . ' : <strong>'
                . number_format($this->soldeFinal, 0, ',', ' ') . ' FCFA</strong>.</p>'
                . '<p>Cordialement,<br>' . e(currentCompany()?->name ?? 'A3 ERP') . '</p>',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBinary, 'releve-' . $this->client->code . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
