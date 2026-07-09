@extends('layouts.erp')
@section('title', 'Portail Employé')
@section('content')
<div class="max-w-lg mx-auto text-center py-16">
    <div class="w-20 h-20 mx-auto mb-6 bg-amber-100 rounded-full flex items-center justify-center">
        <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
    </div>
    <h2 class="text-[17px] font-bold text-gray-900 mb-2">Aucun profil employé associé</h2>
    <p class="text-gray-500 text-sm">
        Votre compte utilisateur n'est pas encore lié à un dossier employé.
        Contactez votre service RH pour faire la liaison.
    </p>
</div>
@endsection
