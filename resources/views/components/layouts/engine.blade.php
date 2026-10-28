@php
    $baseDir = config_path('ui');
    
    $uiApp = file_exists("{$baseDir}/app.json") ? json_decode(file_get_contents("{$baseDir}/app.json"), true) : [];
    $layoutName = $uiApp['layout'] ?? 'sidebar';
    $uiLayout = file_exists("{$baseDir}/layouts/{$layoutName}.json") ? json_decode(file_get_contents("{$baseDir}/layouts/{$layoutName}.json"), true) : [];
    
    $menuPath = "{$baseDir}/menu.json";
    $uiTopMenu = file_exists($menuPath) ? json_decode(file_get_contents($menuPath), true) : [];

    // Načítanie užívateľských nastavení z DB pre prípadný fallback alebo kontrolu na ploche
    $userSettings = [];
    if (auth()->check()) {
        $userSettings = \DB::table('user_settings')
            ->where('user_id', auth()->id())
            ->pluck('value', 'name')
            ->toArray();
    }

    view()->share('app', $uiApp);
    view()->share('layout', $uiLayout);
    view()->share('topMenu', $uiTopMenu['menu_items'] ?? []);
@endphp
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $uiApp['appName'] ?? 'Web Architect' }} - Engine</title>
    <x-ui.favicon />
    
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <script>window.waProjectEditorConfig = @js(array_merge(config('domain-editor'), ['csrf' => csrf_token()]));</script>
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <script defer src="{{ asset('assets/libs/jquery/jquery.min.js') }}"></script>
    <script defer src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
    <script defer src="{{ asset('js/project-editor.js') }}?v=20261028-import1"></script>
    <script defer src="{{ asset('js/site-import.js') }}?v=20261028-import1"></script>
    <!-- Stabilné, oficiálne načítanie AlpineJS a HTMX -->
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
    <script src="{{ asset('js/htmx.min.js') }}"></script>    
    <!-- Bootstrap 5 CSS & JS -->
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>

    <x-ui.styles />
    <x-ui.theme />
    <link rel="stylesheet" href="{{ asset('css/project-editor.css') }}?v=20261028-import1">
</head>
<body>

    {{-- Globálna príprava dát pre asynchrónny Blade / HTMX komponent profilu --}}
    @if(auth()->check())
        <div id="html-profile-tab-template" style="display: none;">
            <div style="background: var(--wa-surface-base); padding: 30px; border-radius: 8px; border: 1px solid var(--wa-border); max-width: 500px; width: 100%; box-sizing: border-box; margin: 20px auto; font-family: 'Figtree', sans-serif;">
                <div style="text-align: center; margin-bottom: 25px;">
                    <h2 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700;">Nastavenia profilu</h2>
                    <p style="color: #7885a0; margin: 5px 0 0 0; font-size: 13px;">Aktualizuj svoje osobné údaje v CMS</p>
                </div>

                <div id="profile-form-container">
                    <form hx-post="{{ route('profile.update') }}" 
                          hx-target="#profile-form-container" 
                          hx-swap="innerHTML"
                          hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
                        @csrf

                        <!-- Meno -->
                        <div style="margin-bottom: 18px;">
                            <label style="color: #a4b1cd; font-size: 13px; margin-bottom: 6px; display: block; font-weight: 500;">Meno / Prezývka</label>
                            <input type="text" name="name" value="{{ auth()->user()->name }}" required style="width: 100%; padding: 11px; background: var(--wa-surface-deep); border: 1px solid var(--wa-border); border-radius: 6px; color: #ffffff; font-size: 14px; box-sizing: border-box; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='var(--wa-border)'" />
                        </div>

                        <!-- Email -->
                        <div style="margin-bottom: 22px;">
                            <label style="color: #a4b1cd; font-size: 13px; margin-bottom: 6px; display: block; font-weight: 500;">E-mailová adresa</label>
                            <input type="email" name="email" value="{{ auth()->user()->email }}" required style="width: 100%; padding: 11px; background: var(--wa-surface-deep); border: 1px solid var(--wa-border); border-radius: 6px; color: #ffffff; font-size: 14px; box-sizing: border-box; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='var(--wa-border)'" />
                        </div>

                        <button type="submit" style="width: 100%; padding: 13px; background-color: #3b82f6; color: #ffffff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#2563eb'" onmouseout="this.style.backgroundColor='#3b82f6'">
                            Uložiť zmeny
                        </button>
                    </form>
                </div>

                <!-- Obnovenie predvolených sekcií v tomto prehliadači -->
                <x-ui.sidebar-reset />
            </div>
        </div>
    @endif

    <x-dynamic-component :component="'layouts.themes.' . ($uiApp['layout'] ?? 'sidebar')">
        {{ $slot }}
    </x-dynamic-component>
    
    <!-- GLOBÁLNY BOOTSTRAP MODAL CEZ EXISTUJÚCI KOMPONENT (NA KONCI BODY) -->
    <div id="engine-global-modal-wrapper" x-data="{
        show: false,
        title: '',
        value: '',
        callbackEvent: '',

        init() {
            window.addEventListener('open-engine-modal', (e) => {
                this.title = e.detail.title;
                this.callbackEvent = e.detail.callback;
                this.value = '';
                this.show = true;
                setTimeout(() => this.$refs.modalInput.focus(), 100);
            });
        },
        submit() {
            if (!this.value.trim()) return;
            window.dispatchEvent(new CustomEvent(this.callbackEvent, { detail: { value: this.value } }));
            this.show = false;
        }
    }">
        <x-ui.modal ::title="title" ::show="show" submit="submit()">
            <div class="form-group">
                <label style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #8a94ad; margin-bottom: 8px; display: inline-block; font-weight: 600;">Názov položky</label>
                <input type="text" class="form-control" style="background: var(--wa-surface-raised); border: 1px solid var(--wa-border-strong); color: #fff; font-size: 13px; padding: 8px 12px; border-radius: 4px;" x-model="value" @keydown.enter="submit()" x-ref="modalInput">
            </div>
        </x-ui.modal>
    </div>

</body>
</html>
