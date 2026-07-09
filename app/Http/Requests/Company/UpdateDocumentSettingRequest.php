<?php
namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentSettingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'primary_color'     => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'font_family'       => 'nullable|string|in:DejaVu Sans,DejaVu Serif,Helvetica,Times New Roman',
            'page_size'         => 'nullable|in:A4,A5,Letter',
            'orientation'       => 'nullable|in:portrait,landscape',
            'show_logo'         => 'boolean',
            'show_watermark'    => 'boolean',
            'watermark_text'    => 'nullable|string|max:50',
            'product_columns'   => 'nullable|array',
            'product_columns.*' => 'string|in:reference,description,longueur,epaisseur,quantity,unit_price,discount,tax,total_ht,total_ttc',
            'footer_text'       => 'nullable|string|max:500',
            'terms_conditions'  => 'nullable|string',
            'signature_name'    => 'nullable|string|max:100',
            'signature_title'   => 'nullable|string|max:100',
            // [SEC] mimes: explicite — exclut SVG (peut embarquer <script>, servi en disque public).
            'signature_image'   => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
            'stamp_image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
            // [Maquette Paramétrage société] en-tête / pied de page PDF (SVG exclu — XSS)
            'pdf_header_file'   => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048',
            'pdf_footer_file'   => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:2048',
        ];
    }
}
