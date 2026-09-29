@php
    // Načítanie všetkých nastavení používateľa z tabuľky user_settings
    $userSettings = \DB::table('user_settings')
        ->where('user_id', auth()->id())
        ->pluck('value', 'name')
        ->toArray();

    // Vytiahnutie rozmerov s fallbackom na predvolené hodnoty
    $blockTopHeight = $userSettings['sidebar_block_top'] ?? 300;
    $blockMiddleHeight = $userSettings['sidebar_block_middle'] ?? 300;

    $profileConfig = [
        'action' => route('profile.update'),
        'target' => '#profile-form-container',
        'swap' => 'innerHTML'
    ];

    $profileInputs = [
        [
            'id' => 'profile_name',
            'name' => 'name',
            'type' => 'text',
            'label' => 'Meno / Prezývka',
            'value' => auth()->user()->name,
            'required' => true,
        ],
        [
            'id' => 'profile_email',
            'name' => 'email',
            'type' => 'email',
            'label' => 'E-mailová adresa',
            'value' => auth()->user()->email,
            'required' => true,
        ]
    ];
@endphp

<script src="https://unpkg.com/htmx.org@1.9.2"></script>

<x-guest-layout>
    <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #151922; display: flex; align-items: center; justify-content: center; z-index: 9999; font-family: 'Figtree', sans-serif; box-sizing: border-box;">
        <div style="background: #1e222d; padding: 40px; border-radius: 10px; border: 1px solid #2d3446; box-shadow: 0 12px 32px rgba(0,0,0,0.5); max-width: 450px; width: 100%; box-sizing: border-box;">
            
            <x-form-header title="Nastavenia profilu" subtitle="Aktualizuj svoje osobné údaje v CMS" />

            <div id="profile-form-container" style="width: 100%;">
                @if (session('status') === 'profile-updated')
                    <div style="color: #10b981; font-size: 14px; margin-bottom: 16px; font-weight: 500;">
                        Zmeny boli úspešne uložené.
                    </div>
                @endif
                @if (session('status') === 'layout-reset')
                    <div style="color: #10b981; font-size: 14px; margin-bottom: 16px; font-weight: 500;">
                        Rozloženie panelov bolo obnovené na predvolené hodnoty.
                    </div>
                @endif
                <x-htmx-form :config="$profileConfig" :inputs="$profileInputs">
                    <div style="margin-top: 25px;">
                        <x-form-button>Uložiť zmeny</x-form-button>
                    </div>
                </x-htmx-form>

								<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #2d3446;">
    <h4 style="color: #ffffff; font-size: 14px; font-weight: 600; margin: 0 0 5px 0;">Rozloženie prostredia</h4>
    <p style="color: #7885a0; font-size: 12px; margin: 0 0 15px 0;">Ak máš rozhádzané šírky a výšky bočných panelov, môžeš ich vrátiť na pôvodné hodnoty.</p>
    
    <button hx-post="{{ route('profile.reset-layout') }}"
            hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'
            hx-on::after-request="window.location.reload();"
            style="padding: 10px 16px; background-color: #2a2e3d; color: #ff4d4d; border: 1px solid #2d3446; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: background 0.2s;"
            onmouseover="this.style.background='#343a4c'" 
            onmouseout="this.style.background='#2a2e3d'">
        Obnoviť predvolené rozmery panelov
    </button>
</div>

            </div>
            
            <x-form-link :href="route('dashboard')" center muted>&larr; Späť na prehľad (Dashboard)</x-form-link>
        </div>
    </div>
</x-guest-layout>
