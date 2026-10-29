<?php

namespace App\Services\Cms;

use App\Models\Cms\Component;
use App\Models\Cms\ComponentEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BootstrapComponents
{
    public const DEFINITIONS = [
        'container' => ['file' => 'bootstrap/zakladne-rozlozenie/bootstrap-container.json', 'category' => 'Základné rozloženie', 'category_position' => 0, 'position' => 0],
        'content' => ['file' => 'bootstrap/zakladne-rozlozenie/bootstrap-content.json', 'category' => 'Základné rozloženie', 'category_position' => 0, 'position' => 1],
        'navbar' => ['file' => 'bootstrap/navigacia-a-rozvrhnutie/bootstrap-navbar.json', 'category' => 'Navigácia a Rozvrhnutie', 'category_position' => 2, 'position' => 0, 'catalog_id' => 'bs-navbar'],
        'sidebar' => ['file' => 'bootstrap/navigacia-a-rozvrhnutie/bootstrap-sidebar.json', 'category' => 'Navigácia a Rozvrhnutie', 'category_position' => 2, 'position' => 4],
        'table' => ['file' => 'bootstrap/zobrazenie-dat-a-prvky/bootstrap-table.json', 'category' => 'Zobrazenie dát a Prvky', 'category_position' => 3, 'position' => 1, 'catalog_id' => 'bs-table'],
        'form' => ['file' => 'bootstrap/formulare/bootstrap-form.json', 'category' => 'Formuláre', 'category_position' => 4, 'position' => 0],
    ];

    public function __construct(private ComponentLibrary $library) {}

    public function install(User $user, string $key): Component
    {
        $definition = self::DEFINITIONS[$key] ?? abort(422, 'Neznáma Bootstrap predloha.');

        return DB::transaction(function () use ($user, $definition) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Component::withTrashed()->where('user_id', $user->id)->where('source_key', $definition['file'])->first();
            if ($existing) {
                return $existing;
            }
            $shared = isset($definition['catalog_id']) ? ComponentEntry::whereNull('user_id')->where('tab', 'bootstrap')->where('source_payload->id', $definition['catalog_id'])->first() : null;
            $personal = $shared ? ComponentEntry::with('component')->where('user_id', $user->id)->where('entry_key', $shared->entry_key)->first() : null;
            if ($personal?->component && ! ($personal->component->settings['placeholder'] ?? false)) {
                return $personal->component;
            }
            $source = json_decode(file_get_contents(config_path('ui/components/'.$definition['file'])), true, 512, JSON_THROW_ON_ERROR);
            $component = $this->library->saveImport($user, [
                'name' => $source['name'], 'slug' => $source['slug'], 'tab' => 'bootstrap', 'category' => $definition['category'],
                'source_payload' => $source, 'document_css' => $source['css'], 'document_js' => $source['js'],
            ], $source['objects'], $definition['file']);
            $entry = ComponentEntry::where('user_id', $user->id)->where('component_id', $component->id)->firstOrFail();
            if ($shared && $personal && $personal->id !== $entry->id) {
                $entry->delete();
                $entry = $personal;
                $entry->component_id = $component->id;
            }
            $entry->fill(['category_position' => $definition['category_position'], 'position' => $definition['position']]);
            if ($shared) {
                $entry->entry_key = $shared->entry_key;
                $entry->source_payload = array_replace($shared->source_payload, ['name' => $component->name, 'path' => $definition['file']]);
            }
            $entry->save();

            return $component;
        });
    }
}
