@props(['app' => [], 'topMenu' => []])

<header class="main-header">
    <x-ui.topbar.brand :name="$app['appName'] ?? 'Web Architect'" />
    
    <x-ui.topbar.menu :items="$topMenu ?? []" />
    
    <x-ui.topbar.profile />
</header>
