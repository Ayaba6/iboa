<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * [CRM] Invalide le cache KPI du tableau de bord CRM (5 min) à chaque écriture
 * sur un modèle CRM. Sans cela, un contact ou une opportunité créé restait
 * invisible dans les compteurs jusqu'à expiration du TTL alors que les listes
 * temps réel l'affichaient déjà.
 */
trait BustsCrmKpiCache
{
    protected static function bootBustsCrmKpiCache(): void
    {
        $bust = function ($model): void {
            if ($model->company_id) {
                Cache::forget('crm:kpis:' . $model->company_id . ':' . now()->format('Y-m'));
            }
        };

        static::saved($bust);
        static::deleted($bust);
    }
}
