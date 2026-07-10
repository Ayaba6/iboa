<?php

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * [PARITÉ SAGE X3] `net_amount` = montant reçu − frais bancaires (informatif).
     * N'affecte NI l'allocation aux factures NI l'écriture comptable (calculées
     * sur `amount`). Recalculé côté serveur pour éviter toute incohérence front.
     */
    protected function prepareForValidation(): void
    {
        $amount = (int) $this->input('amount', 0);
        $fees   = (int) $this->input('bank_fees', 0);
        $this->merge([
            'bank_fees'  => max(0, $fees),
            'net_amount' => max(0, $amount - max(0, $fees)),
        ]);
    }

    public function rules(): array
    {
        return [
            'client_id'                          => 'required|exists:clients,id',
            'payment_method_id'                  => 'nullable|exists:payment_methods,id',
            // [RELATION MÉTIER] Tout encaissement entre dans UNE caisse/banque —
            // sans elle, la transaction de caisse et l'imputation comptable (571/521)
            // ne peuvent pas être rattachées au bon compte.
            'cash_account_id'                    => 'required|exists:cash_accounts,id',
            'amount'                             => 'required|numeric|min:1',
            'payment_date'                       => 'required|date',
            'reference'                          => 'nullable|string|max:100',
            'phone_number'                       => 'nullable|string|max:20',
            'notes'                              => 'nullable|string',
            'is_acompte'                         => 'nullable|boolean',
            'force_duplicate'                    => 'nullable|boolean',
            'allocations'                        => 'nullable|array',
            'allocations.*.invoice_id'           => 'required_with:allocations.*|exists:invoices,id',
            'allocations.*.allocated_amount'     => 'required_with:allocations.*|numeric|min:0',
            // [PARITÉ SAGE X3] Champs descriptifs (métadonnées, sans impact monétaire)
            'bank_fees'                          => 'nullable|integer|min:0',
            'net_amount'                         => 'nullable|integer|min:0',
            'value_date'                         => 'nullable|date',
            'piece_number'                       => 'nullable|string|max:60',
            'bank_reference'                     => 'nullable|string|max:100',
            'treasury_journal'                   => 'nullable|string|max:20',
            'payment_condition'                  => 'nullable|string|max:60',
            'cost_center'                        => 'nullable|string|max:30',
            'analytic_section'                   => 'nullable|string|max:30',
            'project'                            => 'nullable|string|max:60',
            'salesperson'                        => 'nullable|string|max:100',
            'site'                               => 'nullable|string|max:40',
            'observations'                       => 'nullable|string|max:1000',
            'documents'                          => 'nullable|array',
            'documents.*'                        => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id'                      => 'client',
            'payment_method_id'              => 'mode de paiement',
            'cash_account_id'                => 'caisse / compte',
            'payment_date'                   => 'date du paiement',
            'amount'                         => 'montant',
            'reference'                      => 'référence',
            'phone_number'                   => 'numéro de téléphone',
            'allocations.*.invoice_id'       => 'facture',
            'allocations.*.allocated_amount' => 'montant imputé',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required'                        => 'Veuillez sélectionner un client.',
            'client_id.exists'                          => 'Le client sélectionné est invalide.',
            'amount.required'                           => 'Le montant encaissé est obligatoire.',
            'amount.numeric'                            => 'Le montant doit être un nombre valide.',
            'amount.min'                                => 'Le montant doit être supérieur à 0.',
            'payment_date.required'                     => 'La date de paiement est obligatoire.',
            'payment_date.date'                         => 'La date de paiement n\'est pas une date valide.',
            'payment_method_id.exists'                  => 'Le mode de paiement sélectionné est invalide.',
            'cash_account_id.exists'                    => 'La caisse sélectionnée est invalide.',
            'reference.max'                             => 'La référence ne peut pas dépasser 100 caractères.',
            'allocations.*.invoice_id.required_with'    => 'Chaque imputation doit référencer une facture.',
            'allocations.*.invoice_id.exists'           => 'Une facture sélectionnée pour imputation est invalide.',
            'allocations.*.allocated_amount.required_with' => 'Le montant imputé est obligatoire pour chaque facture.',
            'allocations.*.allocated_amount.numeric'    => 'Le montant imputé doit être un nombre valide.',
            'allocations.*.allocated_amount.min'        => 'Le montant imputé ne peut pas être négatif.',
        ];
    }
}
