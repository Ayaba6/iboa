<?php

namespace App\Traits;

use App\Models\CommercialValidation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Trait HasCommercialWorkflow
 *
 * Factorisation du workflow de validation interne pour tous les documents
 * commerciaux : Quote, Order, DeliveryNote, Invoice, CreditNote.
 *
 * Chaque modèle qui utilise ce trait doit définir :
 *   - const DOCUMENT_TYPE = 'quote';   // clé métier
 *   - const STATUT_*       (constantes de statut propres au modèle)
 *
 * Colonnes requises dans la table :
 *   status, submitted_by, submitted_at, validated_by, validated_at,
 *   rejected_by, rejected_at, rejection_reason
 */
trait HasCommercialWorkflow
{
    // ── Relation audit trail ──────────────────────────────────────────────────

    public function workflowHistory(): HasMany
    {
        return $this->hasMany(CommercialValidation::class, 'document_id')
                    ->where('document_type', static::DOCUMENT_TYPE)
                    ->latest('created_at');
    }

    public function submittedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'submitted_by');
    }

    // ── Helpers état ──────────────────────────────────────────────────────────

    public function isBrouillon(): bool
    {
        return $this->status === 'brouillon';
    }

    public function isEnAttenteValidation(): bool
    {
        return $this->status === 'en_attente_validation';
    }

    public function isValidated(): bool
    {
        return in_array($this->status, $this->getValidatedStatuses(), true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['annule', 'annulee'], true);
    }

    public function isEditable(): bool
    {
        return $this->isBrouillon();
    }

    public function isSubmittable(): bool
    {
        return $this->isBrouillon();
    }

    public function isValidatable(): bool
    {
        return $this->isEnAttenteValidation();
    }

    public function isRejectable(): bool
    {
        return $this->isEnAttenteValidation();
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['brouillon', 'en_attente_validation'], true);
    }

    /**
     * Retourne les statuts considérés comme "validé" pour ce type de document.
     * Peut être surchargée par le modèle si nécessaire.
     */
    protected function getValidatedStatuses(): array
    {
        return ['valide', 'validee', 'confirme', 'emise', 'envoyee'];
    }

    // ── Badges UI ─────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return static::getStatusLabels()[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return static::getStatusColors()[$this->status] ?? 'gray';
    }

    /**
     * Labels par défaut — chaque modèle peut surcharger cette méthode.
     */
    public static function getStatusLabels(): array
    {
        return [
            'brouillon'            => 'Brouillon',
            'en_attente_validation'=> 'En attente de validation',
            'valide'               => 'Validé',
            'validee'              => 'Validée',
            'confirme'             => 'Confirmé',
            'emise'                => 'Émise',
            'envoyee'              => 'Envoyée',
            'annule'               => 'Annulé',
            'annulee'              => 'Annulée',
            'refuse'               => 'Refusé',
            'refusee'              => 'Refusée',
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            'brouillon'            => 'gray',
            'en_attente_validation'=> 'yellow',
            'valide'               => 'green',
            'validee'              => 'green',
            'confirme'             => 'green',
            'emise'                => 'blue',
            'envoyee'              => 'blue',
            'annule'               => 'red',
            'annulee'              => 'red',
            'refuse'               => 'red',
            'refusee'              => 'red',
        ];
    }

    // ── Transitions de statut ─────────────────────────────────────────────────

    /**
     * Soumet le document à validation (brouillon → en_attente_validation).
     *
     * @throws \RuntimeException
     */
    public function submit(?string $motif = null): void
    {
        if (! $this->isSubmittable()) {
            throw new \RuntimeException(
                "Ce document ne peut pas être soumis à validation (statut actuel : {$this->status})."
            );
        }

        $ancienStatut = $this->status;

        $this->update([
            'status'       => 'en_attente_validation',
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
        ]);

        $this->logWorkflowAction(
            action:       CommercialValidation::ACTION_SOUMISSION,
            ancienStatut: $ancienStatut,
            nouveauStatut: 'en_attente_validation',
            motif:        $motif,
        );
    }

    /**
     * Valide le document (en_attente_validation → statut validé du modèle).
     *
     * @throws \RuntimeException
     */
    public function validateDocument(string $validatedStatus, ?string $motif = null): void
    {
        if (! $this->isValidatable()) {
            throw new \RuntimeException(
                "Ce document ne peut pas être validé (statut actuel : {$this->status})."
            );
        }

        $ancienStatut = $this->status;

        $this->update([
            'status'       => $validatedStatus,
            'validated_by' => Auth::id(),
            'validated_at' => now(),
        ]);

        $this->logWorkflowAction(
            action:       CommercialValidation::ACTION_VALIDATION,
            ancienStatut: $ancienStatut,
            nouveauStatut: $validatedStatus,
            motif:        $motif,
        );
    }

    /**
     * Refuse le document (en_attente_validation → brouillon).
     *
     * @throws \RuntimeException
     */
    public function rejectDocument(string $motif): void
    {
        if (! $this->isRejectable()) {
            throw new \RuntimeException(
                "Ce document ne peut pas être refusé (statut actuel : {$this->status})."
            );
        }

        if (empty(trim($motif))) {
            throw new \RuntimeException("Le motif de refus est obligatoire.");
        }

        $ancienStatut = $this->status;

        $this->update([
            'status'           => 'brouillon',
            'rejected_by'      => Auth::id(),
            'rejected_at'      => now(),
            'rejection_reason' => $motif,
        ]);

        $this->logWorkflowAction(
            action:       CommercialValidation::ACTION_REFUS,
            ancienStatut: $ancienStatut,
            nouveauStatut: 'brouillon',
            motif:        $motif,
        );
    }

    /**
     * Annule le document avec motif obligatoire.
     *
     * @throws \RuntimeException
     */
    public function cancelDocument(string $cancelledStatus, string $motif): void
    {
        if (! $this->isCancellable()) {
            throw new \RuntimeException(
                "Ce document ne peut pas être annulé (statut actuel : {$this->status})."
            );
        }

        if (empty(trim($motif))) {
            throw new \RuntimeException("Le motif d'annulation est obligatoire.");
        }

        $ancienStatut = $this->status;

        $this->update(['status' => $cancelledStatus]);

        $this->logWorkflowAction(
            action:       CommercialValidation::ACTION_ANNULATION,
            ancienStatut: $ancienStatut,
            nouveauStatut: $cancelledStatus,
            motif:        $motif,
        );
    }

    // ── Journalisation ────────────────────────────────────────────────────────

    public function logWorkflowAction(
        string  $action,
        ?string $ancienStatut,
        string  $nouveauStatut,
        ?string $motif = null,
        array   $metadata = [],
    ): CommercialValidation {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return CommercialValidation::create([
            'document_type' => static::DOCUMENT_TYPE,
            'document_id'   => $this->id,
            'ancien_statut' => $ancienStatut,
            'nouveau_statut'=> $nouveauStatut,
            'action'        => $action,
            'user_id'       => $user->id,
            'user_role'     => $user->getRoleNames()->first(),
            'motif'         => $motif,
            'ip_address'    => Request::ip(),
            'user_agent'    => Request::userAgent(),
            'metadata'      => ! empty($metadata) ? $metadata : null,
        ]);
    }

    // ── Vérification double validation ────────────────────────────────────────

    /**
     * Vérifie si l'utilisateur courant peut valider ce document.
     * En mode double validation, le soumetteur ne peut pas valider son propre document.
     *
     * @throws \RuntimeException
     */
    public function assertCanValidate(): void
    {
        $company = currentCompany();

        // Les super_admins et utilisateurs avec 'sales.bypass_self_validation'
        // peuvent toujours valider, quel que soit le mode.
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && (
            $user->hasRole('super_admin')
            || $user->hasPermissionTo('sales.bypass_self_validation')
        )) {
            return;
        }

        if (
            $company
            && $company->validation_mode === 'double'
            && ! ($company->allow_self_validation ?? false)
            && $this->submitted_by
            && $this->submitted_by === \Illuminate\Support\Facades\Auth::id()
        ) {
            throw new \RuntimeException(
                "Validation impossible : en mode double validation, le soumetteur ne peut pas valider son propre document."
            );
        }
    }
}
