{{-- <x-erp.table :dark="false"> <x-slot:head><tr><th>…</th></tr></x-slot:head> <tr>…</tr> </x-erp.table> --}}
@props(['dark' => false, 'empty' => null])
<div class="erp-table-wrapper">
    <table {{ $attributes->merge(['class' => 'erp-table' . ($dark ? ' erp-table--dark' : '')]) }}>
        @isset($head)<thead>{{ $head }}</thead>@endisset
        <tbody>
            {{ $slot }}
        </tbody>
        @isset($foot)<tfoot>{{ $foot }}</tfoot>@endisset
    </table>
    @if($empty)<div class="erp-table-empty">{{ $empty }}</div>@endif
</div>
