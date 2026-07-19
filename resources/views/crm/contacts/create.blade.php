@extends('layouts.erp')
@section('title', 'CRM — Nouveau contact')

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.contacts.index') }}" class="hover:text-gray-700">Contacts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<form method="POST" action="{{ route('crm.contacts.store') }}" class="space-y-3">
    @csrf

    <x-x3.title-bar title="Nouveau contact" subtitle="CRM — prospect, contact ou partenaire">
        <x-x3.btn variant="primary" type="submit">Enregistrer</x-x3.btn>
        <x-x3.btn :href="route('crm.contacts.index')">✕ Annuler</x-x3.btn>
    </x-x3.title-bar>

    @include('crm.contacts._form')

    <x-x3.footer module="CRM — Nouveau contact" />
</form>
@endsection
