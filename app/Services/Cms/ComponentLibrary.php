<?php

namespace App\Services\Cms;

use App\Models\Cms\Component;
use App\Models\Cms\ComponentEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ComponentLibrary
{
    public function __construct(private DocumentStore $store) {}

    public function catalog(User $user, bool $forEditor = false): array
    {
        $entries = ComponentEntry::with('component')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))->orderBy('category_position')->orderBy('position')->get();
        $overrides = Component::where('user_id', $user->id)->whereNotNull('source_key')->get()->keyBy('source_key');
        $merged = [];
        foreach ($entries as $entry) {
            // Personal catalog entries replace matching shared entries without changing other users' views.
            if (! isset($merged[$entry->entry_key]) || $entry->user_id !== null) {
                $merged[$entry->entry_key] = $entry;
            }
        }
        $tree = [];
        foreach ($merged as $entry) {
            $component = $overrides[$entry->component?->source_key] ?? $entry->component;
            if (! $component || $component->trashed()) {
                continue;
            }
            $group = $tree[$entry->tab][$entry->category] ?? ['category' => $entry->category, 'items' => []];
            $item = $entry->source_payload;
            $item['name'] = $component->name;
            if ($forEditor) {
                $item['placeholder'] = (bool) ($component->settings['placeholder'] ?? false);
            }
            $group['items'][] = $item;
            $tree[$entry->tab][$entry->category] = $group;
        }
        $listed = $entries->pluck('component_id')->all();
        foreach (Component::where('user_id', $user->id)->whereNotIn('id', $listed)->whereNull('source_key')->orderBy('created_at')->get() as $component) {
            $tree['other']['Importované'] ??= ['category' => 'Importované', 'items' => []];
            $tree['other']['Importované']['items'][] = ['id' => 'component-'.$component->id, 'name' => $component->name, 'path' => 'cms/'.$component->id.'.json'];
        }
        foreach ($tree as &$groups) {
            $groups = array_values($groups);
        }

        return array_replace(['native' => [], 'bootstrap' => [], 'other' => []], $tree);
    }

    public function find(User $user, string $path): ?Component
    {
        $query = Component::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'));
        if (preg_match('#^cms/([a-f0-9-]{36})\.json$#i', $path, $m)) {
            return $query->find($m[1]);
        }

        return $query->where('source_key', $path)->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')->first();
    }

    public function legacy(Component $component): array
    {
        return array_merge($component->source_payload ?? [], ['name' => $component->name, 'slug' => $component->slug, 'tab' => $component->settings['tab'] ?? null, 'objects' => DocumentData::toLegacy($component->document), 'css' => $component->document['css'], 'js' => $component->document['js']]);
    }

    public function editorData(Component $component): array
    {
        $entry = ComponentEntry::where('component_id', $component->id)->first();
        $path = $component->source_key ?: 'cms/'.$component->id.'.json';

        return [
            'id' => $component->id, 'revision' => $component->revision, 'name' => $component->name,
            'slug' => $component->slug, 'item_path' => $path, 'target_path' => trim(dirname($path), '/.'),
            'tab' => $component->settings['tab'] ?? $entry?->tab ?? 'other',
            'category' => $entry?->category ?? 'Importované', 'shared' => $component->user_id === null,
            'content' => json_encode($this->legacy($component), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    public function saveImport(User $user, array $data, array $objects, string $path): Component
    {
        return DB::transaction(function () use ($user, $data, $objects, $path) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $original = ! empty($data['component_id']) ? $this->store->query('components', $user)->whereKey($data['component_id'])->lockForUpdate()->firstOrFail() : null;
            if ($original && $original->source_key) {
                $path = $original->source_key;
            }
            $component = Component::where('user_id', $user->id)->where('source_key', $path)->lockForUpdate()->first();
            if ($original && $original->user_id !== null) {
                $component = $original;
            }
            if ($component) {
                abort_unless(isset($data['revision']) && $component->revision === (int) $data['revision'], 409, 'Komponent medzitým zmenil iný editor. Načítajte ho znovu.');
            } else {
                $component = new Component(['user_id' => $user->id, 'owner_key' => 'user:'.$user->id, 'source_key' => $path, 'scope_key' => '', 'kind' => 'component', 'settings' => $original?->settings ?? [], 'source_payload' => $original?->source_payload]);
            }
            $component->source_key ??= $path;
            $tab = $data['tab'] ?? 'other';
            $category = $data['category'] ?? 'Importované';
            $document = DocumentData::fromLegacy($objects);
            $document['css'] = $data['document_css'] ?? '';
            $document['js'] = $data['document_js'] ?? '';
            $component->fill(['name' => $data['name'], 'slug' => Str::slug($data['slug']), 'document' => DocumentData::validate($document), 'source_payload' => array_replace($component->source_payload ?? [], $data['source_payload'] ?? []), 'settings' => array_replace($component->settings ?? [], ['tab' => $tab, 'placeholder' => false])]);
            $this->store->save($component, $user->id);
            $sharedEntry = ComponentEntry::whereHas('component', fn ($q) => $q->where('source_key', $path))->where('tab', $tab)->where('category', $category)->first();
            $item = $sharedEntry?->source_payload ?? ['id' => 'import-'.$component->slug, 'path' => $path];
            $item['name'] = $component->name;
            $item['path'] = $path;
            ComponentEntry::updateOrCreate(['owner_key' => 'user:'.$user->id, 'entry_key' => $sharedEntry?->entry_key ?? hash('sha256', $tab.'|'.$category.'|'.$path)], [
                'user_id' => $user->id, 'component_id' => $component->id, 'tab' => $tab, 'category' => $category,
                'category_position' => $sharedEntry?->category_position ?? 100, 'position' => $sharedEntry?->position ?? 100, 'source_payload' => $item,
            ]);

            return $component;
        });
    }
}
