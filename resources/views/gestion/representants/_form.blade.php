{{-- _form.blade.php — fiche SAGE « Représentant : Création complète », inclus par create et edit --}}
@php
    $r     = $representant ?? null;
    $lbl   = 'block text-[11px] font-bold text-gray-700 mb-1';
    $inp   = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $inpR  = 'w-full h-8 px-2 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white text-right font-mono tabular-nums focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $lk    = 'appearance-none w-full h-8 py-0 pl-2 pr-8 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $chk   = 'w-[15px] h-[15px] border-[1.5px] border-gray-400 rounded-[2px] text-emerald-600 focus:ring-1 focus:ring-emerald-400';
    $secH  = 'px-4 py-1.5 border-b border-gray-200 bg-[#eef5f0] text-[13px] font-bold text-emerald-900';
    $caret = '<span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none text-[11px]">&#9662;</span>';
@endphp

<div x-data="{ tab: 'general' }" class="space-y-3">

    <div class="bg-white border border-gray-300 rounded-[4px]">
        {{-- Bandeau SAGE --}}
        <div class="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
            <h2 class="text-[15px] font-bold text-gray-900">
                Représentant : Création complète
                @if($r && $r->exists)<span class="font-mono text-emerald-700 ml-1">{{ $r->code ?: $r->name }}</span>@endif
            </h2>
            <div class="flex items-center gap-2">
                <button type="submit" class="text-[13px] font-semibold text-emerald-700 border border-emerald-500 bg-white hover:bg-emerald-50 px-4 py-1.5 rounded-[4px] transition-colors">Enregistrer</button>
                <a href="{{ route('representants.index') }}" class="text-[13px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-4 py-1.5 rounded-[4px] transition-colors">Abandon</a>
            </div>
        </div>

        {{-- Onglets --}}
        <nav class="flex items-stretch border-b border-gray-200 px-2 overflow-x-auto">
            @foreach(['general'=>'Général','commission'=>'Commission'] as $tk => $tl)
            <button type="button" @click="tab = '{{ $tk }}'"
                    class="px-3 py-1.5 text-[13px] font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tab === '{{ $tk }}' ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-gray-500 hover:text-gray-700'">{{ $tl }}</button>
            @endforeach
        </nav>

        {{-- ═══════════ GÉNÉRAL ═══════════ --}}
        <div x-show="tab === 'general'" class="p-4 space-y-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Identification</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                    <div class="sm:col-span-2">
                        <label class="{{ $lbl }}">Code</label>
                        <input type="text" name="code" value="{{ old('code', $r->code ?? '') }}" maxlength="20"
                               class="{{ $inp }} font-mono uppercase" placeholder="REP-01">
                        @error('code')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-7">
                        <label class="{{ $lbl }}">Nom <span class="text-red-600">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $r->name ?? '') }}" required maxlength="100"
                               class="{{ $inp }} font-medium">
                        @error('name')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-3 flex items-end pb-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="{{ $chk }}"
                                   {{ old('is_active', $r->is_active ?? true) ? 'checked' : '' }}>
                            <span class="text-[12.5px] font-semibold text-gray-700">Représentant actif</span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Contact</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                    <div class="sm:col-span-6">
                        <label class="{{ $lbl }}">Email</label>
                        <input type="email" name="email" value="{{ old('email', $r->email ?? '') }}" maxlength="100" class="{{ $inp }}">
                        @error('email')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-6">
                        <label class="{{ $lbl }}">Téléphone</label>
                        <input type="text" name="phone" value="{{ old('phone', $r->phone ?? '') }}" maxlength="30" class="{{ $inp }}" placeholder="+226 70 00 00 00">
                        @error('phone')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
        </div>

        {{-- ═══════════ COMMISSION ═══════════ --}}
        <div x-show="tab === 'commission'" x-cloak class="p-4 space-y-4">
            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Commissionnement</div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-12 gap-x-4 gap-y-3">
                    <div class="sm:col-span-3">
                        <label class="{{ $lbl }}">Taux de commission (%) <span class="text-red-600">*</span></label>
                        <input type="number" name="commission_rate" step="0.01" min="0" max="100" required
                               value="{{ old('commission_rate', $r->commission_rate ?? '0.00') }}"
                               class="{{ $inpR }}">
                        <p class="mt-1 text-[11px] text-gray-400">Calculé sur les encaissements confirmés uniquement.</p>
                        @error('commission_rate')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-5">
                        <label class="{{ $lbl }}">Compte utilisateur lié</label>
                        <div class="relative"><select name="user_id" class="{{ $lk }}">
                            <option value="">— Aucun —</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id', $r->user_id ?? '') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>{!! $caret !!}</div>
                        @error('user_id')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="border border-gray-200 rounded-[4px]">
                <div class="{{ $secH }}">Notes</div>
                <div class="p-4">
                    <textarea name="notes" rows="3"
                              class="w-full px-2 py-1.5 border border-[#c3d3c9] rounded-[3px] text-[13px] focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400 resize-none">{{ old('notes', $r->notes ?? '') }}</textarea>
                </div>
            </section>
        </div>
    </div>

</div>
