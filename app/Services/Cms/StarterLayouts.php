<?php

namespace App\Services\Cms;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StarterLayouts
{
    public const NAMES = ['table', 'form', 'sidebar', 'topbar', 'topbar + sidebar'];

    public function __construct(private BootstrapComponents $components, private LayoutEditor $editor) {}

    public function install(User $user): array
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $catalog = $this->editor->catalog($user);
            $report = [];
            $components = [];
            foreach (self::NAMES as $name) {
                $entry = collect($catalog['layouts'])->first(fn ($item) => $item['name'] === $name || ($name === 'topbar + sidebar' && $item['name'] === 'tobbar + sidebar'));
                $layout = $entry ? $this->editor->find($user, $entry['id']) : null;
                if ($layout && ($layout->revision > 1 || $layout->document != DocumentData::empty())) {
                    $report[$name] = 'Ponechaný existujúci obsah';

                    continue;
                }
                if (! $components) {
                    foreach (array_keys(BootstrapComponents::DEFINITIONS) as $key) {
                        $component = $this->components->install($user, $key);
                        abort_if($component->trashed() || ($component->settings['placeholder'] ?? false), 422, 'Komponent '.$key.' nie je dostupný.');
                        $components[$key] = $component->id;
                    }
                }
                $document = $this->document($name, $components);
                $this->editor->save($user, ['name' => $name, 'revision' => $layout?->revision ?? 1, 'document' => $document], $layout?->id);
                $report[$name] = 'Vytvorený layout';
            }

            return $report;
        });
    }

    private function node(string $type, array $attributes = [], array $children = []): array
    {
        return ['id' => (string) Str::uuid(), 'type' => $type, 'attributes' => $attributes, 'children' => $children];
    }

    private function reference(string $id, array $children = [], array $attributes = []): array
    {
        return $this->node('cms-component', array_replace(['component_id' => $id, 'content_target' => 'root', 'full_width' => false], $attributes), $children);
    }

    private function document(string $name, array $components): array
    {
        $content = $this->reference($components['content']);
        $grid = $this->node('div', ['class' => 'row g-3 align-items-stretch', 'editor_label' => 'Bočný panel a hlavný obsah'], [
            $this->node('div', ['class' => 'col-12 col-sm-4 col-lg-3', 'editor_label' => 'Stĺpec bočného panelu'], [$this->reference($components['sidebar'])]),
            $this->node('div', ['class' => 'col-12 col-sm-8 col-lg-9', 'editor_label' => 'Stĺpec hlavného obsahu'], [$content]),
        ]);
        $children = match ($name) {
            'table' => [$this->reference($components['table'])],
            'form' => [$this->reference($components['form'])],
            'sidebar' => [$grid],
            'topbar' => [$this->reference($components['navbar']), $this->node('div', ['class' => 'mt-3', 'editor_label' => 'Hlavný obsah'], [$content])],
            'topbar + sidebar' => [$this->reference($components['navbar']), $this->node('div', ['class' => 'mt-3', 'editor_label' => 'Telo stránky'], [$grid])],
        };
        $document = DocumentData::empty();
        $document['nodes'][] = $this->reference($components['container'], $children, ['full_width' => true]);

        return DocumentData::validate($document);
    }
}
