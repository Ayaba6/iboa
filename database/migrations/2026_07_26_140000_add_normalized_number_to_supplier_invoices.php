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
            $table->string('supplier_invoice_number_normalized', 60)->nullable()->after('supplier_invoice_number');
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

        // Index d'unicité DÉFENSIF : si des doublons normalisés préexistent (base
        // dev polluée), on ne pose qu'un index simple + on journalise — la garde
        // applicative protège de toute façon. Sinon, index UNIQUE (barrière DB
        // concurrente forte).
        $dupes = DB::table('supplier_invoices')
            ->select('company_id', 'supplier_id', 'supplier_invoice_number_normalized', DB::raw('COUNT(*) as n'))
            ->whereNotNull('supplier_invoice_number_normalized')
            ->groupBy('company_id', 'supplier_id', 'supplier_invoice_number_normalized')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        Schema::table('supplier_invoices', function (Blueprint $table) use ($dupes) {
            $cols = ['company_id', 'supplier_id', 'supplier_invoice_number_normalized'];
            if ($dupes->isEmpty()) {
                $table->unique($cols, 'uq_supplier_invoice_number_norm');
            } else {
                $table->index($cols, 'ix_supplier_invoice_number_norm');
                logger()->warning('[ACHATS A1] Index UNIQUE non posé : doublons normalisés préexistants sur supplier_invoices.', [
                    'groupes_en_doublon' => $dupes->count(),
                ]);
            }
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
