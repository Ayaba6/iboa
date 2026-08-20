{{--
    <x-validation-errors />                 → affiche les erreurs du bag "default"
    <x-validation-errors bag="update" />    → affiche un bag nommé

    Affiche un panneau d'erreurs cohérent avec la bannière du layout.
    À utiliser dans les vues ayant un formulaire long où l'utilisateur
    peut scroller loin de la bannière globale.
--}}
@props(['bag' => 'default'])

@php $errors = $errors->getBag($bag); @endphp

@if($errors->any())
<div class="rounded-[4px] border border-red-200 bg-red-50 text-[13px] mb-4 overflow-hidden" role="alert">
    <div class="flex items-start gap-3 p-4">
        <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="flex-1">
            <p class="font-semibold text-red-800 mb-1">
                {{ $errors->count() === 1 ? '1 erreur à corriger' : $errors->count().' erreurs à corriger' }}
            </p>
            <ul class="space-y-0.5 text-red-700">
                @foreach($errors->all() as $error)
                    <li class="flex items-start gap-1.5">
                        <svg class="w-3 h-3 mt-0.5 flex-shrink-0 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        {{ $error }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="h-0.5 bg-red-200"></div>
</div>
@endif
