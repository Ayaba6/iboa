@props([
    'model' => null,            // ex: App\Models\Quote::class
    'id' => null,               // id de l'entité
    'limit' => 20,
    'title' => 'Historique d\'activité',
])
@php
    $modelClass = is_string($model) ? $model : (is_object($model) ? get_class($model) : null);
    $logs = $modelClass && $id
        ? \App\Models\AuditLog::where('model_type', $modelClass)
            ->where('model_id', $id)
            ->latest('created_at')
            ->limit($limit)
            ->get()
        : collect();

    // Libellé module + référence du document (number si le modèle en a un)
    $moduleLabels = [
        'Quote'           => 'Devis',
        'Order'           => 'Commande',
        'DeliveryNote'    => 'Bon de livraison',
        'Invoice'         => 'Facture',
        'CreditNote'      => 'Avoir',
        'StockTransfer'   => 'Transfert stock',
        'Reception'       => 'Réception',
        'Rfq'             => 'Appel d\'offres',
        'PurchaseOrder'   => 'Commande fournisseur',
        'SupplierInvoice' => 'Facture fournisseur',
        'JournalEntry'    => 'Écriture comptable',
        'ProductionOrder' => 'Ordre de fabrication',
        'StockMovement'   => 'Mouvement de stock',
        'ClientPayment'   => 'Paiement client',
        'SupplierPayment' => 'Paiement fournisseur',
    ];
    $moduleLabel = $moduleLabels[class_basename($modelClass ?? '')] ?? class_basename($modelClass ?? '');
    $docRef      = null;
    if ($modelClass && $id && $logs->isNotEmpty()) {
        $doc    = $modelClass::withoutGlobalScopes()->find($id);
        $docRef = $doc->number ?? $doc->reference ?? ('#' . $id);
    }

    $actionMeta = function (string $action): array {
        if (str_starts_with($action, 'approval_')) {
            $sub = substr($action, 9);
            return [
                'en_attente' => ['Soumis à approbation',     'bg-amber-50 text-amber-700 border-amber-200'],
                'approuve'   => ['Approuvé',                 'bg-green-50 text-green-700 border-green-200'],
                'rejete'     => ['Rejeté',                   'bg-red-50 text-red-700 border-red-200'],
                'non_requis' => ['Approbation non requise',  'bg-gray-50 text-gray-600 border-gray-200'],
            ][$sub] ?? [ucfirst($sub), 'bg-gray-50 text-gray-600 border-gray-200'];
        }
        return [
            'created'                 => ['Création',               'bg-blue-50 text-blue-700 border-blue-200'],
            'updated'                 => ['Modification',           'bg-[#eef5f0] text-emerald-800 border-emerald-200'],
            'deleted'                 => ['Suppression',            'bg-red-50 text-red-700 border-red-200'],
            'restored'                => ['Restauration',           'bg-emerald-50 text-emerald-800 border-emerald-200'],
            'status_changed'          => ['Changement de statut',   'bg-violet-50 text-violet-700 border-violet-200'],
            'validated'               => ['Validation',             'bg-green-50 text-green-700 border-green-200'],
            'paid'                    => ['Paiement',               'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'payment_created'         => ['Paiement enregistré',    'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'payment_cancelled'       => ['Paiement annulé',        'bg-orange-50 text-orange-700 border-orange-200'],
            'payment_modified'        => ['Paiement modifié',       'bg-[#eef5f0] text-emerald-800 border-emerald-200'],
            'journal_entry_created'   => ['Écriture comptable',     'bg-sky-50 text-sky-700 border-sky-200'],
            'stock_movement'          => ['Mouvement de stock',     'bg-cyan-50 text-cyan-700 border-cyan-200'],
            'stock_movement_modified' => ['Mouvement modifié',      'bg-cyan-50 text-cyan-700 border-cyan-200'],
        ][$action] ?? [ucfirst(str_replace('_', ' ', $action)), 'bg-gray-50 text-gray-600 border-gray-200'];
    };

    // Traduction des champs les plus fréquents
    $fieldLabels = [
        'status'            => 'Statut',
        'submitted_at'      => 'Soumis le',
        'submitted_by'      => 'Soumis par',
        'validated_at'      => 'Validé le',
        'validated_by'      => 'Validé par',
        'rejected_at'       => 'Refusé le',
        'rejected_by'       => 'Refusé par',
        'rejection_reason'  => 'Motif de refus',
        'issued_at'         => 'Date d\'émission',
        'expires_at'        => 'Date de validité',
        'due_at'            => 'Échéance',
        'delivery_date'     => 'Date de livraison',
        'client_id'         => 'Client',
        'notes'             => 'Notes',
        'reference'         => 'Référence',
        'subtotal_ht'       => 'Total HT',
        'total_tax'         => 'TVA',
        'total_ttc'         => 'Total TTC',
        'remaining_amount'  => 'Reste à payer',
        'paid_amount'       => 'Montant payé',
        'invoiced_amount'   => 'Montant facturé',
        'global_discount_amount' => 'Remise globale',
        'quantity_produced' => 'Quantité produite',
        'number'            => 'Numéro',
    ];

    $fmt = fn ($v) => ($v === null || $v === '')
        ? 'vide'
        : (is_scalar($v) ? \Str::limit((string) $v, 45) : json_encode($v, JSON_UNESCAPED_UNICODE));

    // Aplatit chaque log en lignes « une par champ modifié »
    $rows = [];
    foreach ($logs as $log) {
        $newValues = is_array($log->new_values) ? $log->new_values : (json_decode($log->new_values ?? '[]', true) ?: []);
        $oldValues = is_array($log->old_values) ? $log->old_values : (json_decode($log->old_values ?? '[]', true) ?: []);
        $changes = [];
        foreach ($newValues as $k => $v) {
            if (in_array($k, ['updated_at', 'created_at'])) continue;
            $changes[$k] = ['old' => $oldValues[$k] ?? null, 'new' => $v];
        }

        if (empty($changes)) {
            $rows[] = ['log' => $log, 'field' => null, 'old' => null, 'new' => null, 'span' => 1, 'first' => true];
            continue;
        }
        $first = true;
        $span  = count($changes);
        foreach ($changes as $field => $pair) {
            $rows[] = [
                'log'   => $log,
                'field' => $field,
                'old'   => $pair['old'],
                'new'   => $pair['new'],
                'span'  => $span,
                'first' => $first,
            ];
            $first = false;
        }
    }
@endphp

<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $title }}
        </h2>
        <span class="text-xs text-gray-400">{{ $logs->count() }} événement{{ $logs->count() > 1 ? 's' : '' }}</span>
    </div>

    @if($logs->isEmpty())
        <div class="p-6 text-center text-gray-400 text-sm">Aucune activité enregistrée.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide whitespace-nowrap">Date / Heure</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Utilisateur</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Action</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Champ</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Ancienne valeur</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Nouvelle valeur</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Document</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden lg:table-cell">Référence</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide hidden xl:table-cell">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($rows as $r)
                @php [$label, $badge] = $actionMeta($r['log']->action); @endphp
                <tr class="hover:bg-gray-50 {{ $r['first'] ? 'border-t border-gray-200' : '' }}">
                    @if($r['first'])
                    <td class="px-3 py-2.5 whitespace-nowrap align-top" rowspan="{{ $r['span'] }}">
                        <span class="text-gray-700 tabular-nums">{{ $r['log']->created_at?->format('d/m/Y H:i:s') }}</span>
                        <span class="block text-xs text-gray-400">{{ $r['log']->created_at?->diffForHumans(short: true) }}</span>
                    </td>
                    <td class="px-3 py-2.5 align-top font-medium text-gray-800 whitespace-nowrap" rowspan="{{ $r['span'] }}">
                        {{ $r['log']->user_name ?? 'Système' }}
                    </td>
                    <td class="px-3 py-2.5 align-top" rowspan="{{ $r['span'] }}">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border whitespace-nowrap {{ $badge }}">{{ $label }}</span>
                    </td>
                    @endif
                    <td class="px-3 py-2.5">
                        @if($r['field'])
                            <span class="text-gray-700">{{ $fieldLabels[$r['field']] ?? $r['field'] }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5">
                        @if($r['field'] && !in_array($r['log']->action, ['created']))
                            <span class="{{ $r['old'] === null || $r['old'] === '' ? 'text-gray-300 italic' : 'text-red-500 line-through' }}">{{ $fmt($r['old']) }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5">
                        @if($r['field'])
                            <span class="text-emerald-700 font-medium">{{ $fmt($r['new']) }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    @if($r['first'])
                    <td class="px-3 py-2.5 align-top text-gray-600 hidden lg:table-cell whitespace-nowrap" rowspan="{{ $r['span'] }}">{{ $moduleLabel }}</td>
                    <td class="px-3 py-2.5 align-top font-mono text-xs text-emerald-800 hidden lg:table-cell whitespace-nowrap" rowspan="{{ $r['span'] }}">{{ $docRef }}</td>
                    <td class="px-3 py-2.5 align-top text-xs text-gray-400 hidden xl:table-cell whitespace-nowrap" rowspan="{{ $r['span'] }}">{{ $r['log']->ip_address ?? '—' }}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
