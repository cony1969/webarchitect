@php
    // Načítanie všetkých nastavení používateľa z tabuľky user_settings
    $userSettings = \DB::table('user_settings')
        ->where('user_id', auth()->id())
        ->pluck('value', 'name')
        ->toArray();

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

<!-- Použijeme čisté HTML namiesto x-guest-layout -->
<!DOCTYPE html>
<html lang="sk">
<head>
    <x-ui.favicon />
    <meta charset="UTF-8">
    <!-- Načítanie rovnakého CSS (napr. Tailwind/Bootstrap), aké používa celá aplikácia -->
    @vite(['resources/css/app.css', 'resources/js/app.js']) 
    <script src="https://unpkg.com"></script>
    <style>
        /* Skrytie scrollbaru, ak nie je potrebný */
        body { margin: 0; padding: 0; background: transparent !important; }
    </style>
    <x-ui.theme />
</head>
<body>
    <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Figtree', sans-serif; box-sizing: border-box; padding: 20px;">
        <div style="background: var(--wa-surface-base); padding: 40px; border-radius: 10px; border: 1px solid var(--wa-border); box-shadow: 0 12px 32px rgba(0,0,0,0.5); max-width: 450px; width: 100%; box-sizing: border-box;">
            
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

                <x-ui.sidebar-reset />
            </div>
        </div>
    </div>
</body>
</html>
