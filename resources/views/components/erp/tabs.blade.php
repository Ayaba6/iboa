{{-- <x-erp.tabs :tabs="['general' => 'Général', 'banque' => 'Banque']" model="tab" />
     Requiert un parent Alpine x-data="{ tab: 'general' }". Chaque clic défile vers
     $refs['sec_<clé>'] si présent (pattern ancres) sinon bascule simplement l'état. --}}
@props(['tabs' => [], 'model' => 'tab'])
<nav {{ $attributes->merge(['class' => 'erp-tabs']) }}>
    @foreach($tabs as $key => $label)
    <button type="button"
            @click="{{ $model }} = '{{ $key }}'; $refs['sec_{{ $key }}']?.scrollIntoView({behavior: 'smooth', block: 'start'})"
            class="erp-tab" :class="{{ $model }} === '{{ $key }}' ? 'erp-tab-active' : ''">{{ $label }}</button>
    @endforeach
</nav>
