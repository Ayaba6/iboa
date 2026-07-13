<?php

namespace App\Services;

use App\Models\Appraisal;
use App\Models\AlertRule;
use App\Models\ExpenseReport;
use App\Models\ProductStock;
use App\Models\StockLoss;
use App\Models\TrainingParticipant;
use App\Modules\Quality\Models\CorrectiveAction;
use App\Notifications\ValidationStepNotification;

/**
 * [PIL-04] Évaluation des règles d'alerte par seuil.
 */
class AlertRuleService
{
    /**
     * Registre des indicateurs disponibles : clé → [label, unit, resolver].
     * @return array<string, array{label:string, unit:string, resolver:callable}>
     */
    public function metrics(): array
    {
        return [
            'stock_rupture' => [
                'label' => 'Articles en rupture (stock ≤ 0)', 'unit' => '',
                'resolver' => fn () => ProductStock::where('quantity', '<=', 0)->count(),
            ],
            'pertes_valeur_mois' => [
                'label' => 'Valeur des pertes validées ce mois', 'unit' => 'F',
                'resolver' => fn () => (float) StockLoss::where('status', 'validee')
                    ->whereMonth('validated_at', now()->month)->whereYear('validated_at', now()->year)
                    ->sum('estimated_value'),
            ],
            'capa_en_retard' => [
                'label' => 'Actions correctives en retard', 'unit' => '',
                'resolver' => fn () => CorrectiveAction::whereNotNull('due_date')
                    ->whereDate('due_date', '<', now())
                    ->whereNotIn('status', ['faite', 'verifiee', 'cloturee'])->count(),
            ],
            'habilitations_echeance' => [
                'label' => 'Habilitations à échéance (≤ 60 j)', 'unit' => '',
                'resolver' => fn () => TrainingParticipant::whereNotNull('certificate_expiry')
                    ->whereDate('certificate_expiry', '<=', now()->addDays(60))->count(),
            ],
            'notes_frais_a_approuver' => [
                'label' => 'Notes de frais à approuver', 'unit' => '',
                'resolver' => fn () => ExpenseReport::where('status', 'soumise')->count(),
            ],
            'evaluations_a_finaliser' => [
                'label' => 'Évaluations à finaliser', 'unit' => '',
                'resolver' => fn () => Appraisal::where('status', '!=', 'finalisee')->count(),
            ],
        ];
    }

    /** Valeur courante de l'indicateur d'une règle. */
    public function currentValue(AlertRule $rule): float
    {
        $metric = $this->metrics()[$rule->metric] ?? null;

        return $metric ? (float) ($metric['resolver'])() : 0.0;
    }

    /** Teste si la valeur déclenche l'alerte selon l'opérateur. */
    public function isTriggered(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            'gt'  => $value > $threshold,
            'gte' => $value >= $threshold,
            'lt'  => $value < $threshold,
            'lte' => $value <= $threshold,
            'eq'  => abs($value - $threshold) < 1e-9,
            default => false,
        };
    }

    /** @return array{value: float, triggered: bool} */
    public function evaluate(AlertRule $rule): array
    {
        $value = $this->currentValue($rule);

        return ['value' => $value, 'triggered' => $this->isTriggered($value, $rule->operator, (float) $rule->threshold)];
    }

    /**
     * Évalue toutes les règles actives ; notifie les rôles cibles des règles
     * déclenchées. Retourne le nombre de règles déclenchées.
     */
    public function run(): int
    {
        $triggered = 0;

        foreach (AlertRule::where('is_active', true)->get() as $rule) {
            $res = $this->evaluate($rule);
            $rule->forceFill(['last_value' => $res['value']]);

            if ($res['triggered']) {
                $rule->last_triggered_at = now();
                $triggered++;

                $roles = $rule->target_roles ?: ['super_admin'];
                $label = $this->metrics()[$rule->metric]['label'] ?? $rule->metric;
                ValidationStepNotification::sendToRoles(
                    $roles,
                    title: 'Alerte : '.$rule->name,
                    message: $label.' = '.rtrim(rtrim(number_format($res['value'], 2, ',', ' '), '0'), ',')
                        .' ('.$rule->operatorSymbol().' '.rtrim(rtrim(number_format((float) $rule->threshold, 2, ',', ' '), '0'), ',').')',
                    url: route('pilotage.alertes.index'),
                    modelType: AlertRule::class,
                    modelId: $rule->id,
                    type: 'alerte_seuil',
                    icon: 'bell-alert',
                    color: 'red',
                );
            }

            $rule->save();
        }

        return $triggered;
    }
}
