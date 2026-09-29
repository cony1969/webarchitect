@php
    $appLayoutName = config('ui.app.layout', 'sidebar');
    $fullPath = config_path("ui/layouts/{$appLayoutName}.json");
    
    echo "<!-- DEBUG:
    Zvolený layout z app.json: " . var_export($appLayoutName, true) . "
    Hľadaná cesta na servery: " . $fullPath . "
    Existuje ten súbor?: " . (file_exists($fullPath) ? 'ÁNO' : 'NIE') . "
    Aktuálny config ui.layout: " . var_export(config('ui.layout'), true) . "
    -->";
@endphp

<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('ui.app.appName', 'Web Architect') }} - Dashboard</title>
    
    <!-- Spoločné skripty pre celú aplikáciu -->
    <script defer src="https://jsdelivr.net"></script>
    <script src="https://unpkg.com"></script>

    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-color: #f4f5f8; }
    </style>
</head>
<body>

    <!-- Dynamické načítanie témy podľa app.json -->
    <x-dynamic-component :component="'layouts.themes.' . config('ui.app.layout', 'sidebar')">
        {{ $slot }}
    </x-dynamic-component>

</body>
</html>
