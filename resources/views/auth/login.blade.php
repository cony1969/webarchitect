@php
    $formConfig = [
        'action' => route('login'),
        'target' => '#login-form-container',
        'swap' => 'innerHTML',
        'redirect_script' => "window.location.href='" . route('dashboard') . "';"
    ];

    $formInputs = [
        [
            'id' => 'email',
            'name' => 'email',
            'type' => 'email',
            'label' => 'E-mailová adresa',
            'value' => old('email'),
            'required' => true,
            'autofocus' => true,
            'autocomplete' => 'username',
        ],
        [
            'id' => 'password',
            'name' => 'password',
            'type' => 'password',
            'label' => 'Heslo',
            'required' => true,
            'autocomplete' => 'current-password',
        ]
    ];
@endphp

<script src="https://unpkg.com/htmx.org@1.9.2"></script>

<x-guest-layout>
    <div style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: var(--wa-surface-deep); display: flex; align-items: center; justify-content: center; z-index: 9999; font-family: 'Figtree', sans-serif;">
        <div style="background: var(--wa-surface-base); padding: 40px; border-radius: 10px; border: 1px solid var(--wa-border); box-shadow: 0 12px 32px rgba(0,0,0,0.5); max-width: 400px; width: 100%; box-sizing: border-box;">
            
            <x-form-header title="Web Architect" subtitle="Prihlásenie do CMS systému" />

            <div id="login-form-container" style="width: 100%;">
                @if (session('status'))
                    <div style="color: #3b82f6; font-size: 14px; margin-bottom: 16px;">
                        {{ session('status') }}
                    </div>
                @endif

								<x-htmx-form :config="$formConfig" :inputs="$formInputs">
									

                    <!-- Prvky pod vygenerovanými inputmi zostávajú v slote -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <label for="remember_me" style="display: flex; align-items: center; color: #7885a0; font-size: 13px; cursor: pointer; user-select: none;">
                            <input id="remember_me" type="checkbox" name="remember" style="margin-right: 8px; cursor: pointer; accent-color: #3b82f6;">
                            <span>Zapamätať si ma</span>
                        </label>

                        @if (Route::has('password.request'))
                            <x-form-link :href="route('password.request')">
                                Zabudli ste heslo?
                            </x-form-link>
                        @endif
                    </div>

                    <div>
                        <x-form-button>Prihlásiť sa</x-form-button>
                    </div>
                </x-htmx-form>
            </div>
            
            <x-form-link :href="url('/')" center muted>&larr; Späť na hlavnú stránku</x-form-link>
        </div>
    </div>
</x-guest-layout>
