<?php

use App\Support\SupplierInvoiceNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [ACHATS A1] Clé normalisée du numéro de facture fournisseur + index d'unicité
 * (company_id, supplier_id, supplier_invoice_number_normalized).
 *
 * L'index couvre TOUTES les lignes physiques (y compris annulées et soft-deleted)
 * → politique « une référence saisie reste RÉSERVÉE dans l'historique » : on ne
 * réutilise pas un numéro via annulation ou suppression logique. Les numéros nuls
 * sont autorisés en multiples (MySQL/SQLite : NULL non contraint par UNIQUE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            // 100 caractères : borne l'index (utf8mb4 → 400 octets, très en deçà de
            // la limite MySQL 8.4) et couvre tout numéro fournisseur réaliste.
            $table->string('supplier_invoice_number_normalized', 100)->nullable()->after('supplier_invoice_number');
        });

        // Backfill des lignes existantes (toutes, y compris soft-deleted).
        DB::table('supplier_invoices')
            ->whereNotNull('supplier_invoice_number')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('supplier_invoices')->where('id', $row->id)->update([
                        'supplier_invoice_number_normalized' => SupplierInvoiceNumber::normalize($row->supplier_invoice_number),
                    ]);
                }
            });

        // Détection des collisions AVANT l'index unique. En cas de doublons
        // normalisés préexistants, la migration S'ARRÊTE avec un rapport
        // exploitable — elle ne choisit JAMAIS silencieusement une facture à
        // conserver et ne modifie AUCUN numéro historique. L'opérateur doit
        // arbitrer les doublons (avoir / extourne / correction manuelle tracée)
        // avant de relancer.
        $dupes = DB::table('supplier_invoices')
            ->select('company_id', 'supplier_id', 'supplier_invoice_number_normalized', DB::raw('COUNT(*) as n'))
            ->whereNotNull('supplier_invoice_number_normalized')
            ->groupBy('company_id', 'supplier_id', 'supplier_invoice_number_normalized')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($dupes->isNotEmpty()) {
            $report = $dupes->map(fn ($d) => sprintf(
                '- société %d / fournisseur %d / numéro « %s » : %d occurrences',
                $d->company_id, $d->supplier_id, $d->supplier_invoice_number_normalized, $d->n
            ))->implode("\n");
            throw new \RuntimeException(
                "[ACHATS A1] Migration ARRÊTÉE : doublons de numéro fournisseur normalisé préexistants — "
                . "l'index unique ne peut pas être posé sans arbitrage. Aucune donnée n'a été modifiée. "
                . "Résolvez ces collisions (avoir / extourne / correction tracée), puis relancez :\n" . $report
            );
        }

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'supplier_id', 'supplier_invoice_number_normalized'],
                'uq_supplier_invoice_number_norm'
            );
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            foreach (['uq_supplier_invoice_number_norm', 'ix_supplier_invoice_number_norm'] as $idx) {
                try {
                    $table->dropIndex($idx);
                } catch (\Throwable $e) {
                    // index absent selon la branche up() — ignorer
                }
            }
            $table->dropColumn('supplier_invoice_number_normalized');
        });
    }
};
