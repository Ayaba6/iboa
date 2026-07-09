<?php
namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:150',
            'trade_name'  => 'nullable|string|max:150',
            'slogan'      => 'nullable|string|max:255',
            // [SEC] SVG exclu — peut embarquer <script>, fichier servi en disque public
            // (rendu image/svg+xml en navigation directe = exécution JS, XSS stocké).
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'region'      => 'nullable|string|max:100',
            'country'     => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone'       => 'nullable|string|max:20',
            'phone2'      => 'nullable|string|max:20',
            'fax'         => 'nullable|string|max:20',
            'email'       => 'nullable|email|max:150',
            'website'     => 'nullable|url|max:150',
            // [Maquette Paramétrage société]
            'company_code'      => 'nullable|string|max:30',
            'sigle'             => 'nullable|string|max:20',
            'cnss_number'       => 'nullable|string|max:40',
            'main_activity'     => 'nullable|string|max:120',
            'language'          => 'nullable|string|max:20',
            'timezone'          => 'nullable|string|max:60',
            'opened_at'         => 'nullable|date',
            'status'            => 'nullable|string|max:15',
            'notes'             => 'nullable|string|max:1000',
            'district'          => 'nullable|string|max:80',
            'po_box'            => 'nullable|string|max:20',
            'main_contact'      => 'nullable|string|max:100',
            'accounting_email'  => 'nullable|email|max:120',
            'fiscal_regime'     => 'nullable|string|max:40',
            'vat_mode'          => 'nullable|string|max:20',
            'default_vat_rate'  => 'nullable|numeric|min:0|max:100',
            'tax_center'        => 'nullable|string|max:80',
            'withholding_regime'=> 'nullable|string|max:30',
            'taxpayer_type'     => 'nullable|string|max:30',
            'multi_sites'           => 'nullable|boolean',
            'vat_management'        => 'nullable|boolean',
            'validation_workflow'   => 'nullable|boolean',
            'electronic_signature'  => 'nullable|boolean',
            'auto_pdf_print'        => 'nullable|boolean',
            'email_notifications'   => 'nullable|boolean',
            'secondary_currency'    => 'nullable|boolean',
            'maintenance_mode'      => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'  => 'raison sociale',
            'email' => 'adresse email',
            'logo'  => 'logo',
        ];
    }
}
