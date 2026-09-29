@props(['app' => [], 'layout' => []])

<header style="background: #ffffff; padding: 15px 20px; display: flex; justify-content: space-between; border-bottom: 1px solid #e9e9ef; align-items: center;">
    <button @click="sidebarCollapsed = !sidebarCollapsed" style="background: #3bc0c3; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">☰ Menu</button>
    <div style="font-weight: bold;">{{ $app['appName'] ?? 'Neznámy názov' }}</div>
    <div>Používateľ: {{ $app['appAuthor'] ?? 'Neznámy autor' }}</div>
</header>

<div style="display: flex; min-height: calc(100vh - 52px);" x-data="{ sidebarCollapsed: {{ !empty($layout['sidebar_collapsed']) ? 'true' : 'false' }} }">
    <aside x-show="!sidebarCollapsed" x-transition style="width: 240px; background: #2a3042; color: #a6b0cf; padding: 20px; transition: all 0.3s;">
        <h3 style="color: white; margin-top: 0;">Upcube Bočné Menu</h3>
        <p style="font-size: 13px;">Licencia: {{ $app['appLicense'] ?? 'Neznáma' }}</p>
        <p style="font-size: 12px; color: #6f768e;">Téma: {{ $layout['theme'] ?? 'Základná' }}</p>
        <hr style="border-color: #32394e;">
        <div>[ Odkazy menu prídu sem ]</div>
    </aside>

    <main style="flex: 1; padding: 25px;">
        {{ $slot }}
    </main>
</div>
