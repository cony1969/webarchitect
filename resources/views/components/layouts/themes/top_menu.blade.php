@props(['app' => [], 'layout' => []])

<header style="background: #ffffff; padding: 15px 20px; display: flex; justify-content: space-between; border-bottom: 1px solid #e9e9ef; align-items: center;">
    <div style="font-weight: bold;">{{ $app['appName'] ?? 'Neznámy názov' }}</div>
    <div>Používateľ: {{ $app['appAuthor'] ?? 'Neznámy autor' }}</div>
</header>

<nav style="background: #2a3042; color: white; padding: 12px 20px; display: flex; gap: 20px; align-items: center;">
    <a href="#" style="color: white; text-decoration: none; font-weight: bold;">🏠 Dashboard</a>
    <a href="#" style="color: #a6b0cf; text-decoration: none;">📦 Produkty</a>
    <span style="margin-left: auto; font-size: 12px; color: #6f768e;">Licencia: {{ $app['appLicense'] ?? 'Neznáma' }} | Téma: {{ $layout['theme'] ?? 'Základná' }}</span>
</nav>

<main style="padding: 25px;">
    {{ $slot }}
</main>
