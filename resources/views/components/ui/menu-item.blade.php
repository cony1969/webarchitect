@props(['item'])

<div {{ $attributes->merge(['style' => 'padding: 8px 12px; margin-bottom: 4px; border-radius: 4px; display: flex; align-items: center; cursor: pointer;']) }}>
    <!-- Zatiaľ len surový výpis, kým nespojazdníme ostatné mikro-atómy -->
    <span style="font-size: 14px;">{{ $item['title'] ?? 'Bez názvu' }}</span>
</div>
