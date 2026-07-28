<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restreint toutes les requêtes Eloquent à la société du CONTEXTE COURANT.
 *
 * [Ventes §6] Correction d'un défaut d'isolation : la version précédente
 * filtrait systématiquement sur `SELECT id FROM companies ORDER BY id LIMIT 1`,
 * c'est-à-dire toujours la PREMIÈRE société créée, quelle que soit la société
 * de l'utilisateur. Sur une base mono-société le résultat était juste par
 * coïncidence ; dès qu'une deuxième société existe, ses utilisateurs voient les
 * données de la société n°1 et pas les leurs — une fuite inter-société, pas un
 * simple défaut d'affichage.
 *
 * Résolution :
 *   - société liée au contexte (`current_company`, posée par le middleware
 *     SetCurrentCompany ou explicitement dans les jobs/commandes) → filtre sur
 *     son identifiant ;
 *   - aucun contexte (CLI sans société explicite) → repli sur la sous-requête
 *     historique, qui reste correcte sur une base mono-société.
 *
 * Le repli n'invente rien : en l'absence de contexte, il n'y a pas de société
 * « demandée » à respecter, et une base mono-société n'a qu'une réponse possible.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $column = $model->getTable().'.company_id';
        $companyId = self::contextCompanyId();

        if ($companyId !== null) {
            $builder->where($column, $companyId);

            return;
        }

        $builder->where(
            $column,
            fn ($q) => $q->select('id')->from('companies')->orderBy('id')->limit(1)
        );
    }

    /** Identifiant de la société du contexte, ou null si aucun contexte n'est posé. */
    public static function contextCompanyId(): ?int
    {
        if (! app()->has('current_company')) {
            return null;
        }

        $company = app('current_company');
        $id = is_object($company) ? ($company->id ?? null) : $company;

        return $id ? (int) $id : null;
    }
}
