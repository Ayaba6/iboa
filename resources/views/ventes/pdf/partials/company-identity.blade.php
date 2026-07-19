{{--
    Identité société pour en-têtes PDF (devis, BL, avoir…).
    Source unique : paramétrage société (table companies). Évite la duplication.
    Attend : $company. Utilise les classes .company-name / .company-sub définies par la vue hôte.
--}}
<div class="company-name">{{ $company?->trade_name ?? $company?->name ?? 'OA METAL INDUSTRIE' }}</div>
@if($company?->legal_form)
<div class="company-sub">{{ $company->legal_form }}@if($company->share_capital) — Capital : {{ number_format($company->share_capital, 0, ',', ' ') }} {{ $company->share_capital_currency ?? 'FCFA' }}@endif</div>
@endif
@if($company?->address)
<div class="company-sub">{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}</div>
@endif
@if($company?->phone)
<div class="company-sub">Tél. : {{ $company->phone }}@if($company?->phone2) / {{ $company->phone2 }}@endif</div>
@endif
@if($company?->email || $company?->website)
<div class="company-sub">{{ $company->email }}@if($company?->email && $company?->website) · @endif{{ $company->website }}</div>
@endif
@php
    $legalIds = array_filter([
        $company?->ifu         ? 'IFU : '.$company->ifu          : null,
        $company?->rccm        ? 'RCCM : '.$company->rccm         : null,
        $company?->nif         ? 'NIF : '.$company->nif           : null,
        $company?->cnss_number ? 'CNSS : '.$company->cnss_number  : null,
    ]);
@endphp
@if($legalIds)
<div class="company-sub">{{ implode(' | ', $legalIds) }}</div>
@endif
