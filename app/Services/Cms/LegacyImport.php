<?php

namespace App\Services\Cms;

use App\Models\Cms\Component;
use App\Models\Cms\ComponentEntry;
use App\Models\Cms\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyImport
{
    public function __construct(private ProjectTree $projects, private DocumentStore $store) {}

    public function run(string $path): array
    {
        $source = 'legacy-json-v1';
        if ($existing = DB::table('cms_imports')->where('source', $source)->first()) {
            return ['already_imported' => true] + json_decode($existing->manifest, true);
        }
        $read = function (string $file) use ($path) {
            $contents = file_get_contents($path.'/'.$file);
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new RuntimeException('Zdroj neobsahuje JSON pole: '.$file);
            }

            return $data;
        };
        $catalog = $read('content/components.json');
        $layouts = $read('content/layouts.json');
        $trees = $read('content/projects.json');

        return DB::transaction(function () use ($source, $path, $catalog, $layouts, $trees) {
            $manifest = ['projects' => 0, 'domains' => 0, 'pages' => 0, 'catalog_entries' => 0, 'component_definitions' => 0, 'layout_templates' => 0, 'source_hashes' => []];
            foreach (['content/components.json', 'content/layouts.json', 'content/projects.json'] as $file) {
                $manifest['source_hashes'][$file] = hash_file('sha256', $path.'/'.$file);
            }
            foreach ($trees as $key => $tree) {
                if (! preg_match('/^user_(\d+)$/', $key, $m) || ! is_array($tree)) {
                    throw new RuntimeException('Neplatný vlastník stromu projektov.');
                }
                $user = User::findOrFail((int) $m[1]);
                $this->projects->replace($user, $tree, null, true);
                $manifest['projects'] += count($tree);
                foreach ($tree as $project) {
                    foreach ($project['domains'] ?? [] as $domain) {
                        $manifest['domains']++;
                        $manifest['pages'] += count($domain['pages'] ?? []);
                    }
                }
            }
            $components = [];
            foreach ($catalog as $tab => $groups) {
                foreach ($groups as $groupPosition => $group) {
                    foreach ($group['items'] ?? [] as $position => $item) {
                        $relative = $item['path'] ?? null;
                        $definition = null;
                        if ($relative) {
                            $file = realpath($path.'/components/'.$relative);
                            $base = realpath($path.'/components');
                            if ($file && $base && str_starts_with($file, $base.DIRECTORY_SEPARATOR) && is_file($file)) {
                                $definition = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                                $manifest['source_hashes']['components/'.$relative] = hash_file('sha256', $file);
                            }
                        }
                        $sourceKey = $relative ?: 'catalog:'.$tab.':'.($item['id'] ?? hash('sha256', $item['name']));
                        if (! isset($components[$sourceKey])) {
                            $component = new Component([
                                'owner_key' => 'library', 'source_key' => $sourceKey, 'name' => $definition['name'] ?? $item['name'],
                                'slug' => $definition['slug'] ?? ((Str::slug($item['name']) ?: 'component').'-'.substr(hash('sha256', $sourceKey), 0, 8)),
                                'kind' => 'component', 'position' => $position, 'scope_key' => '',
                                'document' => $definition ? DocumentData::validate(array_replace(DocumentData::fromLegacy($definition['objects'] ?? []), ['css' => $definition['css'] ?? '', 'js' => $definition['js'] ?? ''])) : DocumentData::empty(),
                                'settings' => ['tab' => $tab, 'placeholder' => ! $definition], 'source_payload' => $definition,
                            ]);
                            $this->store->save($component, null);
                            $components[$sourceKey] = $component;
                            if ($definition) {
                                $manifest['component_definitions']++;
                            }
                        }
                        ComponentEntry::create([
                            'owner_key' => 'library', 'component_id' => $components[$sourceKey]->id, 'entry_key' => hash('sha256', $tab.'|'.$group['category'].'|'.($item['id'] ?? $position)),
                            'tab' => $tab, 'category' => $group['category'], 'category_position' => $groupPosition, 'position' => $position, 'source_payload' => $item,
                        ]);
                        $manifest['catalog_entries']++;
                    }
                }
            }
            foreach ($layouts as $position => $layout) {
                $section = new Section(['owner_key' => 'library', 'legacy_id' => $layout['id'], 'name' => $layout['name'], 'slug' => Str::slug($layout['name']) ?: 'layout', 'kind' => 'layout_template', 'position' => $position, 'scope_key' => '', 'document' => DocumentData::empty(), 'settings' => [], 'source_payload' => $layout]);
                $this->store->save($section, null);
                $manifest['layout_templates']++;
            }
            $manifest['components'] = count($components);
            DB::table('cms_imports')->insert(['source' => $source, 'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR), 'created_at' => now()]);

            return $manifest;
        });
    }
}
