@props(['app' => [], 'layout' => []])

@php
    // Načítanie dát z configu, kam ich uložil Provider
    $components = config('ui.components', []);

    $userSettings = [];
    if (auth()->check()) {
        $userSettings = \DB::table('user_settings')
            ->where('user_id', auth()->id())
            ->whereIn('name', ['sidebar_active_tab'])
            ->pluck('value', 'name')
            ->toArray();
    }

    // Načítanie uloženej záložky primárne z user_settings, fallback session
    $savedTab = (string) ($userSettings['sidebar_active_tab'] ?? session('sidebar_active_tab', 'native'));
@endphp

<div class="app-container">
    <x-ui.topbar :app="$app" :top-menu="$topMenu ?? []" />

    <div class="workspace-wrapper">
        <x-ui.sidebar-left :layout="$layout" :components="$components" :saved-tab="$savedTab" />

        <main class="main-content" style="padding: 0;">
            <x-ui.canvas-tabs>
                {{ $slot }}
            </x-ui.canvas-tabs>
        </main>

        <x-ui.sidebar-right :layout="$layout" />
    </div>
</div>
