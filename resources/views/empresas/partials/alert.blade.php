@if (session('success'))
    <div class="mb-4 flex items-start gap-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
        <svg class="w-5 h-5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>{{ session('success') }}</span>
    </div>
@endif
@if ($errors->any())
    {{-- Datos que faltan o hay que corregir: aviso amable, no de error --}}
    <div class="mb-4 flex items-start gap-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
        <svg class="w-5 h-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <p class="font-semibold">{{ $errors->count() === 1 ? 'Revisa este dato antes de guardar:' : 'Revisa estos datos antes de guardar:' }}</p>
            <ul class="mt-1 space-y-0.5">
                @foreach ($errors->all() as $error)<li>• {{ $error }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif
