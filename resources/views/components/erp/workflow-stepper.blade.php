{{-- <x-erp.workflow-stepper :steps="['Devis','Commande','BL','Facture','Paiement']" :current="2" />
     current = index (0-based) de l'étape active ; les précédentes sont "complétées". --}}
@props(['steps' => [], 'current' => 0])
<div {{ $attributes->merge(['class' => 'erp-stepper']) }}>
    @foreach($steps as $i => $step)
    @php $state = $i < $current ? 'erp-step-completed' : ($i === (int) $current ? 'erp-step-active' : 'erp-step-disabled'); @endphp
    <span class="erp-step {{ $state }}">
        <span class="erp-step-dot">@if($i < $current)&#10003;@else{{ $i + 1 }}@endif</span>
        {{ $step }}
    </span>
    @endforeach
</div>
