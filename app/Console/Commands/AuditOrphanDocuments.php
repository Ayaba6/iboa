<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [Ventes §17] Détecte les documents dont le parent a disparu.
 *
 * Motif de création : neuf bons de préparation pointaient vers des commandes
 * absentes de la table `orders`, alors qu'une contrainte
 * `bon_preparations_order_id_foreign` existe. InnoDB ne laisse pas passer ça —
 * les commandes ont donc été effacées contraintes désactivées, signature d'un
 * `migrate:fresh` ou d'un seeder appelant `disableForeignKeyConstraints()`.
 *
 * Ces orphelins n'étaient signalés par rien. Ils sont apparus dans un inventaire
 * manuel, pas dans une alerte de l'ERP : à l'écran, la colonne « commande »
 * restait simplement vide.
 *
 * Lecture seule. Ne répare rien : la réparation d'un orphelin est une décision
 * métier (rattacher ? annuler ? purger ?) qui n'appartient pas à un audit.
 */
class AuditOrphanDocuments extends Command
{
    protected $signature = 'a3:audit-orphans {--company= : Restreindre à une société}';

    protected $description = 'Détecte les documents dont le parent a disparu (clés étrangères contournées).';

    /**
     * Liens vérifiés : table enfant, colonne, table parente, libellé.
     *
     * Seuls les rattachements STRUCTURANTS sont listés — ceux dont l'absence rend
     * le document inexploitable ou fausse un indicateur. Un lien facultatif
     * (commercial, dépôt) n'a pas sa place ici.
     *
     * @var list<array{0:string,1:string,2:string,3:string}>
     */
    private const LINKS = [
        ['bon_preparations',   'order_id',      'orders',          'Bons de préparation sans commande'],
        ['delivery_notes',     'order_id',      'orders',          'Bons de livraison sans commande'],
        ['delivery_note_items', 'delivery_note_id', 'delivery_notes', 'Lignes de BL sans bon de livraison'],
        ['invoices',           'order_id',      'orders',          'Factures sans commande'],
        ['invoice_items',      'invoice_id',    'invoices',        'Lignes de facture sans facture'],
        ['order_items',        'order_id',      'orders',          'Lignes de commande sans commande'],
        ['orders',             'client_id',     'clients',         'Commandes sans client'],
        ['credit_notes',       'invoice_id',    'invoices',        'Avoirs sans facture'],
        ['client_payments',    'client_id',     'clients',         'Encaissements sans client'],
        ['stock_reservations', 'order_id',      'orders',          'Réservations de stock sans commande'],
    ];

    public function handle(): int
    {
        $companyId = $this->option('company');
        $anomalies = 0;
        $skipped   = [];

        $this->info('── Audit des documents orphelins ──');
        $this->newLine();

        foreach (self::LINKS as [$child, $column, $parent, $label]) {
            // Un lien portant sur une table absente n'est PAS un lien sain : on le
            // déclare non vérifié plutôt que de le compter à zéro.
            if (! Schema::hasTable($child) || ! Schema::hasTable($parent)) {
                $skipped[] = "{$label} — table absente";
                continue;
            }
            if (! Schema::hasColumn($child, $column)) {
                $skipped[] = "{$label} — colonne {$column} absente";
                continue;
            }

            // Jointure sur la table BRUTE, sans Eloquent : un parent en suppression
            // logique n'est donc PAS compté comme disparu. Sa ligne existe encore et
            // reste consultable — seule son absence physique fait un orphelin.
            $query = DB::table($child.' as c')
                ->leftJoin($parent.' as p', 'p.id', '=', "c.{$column}")
                ->whereNotNull("c.{$column}")
                ->whereNull('p.id');

            // Un enfant lui-même RETIRÉ (suppression logique) n'est plus un
            // orphelin ACTIF : il ne circule plus dans l'ERP, même si sa ligne
            // reste consultable pour l'historique. Le signaler indéfiniment
            // rendrait l'audit incapable de revenir au vert après un retrait
            // pourtant assumé et tracé.
            if (Schema::hasColumn($child, 'deleted_at')) {
                $query->whereNull('c.deleted_at');
            }

            if ($companyId && Schema::hasColumn($child, 'company_id')) {
                $query->where('c.company_id', $companyId);
            }

            $rows = $query->orderBy('c.id')->limit(20)->get(['c.id', "c.{$column} as parent_id"]);
            $count = $query->count();

            if ($count === 0) {
                $this->line(sprintf('  <fg=green>OK</>    %-42s 0', $label));
                continue;
            }

            $anomalies += $count;
            $this->line(sprintf('  <fg=red>ANOMALIE</> %-40s %d', $label, $count));

            $sample = $rows->take(10)
                ->map(fn ($r) => "#{$r->id}→{$r->parent_id}")
                ->implode(', ');
            $this->line("            {$child}.{$column} : {$sample}".($count > 10 ? ', …' : ''));
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('Liens NON VÉRIFIÉS (ni sains, ni fautifs) :');
            foreach ($skipped as $reason) {
                $this->line("  - {$reason}");
            }
        }

        $this->newLine();

        if ($anomalies > 0) {
            $this->error(
                "{$anomalies} document(s) orphelin(s). Une clé étrangère a été contournée — "
                .'typiquement un migrate:fresh ou un seeder exécuté contraintes désactivées. '
                .'Aucune modification effectuée : le sort de chaque orphelin est une décision métier.'
            );

            return self::FAILURE;
        }

        $this->info('AUCUN ORPHELIN — tous les rattachements vérifiés pointent vers un parent existant.');

        return self::SUCCESS;
    }
}
