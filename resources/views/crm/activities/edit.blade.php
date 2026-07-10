@extends('layouts.erp')
@section('title', 'CRM — Modifier activité')

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.activities.index') }}" class="hover:text-gray-700">Activités</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<form method="POST" action="{{ route('crm.activities.update', $activity) }}" class="space-y-3">
    @csrf
    @method('PUT')

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Modifier activité</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.activities.index') }}"
               class="px-3 py-2.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                Annuler
            </a>
            <button type="submit"
                    class="px-3 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                Mettre à jour
            </button>
        </div>
    </div>

    @include('crm.activities._form')
</form>
@endsection
