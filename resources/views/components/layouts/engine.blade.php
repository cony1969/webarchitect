@php
    $baseDir = '/data/d/4/d4f1af97-73d7-4842-9f8a-d4679c7e0428/pokerclub.sk/sub/war/config/ui';
    
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
    
    <!-- Transparentná sivá ikona nanútená priamo cez kód (obchádza cache aj SFTP nahrávanie) -->
    <link rel="icon" type="image/png" href="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAMAAABEpIrGAAAABGdBTUEAALGPC/xhBQAAAAFzUkdCAK7OHOkAAAAnUExURf///8HBwcXFxcbGxsfHx8jIyMnJycrKysvLy8zMzM3Nzc7Ozs/Pz0wS0pIAAAAMdFJOUwA3gInC09Xi6u3v9v3gXgMAAAHYSURBVDjLpZPZkoMgEEXHzbAWh0RAn///wwVbY6YyZSpXreSh6HPh9gGo1v6PAnB9AL6P8w6glA5gXGv9D8bBWhjAOOZgHMo4WA1HMI7t6ZqmXf07A1wD9mC1e8g9wD2A+WDuPWeBfWwD3AKeC+w9pM65gOfCfWDvIXWuOecC9pT7V9g9wDlM6gGeC96/ZPcAt3NuN8Z19SfZPcDn8vU9B9g7nDuMcV0uYPdA97Vd/Y17wDvgvFwNxtXf7h5yV3/fFex7wPu6fXmBfQ90T3tWwN6B7p9X7XvInWvgfV3N7XvIO9W8Z8w71bwnWb9jL+v3Sda36+bYp9m7Nfbe1X8x79vs7dr2v2b3SdawY9fullbdfzf7H7T7HmrPtnvNme6+adfeaddfed6f5b9b/Zu950Z67vWfeOePOnPeOdOeceeecee+Z9eeesfefe++f3vPne++Me+/Meceddfeedee+eedeee++eedecffeee9f3vPne++Me+/M+886be+Z86bcedecdfeedee+f+88be+cffeedecffeddeeeee3vPnfWfZP1J1veTdXPeS9Y9WfeSdc+Y86f3jHnPmHPOnHfO6XPe96Z9zp6T96Y579v6tG6OOWvO+7Y6b86eN+3Zem/OOXPeOadPed/W617W/6R57+3T9WkdczrnfU7m/K9vWp9v68fV+vG+VutzNfP6/gEXvA65e8+X9QAAAABJRU5ErkJggg==">

    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Stabilné, oficiálne načítanie AlpineJS a HTMX -->
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>
    <script src="{{ asset('js/htmx.min.js') }}"></script>    
    <!-- Bootstrap 5 CSS & JS -->
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>

    <x-ui.styles />
</head>
<body>

    {{-- Globálna príprava dát pre asynchrónny Blade / HTMX komponent profilu --}}
    @if(auth()->check())
        <div id="html-profile-tab-template" style="display: none;">
            <div style="background: #1e222d; padding: 30px; border-radius: 8px; border: 1px solid #2d3446; max-width: 500px; width: 100%; box-sizing: border-box; margin: 20px auto; font-family: 'Figtree', sans-serif;">
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
                            <input type="text" name="name" value="{{ auth()->user()->name }}" required style="width: 100%; padding: 11px; background: #151922; border: 1px solid #2d3446; border-radius: 6px; color: #ffffff; font-size: 14px; box-sizing: border-box; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#2d3446'" />
                        </div>

                        <!-- Email -->
                        <div style="margin-bottom: 22px;">
                            <label style="color: #a4b1cd; font-size: 13px; margin-bottom: 6px; display: block; font-weight: 500;">E-mailová adresa</label>
                            <input type="email" name="email" value="{{ auth()->user()->email }}" required style="width: 100%; padding: 11px; background: #151922; border: 1px solid #2d3446; border-radius: 6px; color: #ffffff; font-size: 14px; box-sizing: border-box; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#2d3446'" />
                        </div>

                        <button type="submit" style="width: 100%; padding: 13px; background-color: #3b82f6; color: #ffffff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#2563eb'" onmouseout="this.style.backgroundColor='#3b82f6'">
                            Uložiť zmeny
                        </button>
                    </form>
                </div>

                <!-- Sekcia pre Obnovu Rozmerov Rozhrania z user_settings -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #2d3446;">
                    <h4 style="color: #ffffff; font-size: 14px; font-weight: 600; margin: 0 0 5px 0;">Rozloženie prostredia</h4>
                    <p style="color: #7885a0; font-size: 12px; margin: 0 0 15px 0;">Ak máš rozhádzané šírky a výšky bočných panelov, môžeš ich vrátiť na pôvodné hodnoty.</p>
                    
                    <button hx-post="{{ route('profile.reset-layout') }}"
                            hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'
                            hx-on::after-request="window.location.reload();"
                            style="width: 100%; padding: 11px; background-color: #2a2e3d; color: #ff4d4d; border: 1px solid #2d3446; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: background 0.2s, border-color 0.2s;"
                            onmouseover="this.style.background='#343a4c'; this.style.borderColor='#ff4d4d';" 
                            onmouseout="this.style.background='#2a2e3d'; this.style.borderColor='#2d3446';">
                        Obnoviť predvolené rozmery panelov
                    </button>
                </div>
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
                <input type="text" class="form-control" style="background: #2a2e3d; border: 1px solid #4e566d; color: #fff; font-size: 13px; padding: 8px 12px; border-radius: 4px;" x-model="value" @keydown.enter="submit()" x-ref="modalInput">
            </div>
        </x-ui.modal>
    </div>

</body>
</html>
