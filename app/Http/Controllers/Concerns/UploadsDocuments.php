<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * [Dédoublonnage] Upload des pièces jointes « documents[] » sur un modèle HasAttachments.
 * Remplace les 16 copies privées identiques dans les contrôleurs.
 * Les fichiers déjà stockés gardent leur chemin (persisté par ligne en base) —
 * seuls les nouveaux uploads utilisent la convention snake_case.
 */
trait UploadsDocuments
{
    protected function uploadDocuments(Model $model, Request $request, ?string $folder = null, string $field = 'documents'): void
    {
        $folder ??= Str::snake(class_basename($model));

        foreach ((array) $request->file($field, []) as $file) {
            $path = $file->store('attachments/' . $folder . '/' . $model->getKey(), 'local');
            $model->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }
}
