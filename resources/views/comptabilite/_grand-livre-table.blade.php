@php
    // $withAccount : colonnes Compte / Intitulé (mode plat maquette X3). $lines : collection de lignes.
    $withAccount = $withAccount ?? false;
    $colspanTot  = $withAccount ? 8 : 6;
@endphp
<div class="overflow-x-auto">
    <table class="min-w-full text-[14px] border-collapse">
        <thead class="bg-[#3b4248] text-[12px] font-semibold text-white">
            <tr>
                <th class="px-3 py-1.5 text-left">Date</th>
                <th class="px-3 py-1.5 text-left">Journal</th>
                <th class="px-3 py-1.5 text-left">N° pièce</th>
                <th class="px-3 py-1.5 text-left">Référence</th>
                @if($withAccount)
                <th class="px-3 py-1.5 text-left">Compte</th>
                <th class="px-3 py-1.5 text-left">Intitulé compte</th>
                @endif
                <th class="px-3 py-1.5 text-left">Tiers</th>
                <th class="px-3 py-1.5 text-left">Libellé</th>
                <th class="px-3 py-1.5 text-right">Débit (XOF)</th>
                <th class="px-3 py-1.5 text-right">Crédit (XOF)</th>
                <th class="px-3 py-1.5 text-right">Solde cumulé (XOF)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @php $running = 0; @endphp
            @forelse($lines as $line)
            @php $running += $line->debit - $line->credit; @endphp
            <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                <td class="px-3 py-1 text-gray-600 whitespace-nowrap tabular-nums">
                    {{ $line->journalEntry?->entry_date?->format('d/m/Y') ?? '—' }}
                </td>
                <td class="px-3 py-1 whitespace-nowrap">
                    <span class="font-mono text-[11px] bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded-[3px]">
                        {{ $line->journalEntry?->journalType?->code ?? '—' }}
                    </span>
                    @if($line->journalEntry?->status === 'brouillon')
                    <span class="ml-1 text-[10px] font-bold text-amber-600 uppercase" title="Écriture non validée">brouillon</span>
                    @endif
                </td>
                <td class="px-3 py-1 whitespace-nowrap">
                    @if($line->journalEntry)
                    <a href="{{ route('comptabilite.journaux.show', $line->journal_entry_id) }}"
                       class="font-mono font-semibold text-blue-600 hover:text-blue-800 text-[13px]">
                        {{ $line->journalEntry->number ?? '—' }}
                    </a>
                    @else
                    <span class="text-gray-400 text-xs">—</span>
                    @endif
                </td>
                <td class="px-3 py-1 font-mono text-[12px] text-gray-500 whitespace-nowrap">
                    {{ $line->reconciliation_ref ?: ($line->journalEntry?->reference ?: '—') }}
                </td>
                @if($withAccount)
                <td class="px-3 py-1 font-mono font-semibold text-gray-700 text-[13px]">{{ $line->account?->code ?? '—' }}</td>
                <td class="px-3 py-1 text-gray-600 text-[12px] max-w-[140px] truncate">{{ $line->account?->name ?? '—' }}</td>
                @endif
                <td class="px-3 py-1 text-gray-600 text-[12px] whitespace-nowrap">
                    {{ $line->partner_name ?: ($line->journalEntry?->partner_name ?: '—') }}
                </td>
                <td class="px-3 py-1 text-gray-700 max-w-xs truncate" title="{{ $line->label ?: $line->journalEntry?->description }}">
                    {{ $line->label ?: $line->journalEntry?->description ?: '—' }}
                </td>
                <td class="px-3 py-1 text-right tabular-nums {{ $line->debit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300' }}">
                    {{ $line->debit > 0 ? number_format($line->debit, 0, ',', ' ') : '—' }}
                </td>
                <td class="px-3 py-1 text-right tabular-nums {{ $line->credit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300' }}">
                    {{ $line->credit > 0 ? number_format($line->credit, 0, ',', ' ') : '—' }}
                </td>
                <td class="px-3 py-1 text-right tabular-nums whitespace-nowrap font-medium
                    {{ $running < 0 ? 'text-red-600' : ($running > 0 ? 'text-gray-900' : 'text-gray-400') }}">
                    @if($running == 0)
                        0
                    @else
                        {{ $running < 0 ? '-' : '' }}{{ number_format(abs($running), 0, ',', ' ') }}
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ $colspanTot + 3 }}" class="px-4 py-8 text-center text-gray-400 text-[13px]">Aucun mouvement.</td>
            </tr>
            @endforelse
        </tbody>

        {{-- Totals footer --}}
        @if($lines->isNotEmpty())
        @php
            $footDebit   = $lines->sum('debit');
            $footCredit  = $lines->sum('credit');
            $footBalance = $footDebit - $footCredit;
        @endphp
        {{-- [Maquette X3] ligne de totaux grise, solde rouge si créditeur --}}
        <tfoot>
            <tr class="bg-[#edf0f2] border-t-2 border-gray-300 font-bold text-gray-900">
                <td colspan="{{ $colspanTot }}" class="px-3 py-1.5 text-right text-[11px] uppercase text-gray-500">
                    Total — {{ $lines->count() }} ligne(s)
                </td>
                <td class="px-3 py-1.5 text-right font-mono tabular-nums">
                    {{ number_format($footDebit, 0, ',', ' ') }}
                </td>
                <td class="px-3 py-1.5 text-right font-mono tabular-nums">
                    {{ number_format($footCredit, 0, ',', ' ') }}
                </td>
                <td class="px-3 py-1.5 text-right font-mono tabular-nums whitespace-nowrap {{ $footBalance < 0 ? 'text-red-600' : '' }}">
                    {{ $footBalance < 0 ? '-' : '' }}{{ number_format(abs($footBalance), 0, ',', ' ') }}
                </td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
