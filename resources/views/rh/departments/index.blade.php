@extends('layouts.erp')
@section('title', 'Départements RH')
@section('breadcrumb')
    <a href="{{ route('rh.employes.index') }}" class="hover:text-gray-700">Employés</a>
    <span class="mx-1">/</span><span>Départements</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-[16px] font-bold text-gray-900">Départements</h1>
    <button onclick="document.getElementById('modal-dept').classList.remove('hidden')"
            class="inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-semibold hover:bg-emerald-800">
        + Nouveau département
    </button>
</div>

<div class="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
    <table class="w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-[#eef5f0] border-b border-gray-300 text-[11px] text-emerald-900 font-bold uppercase tracking-wide">
            <tr>
                <th class="px-3 py-1.5 text-left">Nom</th>
                <th class="px-3 py-1.5 text-left">Code</th>
                <th class="px-3 py-1.5 text-left">Description</th>
                <th class="px-3 py-1.5 text-center">Employés</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($departments as $dept)
        <tr class="hover:bg-gray-50">
            <td class="px-3 py-1.5 font-medium">{{ $dept->name }}</td>
            <td class="px-3 py-1.5 font-mono text-xs text-gray-500">{{ $dept->code ?? '—' }}</td>
            <td class="px-3 py-1.5 text-gray-600">{{ $dept->description ?? '—' }}</td>
            <td class="px-3 py-1.5 text-center">{{ $dept->employees_count ?? 0 }}</td>
        </tr>
        @empty
        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">Aucun département. Créez-en un.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if($departments->hasPages())
    <div class="px-3 py-1.5 border-t">{{ $departments->links() }}</div>
    @endif
</div>

<div id="modal-dept" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-[4px] shadow-xl w-96 p-6">
        <h3 class="text-base font-semibold mb-4">Nouveau département</h3>
        <form method="POST" action="{{ route('rh.departments.store') }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                    <input type="text" name="code" maxlength="20" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" name="description" class="w-full border border-gray-300 rounded-[4px] px-3 py-2 text-sm">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-dept').classList.add('hidden')"
                            class="px-3 py-1.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm">Annuler</button>
                    <button type="submit" class="px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium">Créer</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
