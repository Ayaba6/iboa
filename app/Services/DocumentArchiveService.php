<?php

namespace App\Services;

use App\Models\DocumentArchive;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * [Phase 2.8] Archivage des PDF de documents validés (disque privé) +
 * empreinte SHA-256. Idempotent : un document déjà archivé n'est jamais
 * réécrit — l'exemplaire d'origine fait foi.
 */
class DocumentArchiveService
{
    /** Disque privé (non exposé publiquement). */
    private const DISK = 'local';

    /**
     * Archive le PDF binaire d'un document, sauf s'il l'est déjà.
     *
     * @param  Model   $document  facture, avoir, BL…
     * @param  string  $pdfBytes  contenu binaire du PDF réellement émis
     * @param  string  $number    numéro de pièce (nommage lisible)
     */
    public function archive(Model $document, string $pdfBytes, string $number): DocumentArchive
    {
        $existing = DocumentArchive::where('document_type', get_class($document))
            ->where('document_id', $document->getKey())
            ->first();
        if ($existing) {
            return $existing; // l'original ne se réécrit jamais
        }

        $companyId = (int) ($document->company_id ?? currentCompany()?->id);
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '-', $number);
        $path = sprintf('archives/%d/%s/%s.pdf', $companyId, class_basename($document), $slug);

        Storage::disk(self::DISK)->put($path, $pdfBytes);

        return DocumentArchive::create([
            'company_id'    => $companyId,
            'document_type' => get_class($document),
            'document_id'   => $document->getKey(),
            'number'        => $number,
            'disk'          => self::DISK,
            'path'          => $path,
            'sha256'        => hash('sha256', $pdfBytes),
            'byte_size'     => strlen($pdfBytes),
            'archived_at'   => now(),
            'archived_by'   => Auth::id(),
        ]);
    }

    /** Archive existante d'un document, s'il y en a une. */
    public function for(Model $document): ?DocumentArchive
    {
        return DocumentArchive::where('document_type', get_class($document))
            ->where('document_id', $document->getKey())
            ->first();
    }
}
