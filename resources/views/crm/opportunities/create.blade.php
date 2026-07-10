@extends('layouts.erp')
@section('title', 'CRM — Nouvelle opportunité')

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.opportunities.index') }}" class="hover:text-gray-700">Pipeline</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
<form method="POST" action="{{ route('crm.opportunities.store') }}" class="space-y-3">
    @csrf
    @if($contactId)<input type="hidden" name="crm_contact_id" value="{{ $contactId }}">@endif

    <div class="flex items-center justify-between">
        <h1 class="text-[16px] font-bold text-gray-900">Nouvelle opportunité</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.opportunities.index') }}"
               class="px-3 py-2.5 border border-gray-300 text-gray-700 rounded-[4px] text-sm font-medium hover:bg-gray-50 transition-colors">
                Annuler
            </a>
            <button type="submit"
                    class="px-3 py-2.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800 transition-colors">
                Enregistrer
            </button>
        </div>
    </div>

    @include('crm.opportunities._form')
</form>
@endsection
