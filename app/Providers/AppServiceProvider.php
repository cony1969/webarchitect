<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $path = config_path('ui/app.json');

        if (file_exists($path)) {
            $config = json_decode(file_get_contents($path), true);
            
            // Uložíme dáta do runtime konfigurácie Laravelu pod kľúč 'ui.app'
            config(['ui.app' => $config]);
        }
    }
}
