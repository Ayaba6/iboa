@extends('layouts.erp')
@section('title', 'CRM — Modifier contact')

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.contacts.index') }}" class="hover:text-gray-700">Contacts</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.contacts.show', $contact) }}" class="hover:text-gray-700">{{ $contact->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<form method="POST" action="{{ route('crm.contacts.update', $contact) }}" class="space-y-3">
    @csrf
    @method('PUT')

    <x-x3.title-bar title="Modifier — {{ $contact->name }}" subtitle="CRM — {{ $contact->typeLabel() }}">
        <x-x3.btn variant="primary" type="submit">Mettre à jour</x-x3.btn>
        <x-x3.btn :href="route('crm.contacts.show', $contact)">✕ Annuler</x-x3.btn>
    </x-x3.title-bar>

    @include('crm.contacts._form')

    <x-x3.footer module="CRM — Modification contact" />
</form>
@endsection
