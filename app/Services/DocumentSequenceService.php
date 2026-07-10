<?php
namespace App\Services;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\DocumentSequenceAudit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use RuntimeException;

/**
 * Service de numérotation des documents — niveau Sage GesCom.
 *
 * Garanties :
 *  - Atomicité  : DB::transaction + SELECT ... FOR UPDATE → pas de doublon même en concurrence.
 *  - Audit trail: toute modification crée une ligne dans document_sequence_audits.
 *  - Anti-régression : refus de descendre last_number sous la dernière valeur effectivement consommée.
 *  - Multi-exercices : chaque (company, fiscal_year, type) a sa propre séquence.
 *  - Préservation des documents : les références déjà émises ne sont jamais touchées.
 *  - Concurrence : verrou pessimiste (lockForUpdate) pendant la génération.
 *
 * Mode :
 *  - 'auto'   : nextNumber() incrémente + retourne le numéro formaté.
 *  - 'manual' : nextNumber() retourne juste un suggéré ; le caller doit vérifier l'unicité du n° saisi.
 */
class DocumentSequenceService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Mapping document_type → table SQL contenant la colonne "number"
    // Permet de connaître le n° max EFFECTIVEMENT consommé pour éviter les régressions.
    // ─────────────────────────────────────────────────────────────────────────
    private const TYPE_TO_TABLE = [
        'devis'               => 'quotes',
        'commande'            => 'orders',
        'bon_livraison'       => 'delivery_notes',
        'facture'             => 'invoices',
        'avoir'               => 'credit_notes',
        'demande_achat'       => 'purchase_requests',
        'commande_achat'      => 'purchase_orders',
        'reception'           => 'receptions',
        'facture_fournisseur' => 'supplier_invoices',
        'retour_fournisseur'  => 'supplier_returns',
        'encaissement'        => 'client_payments',
        'decaissement'        => 'supplier_payments',
        'remise_banque'       => 'bank_deposits',
        'effet_commerce'      => 'commercial_effects',
        'inventaire'          => 'inventory_sessions',
        'ecriture_comptable'  => 'journal_entries',
        'rapprochement'       => 'bank_reconciliations',
        'declaration_tva'     => 'vat_declarations',
        'virement_interne'    => 'cash_transfers',
        'cloture_caisse'      => 'cash_closures',
        'operation_caisse'    => 'cash_operations',
        'contentieux'         => 'litigation_cases',
        'demande_paiement'    => 'payment_requests',
        'ordre_fabrication'   => 'production_orders',
        'bon_preparation'     => 'bon_preparations',
    ];

    // ═════════════════════════════════════════════════════════════════════════
    // GÉNÉRATION DE NUMÉRO
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Réserve atomiquement le prochain numéro et le retourne formaté.
     * Si la séquence est en mode 'manual', renvoie le SUGGÉRÉ sans incrémenter.
     */
    public function nextNumber(Company $company, string $documentType): string
    {
        return DB::transaction(function () use ($company, $documentType) {
            $seq = DocumentSequence::where('company_id',     $company->id)
                ->where('fiscal_year_id', $company->current_fiscal_year_id)
                ->where('document_type',  $documentType)
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'company_id'     => $company->id,
                        'fiscal_year_id' => $company->current_fiscal_year_id,
                        'document_type'  => $documentType,
                    ],
                    $this->defaultConfig($documentType)
                );

            // [FIX-DÉFINITIF-NUM] Resynchroniser le compteur sur le plus grand numéro
            // RÉELLEMENT émis dans la table cible. Indispensable : des documents peuvent
            // avoir été créés HORS de ce service (seeder de démo, import, SQL manuel),
            // laissant document_sequences.last_number en retard. Sans cette resync, le
            // service régénère un numéro déjà pris → SQLSTATE[23000] Duplicate entry.
            // S'exécute dans le verrou pessimiste, donc cohérent et concurrence-safe.
            $maxUsed = $this->maxUsedNumber($seq);
            if ($maxUsed > $seq->last_number) {
                $seq->last_number = $maxUsed;
                $seq->save();
            }

            // Mode manuel : on suggère mais on n'incrémente PAS.
            // Le caller doit appeler confirmManual($seq, $number) APRÈS création du document.
            if ($seq->numbering_mode === 'manual') {
                return $this->format($seq, $seq->last_number + 1);
            }

            $seq->increment('last_number');
            $seq->refresh();

            return $this->format($seq);
        });
    }

    /**
     * Mode manuel : l'utilisateur a saisi un numéro précis ; on l'enregistre
     * comme dernier compteur si supérieur, et on retourne le formaté.
     * À appeler APRÈS validation d'unicité côté contrôleur.
     */
    public function confirmManual(Company $company, string $documentType, int $assignedNumber): void
    {
        DB::transaction(function () use ($company, $documentType, $assignedNumber) {
            $seq = DocumentSequence::where('company_id', $company->id)
                ->where('fiscal_year_id', $company->current_fiscal_year_id)
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if (!$seq) return;

            if ($assignedNumber > $seq->last_number) {
                $seq->update(['last_number' => $assignedNumber]);
            }
        });
    }

    /**
     * Aperçu du prochain numéro sans incrémenter le compteur.
     */
    public function previewNext(Company $company, string $documentType): string
    {
        $seq = DocumentSequence::where('company_id', $company->id)
            ->where('fiscal_year_id', $company->current_fiscal_year_id)
            ->where('document_type', $documentType)
            ->first();

        if (!$seq) {
            $seq = new DocumentSequence($this->defaultConfig($documentType));
            $seq->last_number = 0;
        }

        return $this->format($seq, $seq->last_number + 1);
    }

    /**
     * Formate un numéro depuis une séquence.
     */
    public function format(DocumentSequence $seq, ?int $number = null): string
    {
        $num    = $number ?? $seq->last_number;
        $padded = str_pad((string) $num, (int) ($seq->padding ?? 3), '0', STR_PAD_LEFT);

        $yearPart = '';
        if ($seq->include_year ?? true) {
            $yearFmt  = ($seq->year_format ?? '4') === '2' ? 'y' : 'Y';
            $yearPart = now()->format($yearFmt) . ($seq->year_separator ?? '-');
        }

        // [Maquette Numérotation] segment mois optionnel (ex: FAC-2026/07/0001)
        $monthPart = '';
        if ($seq->include_month ?? false) {
            $monthPart = now()->format('m') . ($seq->year_separator ?? '-');
        }

        return ($seq->prefix ?? '') . $yearPart . $monthPart . $padded . ($seq->suffix ?? '');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // OPÉRATIONS ADMINISTRATIVES — toutes auditées
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Modifier le format d'une séquence (préfixe, padding, année…).
     * - Vérifie le verrou
     * - Audite avant/après
     * - N'affecte PAS les documents déjà émis.
     */
    public function updateFormat(DocumentSequence $seq, array $changes, ?string $reason = null): DocumentSequence
    {
        if ($seq->is_locked) {
            throw new RuntimeException("Cette séquence est verrouillée. Déverrouillez-la avant de modifier son format.");
        }

        $before = $seq->only(DocumentSequence::FORMAT_FIELDS);

        // Ne garder que les champs autorisés
        $allowed = array_intersect_key($changes, array_flip(DocumentSequence::FORMAT_FIELDS));

        return DB::transaction(function () use ($seq, $allowed, $before, $reason) {
            $seq->update(array_merge($allowed, [
                'last_modified_by'     => Auth::id(),
                'last_modified_reason' => $reason,
            ]));

            $this->audit($seq, 'update_format', $before, $seq->only(DocumentSequence::FORMAT_FIELDS), $reason);

            return $seq;
        });
    }

    /**
     * Définir manuellement le compteur — avec garde-fou anti-régression.
     *
     * @param  bool  $force  Si true, autorise la régression (DANGEREUX).
     */
    public function setCounter(DocumentSequence $seq, int $newCounter, ?string $reason = null, bool $force = false): DocumentSequence
    {
        if ($newCounter < 0) {
            throw new RuntimeException("Le compteur ne peut pas être négatif.");
        }

        $maxUsed = $this->maxUsedNumber($seq);

        if ($newCounter < $maxUsed && !$force) {
            throw new RuntimeException(
                "Impossible de descendre le compteur sous {$maxUsed} : ce numéro a déjà été émis. "
                . "Cela génèrerait des doublons. Forcez avec un motif explicite si vous comprenez les conséquences."
            );
        }

        return DB::transaction(function () use ($seq, $newCounter, $reason) {
            $before = ['last_number' => $seq->last_number];

            $seq->update([
                'last_number'          => $newCounter,
                'last_modified_by'     => Auth::id(),
                'last_modified_reason' => $reason,
            ]);

            $this->audit($seq, 'set_counter', $before, ['last_number' => $newCounter], $reason);

            return $seq;
        });
    }

    /**
     * Remettre le compteur à zéro — refusé si des documents existent
     * (sauf en début d'exercice où aucun document n'a encore été émis).
     */
    public function resetCounter(DocumentSequence $seq, ?string $reason = null, bool $force = false): DocumentSequence
    {
        $maxUsed = $this->maxUsedNumber($seq);

        if ($maxUsed > 0 && !$force) {
            throw new RuntimeException(
                "Remise à zéro refusée : {$maxUsed} document(s) ont déjà utilisé cette séquence. "
                . "Reset autorisé uniquement en début d'exercice. Forcez si vous comprenez les conséquences."
            );
        }

        return DB::transaction(function () use ($seq, $reason) {
            $before = ['last_number' => $seq->last_number];

            $seq->update([
                'last_number'          => 0,
                'last_modified_by'     => Auth::id(),
                'last_modified_reason' => $reason,
            ]);

            $this->audit($seq, 'reset_counter', $before, ['last_number' => 0], $reason);

            return $seq;
        });
    }

    /**
     * Basculer le mode (auto ↔ manuel).
     */
    public function setMode(DocumentSequence $seq, string $mode, ?string $reason = null): DocumentSequence
    {
        if (!in_array($mode, ['auto', 'manual'], true)) {
            throw new RuntimeException("Mode invalide : '$mode'. Valeurs autorisées : auto, manual.");
        }

        return DB::transaction(function () use ($seq, $mode, $reason) {
            $before = ['numbering_mode' => $seq->numbering_mode];

            $seq->update([
                'numbering_mode'       => $mode,
                'last_modified_by'     => Auth::id(),
                'last_modified_reason' => $reason,
            ]);

            $this->audit($seq, 'update_format', $before, ['numbering_mode' => $mode], $reason);

            return $seq;
        });
    }

    /**
     * Verrouillage / déverrouillage du format.
     */
    public function toggleLock(DocumentSequence $seq, ?string $reason = null): DocumentSequence
    {
        return DB::transaction(function () use ($seq, $reason) {
            $wasLocked = $seq->is_locked;

            $seq->update([
                'is_locked'            => !$wasLocked,
                'last_modified_by'     => Auth::id(),
                'last_modified_reason' => $reason,
            ]);

            $this->audit(
                $seq,
                $wasLocked ? 'unlock' : 'lock',
                ['is_locked' => $wasLocked],
                ['is_locked' => !$wasLocked],
                $reason
            );

            return $seq;
        });
    }

    // ═════════════════════════════════════════════════════════════════════════
    // INSPECTION
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Récupère le n° max EFFECTIVEMENT consommé en base pour cette séquence,
     * en interrogeant la table cible filtrée par company + fiscal_year.
     *
     * Si le type n'est pas cartographié → 0 (pas de garde-fou possible).
     */
    public function maxUsedNumber(DocumentSequence $seq): int
    {
        $table = self::TYPE_TO_TABLE[$seq->document_type] ?? null;
        if (!$table) {
            return 0;
        }

        // La quasi-totalité des tables ont les colonnes company_id, fiscal_year_id, number.
        // On défensive sur l'existence des colonnes.
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        if (!in_array('number', $columns, true)) {
            return 0;
        }

        $q = DB::table($table)->where('company_id', $seq->company_id);

        if (in_array('fiscal_year_id', $columns, true)) {
            $q->where('fiscal_year_id', $seq->fiscal_year_id);
        }

        // On extrait la partie numérique finale du `number` pour comparer.
        // Format attendu : PRÉFIXE[YYYY-]NNN[-SUFFIXE]. On enlève préfixe/suffixe/année.
        $prefix    = $seq->prefix ?? '';
        $suffix    = $seq->suffix ?? '';
        $yearPart  = '';
        if ($seq->include_year) {
            $yearPart = ($seq->year_format === '2' ? now()->format('y') : now()->format('Y'))
                      . ($seq->year_separator ?? '-');
        }

        $numbers = $q->pluck('number')->filter()->map(function ($n) use ($prefix, $yearPart, $suffix) {
            $core = $n;
            if ($prefix !== '' && str_starts_with($core, $prefix))   $core = substr($core, strlen($prefix));
            if ($yearPart !== '' && str_starts_with($core, $yearPart)) $core = substr($core, strlen($yearPart));
            if ($suffix !== '' && str_ends_with($core, $suffix))     $core = substr($core, 0, -strlen($suffix));
            return is_numeric($core) ? (int) $core : 0;
        });

        return (int) ($numbers->max() ?? 0);
    }

    /**
     * Indique s'il existe déjà des documents émis avec cette séquence.
     */
    public function hasIssuedDocuments(DocumentSequence $seq): bool
    {
        return $this->maxUsedNumber($seq) > 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // AUDIT
    // ═════════════════════════════════════════════════════════════════════════

    private function audit(DocumentSequence $seq, string $action, ?array $before, ?array $after, ?string $reason): void
    {
        DocumentSequenceAudit::create([
            'document_sequence_id' => $seq->id,
            'user_id'              => Auth::id(),
            'action'               => $action,
            'before'               => $before,
            'after'                => $after,
            'reason'               => $reason,
            'ip_address'           => Request::ip(),
            'user_agent'           => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CONFIG PAR DÉFAUT
    // ═════════════════════════════════════════════════════════════════════════

    public function defaultConfig(string $type): array
    {
        $base = [
            'include_year'   => true,
            'year_format'    => '4',
            'year_separator' => '-',
            'suffix'         => null,
            'numbering_mode' => 'auto',
            'is_locked'      => false,
        ];

        $configs = [
            'devis'               => ['prefix' => 'DEV-', 'padding' => 3],
            'commande'            => ['prefix' => 'CMD-', 'padding' => 3],
            'bon_livraison'       => ['prefix' => 'BL-',  'padding' => 3],
            'facture'             => ['prefix' => 'FA-',  'padding' => 3],
            'avoir'               => ['prefix' => 'AV-',  'padding' => 3],
            'commande_achat'      => ['prefix' => 'CA-',  'padding' => 3],
            'reception'           => ['prefix' => 'REC-', 'padding' => 3],
            'facture_fournisseur' => ['prefix' => 'FF-',  'padding' => 3],
            'retour_fournisseur'  => ['prefix' => 'RF-',  'padding' => 3],
            'demande_achat'       => ['prefix' => 'DA-',  'padding' => 3],
            'encaissement'        => ['prefix' => 'ENC-', 'padding' => 3],
            'decaissement'        => ['prefix' => 'DEC-', 'padding' => 3],
            'remise_banque'       => ['prefix' => 'RB-',  'padding' => 3],
            'effet_commerce'      => ['prefix' => 'EFF-', 'padding' => 3],
            'inventaire'          => ['prefix' => 'INV-', 'padding' => 3],
            'ecriture_comptable'  => ['prefix' => 'EC-',  'padding' => 4],
            'rapprochement'       => ['prefix' => 'RBQ-', 'padding' => 3],
            'declaration_tva'     => ['prefix' => 'TVA-', 'padding' => 3],
            'prevision_tresorerie'=> ['prefix' => 'PT-',  'padding' => 3],
            'immobilisation'      => ['prefix' => 'IMB-', 'padding' => 3],
            'virement_interne'    => ['prefix' => 'VIR-', 'padding' => 3],
            'cloture_caisse'      => ['prefix' => 'CLC-', 'padding' => 3],
            'operation_caisse'    => ['prefix' => 'OPC-', 'padding' => 4],
            'contentieux'         => ['prefix' => 'CTX-', 'padding' => 4],
            'demande_paiement'    => ['prefix' => 'DP-',  'padding' => 3],
            'ordre_fabrication'   => ['prefix' => 'OF-',  'padding' => 4],
            'bon_preparation'     => ['prefix' => 'BP-',  'padding' => 4],
            'mouvement_stock'     => ['prefix' => 'MVT-', 'padding' => 4],
            'transfert_stock'     => ['prefix' => 'TRF-', 'padding' => 4],
            'contrat'             => ['prefix' => 'CT-',  'padding' => 4],
            'certificat_qualite'  => ['prefix' => 'CQ-',  'padding' => 4],
        ];

        $specific = $configs[$type] ?? ['prefix' => strtoupper(substr($type, 0, 3)) . '-', 'padding' => 3];

        return array_merge($base, $specific);
    }
}
