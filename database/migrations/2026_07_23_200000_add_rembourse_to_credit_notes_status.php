<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * [Parcours D — remboursement réel] Ajoute 'rembourse' à l'ENUM
 * credit_notes.status (leçon stock_lots : ENUM élargi AVANT d'écrire la
 * valeur, MySQL strict tronque sinon). SQLite (tests) : la table est
 * recréée par migrate:fresh avec le nouvel enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE credit_notes MODIFY COLUMN status ENUM('brouillon', 'valide', 'applique', 'annule', 'rembourse') NOT NULL DEFAULT 'brouillon'");
        } else {
            // SQLite : l'enum est un CHECK — le recréer proprement est coûteux ;
            // les bases de test sont reconstruites par migrate:fresh, et le CHECK
            // SQLite généré par Laravel n'inclut que les valeurs d'origine. On
            // supprime le CHECK en recréant la colonne en TEXT (comportement
            // équivalent aux environnements de test).
            DB::statement('DROP INDEX IF EXISTS idx_cn_status_date');
            DB::statement('ALTER TABLE credit_notes RENAME COLUMN status TO status_old');
            DB::statement("ALTER TABLE credit_notes ADD COLUMN status TEXT NOT NULL DEFAULT 'brouillon'");
            DB::statement('UPDATE credit_notes SET status = status_old');
            DB::statement('ALTER TABLE credit_notes DROP COLUMN status_old');
            DB::statement('CREATE INDEX idx_cn_status_date ON credit_notes (status, issued_at)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE credit_notes MODIFY COLUMN status ENUM('brouillon', 'valide', 'applique', 'annule') NOT NULL DEFAULT 'brouillon'");
        }
    }
};
