@php
    $configData = [
        'action' => route('register'),
        'target' => '#register-form-container',
        'swap' => 'innerHTML',
        'redirect_script' => "window.location.href='" . route('dashboard') . "';"
    ];

    $inputsData = [
        [
            'id' => 'name',
            'type' => 'text',
            'label' => 'Meno / Prezývka',
            'value' => old('name'),
            'required' => true,
            'autofocus' => true,
            'autocomplete' => 'name',
        ],
        [
            'id' => 'email',
            'type' => 'email',
            'label' => 'E-mailová adresa',
            'value' => old('email'),
            'required' => true,
            'autocomplete' => 'username',
        ],
        [
            'id' => 'password',
            'type' => 'password',
            'label' => 'Heslo',
            'required' => true,
            'autocomplete' => 'new-password',
        ],
        [
            'id' => 'password_confirmation',
            'type' => 'password',
            'label' => 'Potvrdenie hesla',
            'required' => true,
            'autocomplete' => 'new-password',
        ]
    ];
@endphp

<script src="https://unpkg.com/htmx.org@1.9.2"></script>

<x-guest-layout>
    <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: var(--wa-surface-deep); display: flex; align-items: center; justify-content: center; z-index: 9999; font-family: 'Figtree', sans-serif; box-sizing: border-box;">
        <div style="background: var(--wa-surface-base); padding: 40px; border-radius: 10px; border: 1px solid var(--wa-border); box-shadow: 0 12px 32px rgba(0,0,0,0.5); max-width: 400px; width: 100%; box-sizing: border-box;">
            
            <x-form-header title="Web Architect" subtitle="Vytvorenie nového účtu" />

            <div id="register-form-container" style="width: 100%;">
                
                <x-htmx-form :config="$configData" :inputs="$inputsData">
                    
                    <div style="margin-top: 25px;">
                        <x-form-button>Zaregistrovať sa</x-form-button>
                    </div>

                </x-htmx-form>
            </div>
            
            <p style="text-align: center; margin-top: 20px; font-size: 13px; color: #7885a0;">
                Už máš účet? 
                <x-form-link :href="route('login')">Prihlás sa</x-form-link>
            </p>
        </div>
    </div>
</x-guest-layout>
