<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * [Phase 2.8] Exemplaire PDF archivé d'un document validé + empreinte SHA-256.
 * Fait foi : la régénération peut diverger (référentiels vivants), l'archive non.
 */
class DocumentArchive extends Model
{
    protected $fillable = [
        'company_id', 'document_type', 'document_id', 'number',
        'disk', 'path', 'sha256', 'byte_size', 'archived_at', 'archived_by',
    ];

    protected $casts = ['archived_at' => 'datetime'];

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    /** Contenu binaire de l'exemplaire archivé. */
    public function contents(): ?string
    {
        return Storage::disk($this->disk)->exists($this->path)
            ? Storage::disk($this->disk)->get($this->path)
            : null;
    }

    /** Vérifie que le fichier stocké n'a pas été altéré depuis l'archivage. */
    public function verifyIntegrity(): bool
    {
        $data = $this->contents();

        return $data !== null && hash('sha256', $data) === $this->sha256;
    }
}
