<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class UiConfigurationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Použitie 100% funkčnej a overenej absolútnej cesty z tvojho servera
        $baseDir = '/data/d/4/d4f1af97-73d7-4842-9f8a-d4679c7e0428/pokerclub.sk/sub/war/config/ui';

        // 1. Načítanie globálnej aplikácie
        $appPath = "{$baseDir}/app.json";
        
        if (file_exists($appPath)) {
            $appConfig = json_decode(file_get_contents($appPath), true);
            config(['ui.app' => $appConfig]);

            // 2. Dynamické načítanie konfigurácie zvoleného layoutu
            $layoutName = $appConfig['layout'] ?? 'sidebar';
            $layoutPath = "{$baseDir}/layouts/{$layoutName}.json";

            if (file_exists($layoutPath)) {
                config(['ui.layout' => json_decode(file_get_contents($layoutPath), true)]);
            } else {
                Log::error("Web Architect: Súbor layoutu nebol nájdený na ceste: {$layoutPath}");
            }
        } else {
            Log::error("Web Architect: Globálny súbor app.json nebol nájdený na ceste: {$appPath}");
        }

        // 3. Načítanie components.json
        // 1. DYNAMICKÉ NAČÍTANIE KOMPONENTOV Z CONFIG SÚBORU
        $componentsPath = "{$baseDir}/content/components.json";
        if (file_exists($componentsPath)) {
            $componentsContent = file_get_contents($componentsPath);
            config(['ui.components' => json_decode($componentsContent, true) ?: []]);
        } else {
            Log::error("Web Architect: Súbor components.json nebol nájdený na ceste: {$componentsPath}");
            config(['ui.components' => []]);
        }

        // 4. Načítanie projektov z projects.json
        $projectsPath = "{$baseDir}/content/projects.json";
        if (file_exists($projectsPath)) {
            $projectsContent = file_get_contents($projectsPath);
            config(['ui.projects' => json_decode($projectsContent, true) ?: []]);
        } else {
            config(['ui.projects' => []]);
        }
    }
}
