<?php

namespace App\Console\Commands;

use App\Models\Cms\Component;
use App\Models\Cms\ComponentEntry;
use App\Models\User;
use App\Services\Cms\ComponentLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallBootstrapComponent extends Command
{
    protected $signature = 'cms:install-bootstrap {component=container : Identifikátor predlohy} {--user= : E-mail vlastníka knižnice}';

    protected $description = 'Pridá hotový Bootstrap komponent do osobnej knižnice bez prepísania existujúcich úprav';

    private const DEFINITIONS = [
        'container' => ['file' => 'bootstrap/zakladne-rozlozenie/bootstrap-container.json', 'category' => 'Základné rozloženie', 'category_position' => 0, 'position' => 0],
    ];

    public function handle(ComponentLibrary $library): int
    {
        $definition = self::DEFINITIONS[$this->argument('component')] ?? null;
        $email = strtolower(trim((string) $this->option('user')));
        $user = $email !== '' ? User::whereRaw('LOWER(email) = ?', [$email])->first() : null;
        if (! $definition || ! $user) {
            $this->error('Zadajte známu predlohu a existujúci účet cez --user.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($library, $definition, $user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Component::withTrashed()->where('user_id', $user->id)->where('source_key', $definition['file'])->first();
            if ($existing) {
                $this->info('Komponent už existuje. Jeho obsah a revízia zostali zachované.');

                return self::SUCCESS;
            }
            $source = json_decode(file_get_contents(config_path('ui/components/'.$definition['file'])), true, 512, JSON_THROW_ON_ERROR);
            $component = $library->saveImport($user, [
                'name' => $source['name'], 'slug' => $source['slug'], 'tab' => 'bootstrap', 'category' => $definition['category'],
                'source_payload' => $source, 'document_css' => $source['css'], 'document_js' => $source['js'],
            ], $source['objects'], $definition['file']);
            ComponentEntry::where('user_id', $user->id)->where('component_id', $component->id)->update([
                'category_position' => $definition['category_position'], 'position' => $definition['position'],
            ]);
            $this->info('Vytvorený komponent '.$component->name.' pre '.$user->email.'.');

            return self::SUCCESS;
        });
    }
}
