<?php

namespace App\Services\Cms;

use App\Models\Cms\Component;
use App\Models\Cms\Page;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PageComposition
{
    public function component(User $user, string $id, ?string $projectId): Component
    {
        abort_unless(Str::isUuid($id), 422, 'Neplatný odkaz na komponent.');

        return Component::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
            ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $projectId))->findOrFail($id);
    }

    public function resolve(User $user, array $document, ?string $projectId): array
    {
        $css = [];
        $js = [];
        $used = [];
        $count = 0;
        $bytes = 0;
        $expand = function (array $doc, array $stack = [], int $depth = 0) use (&$expand, &$css, &$js, &$used, &$count, &$bytes, $user, $projectId) {
            DocumentData::validate($doc);
            foreach (['css', 'js'] as $field) {
                $hash = hash('sha256', $doc[$field]);
                if (! isset(${$field}[$hash])) {
                    $bytes += strlen($doc[$field]);
                    if ($bytes > 2_000_000) {
                        throw ValidationException::withMessages(['document' => 'Stránka prekračuje limit štýlov a skriptov.']);
                    }
                    ${$field}[$hash] = $doc[$field];
                }
            }
            $walk = function ($node, int $level) use (&$walk, &$expand, &$used, &$count, $user, $projectId, $stack) {
                if (++$count > 5000 || $level > 48) {
                    throw ValidationException::withMessages(['document' => 'Stránka prekračuje limit obsahu alebo vnorenia.']);
                }
                if (is_string($node)) {
                    return [$node];
                }
                if ($node['type'] === 'cms-component') {
                    $id = $node['attributes']['component_id'] ?? '';
                    if (in_array($id, $stack, true)) {
                        throw ValidationException::withMessages(['document' => 'Komponenty obsahujú kruhový odkaz.']);
                    }
                    $component = $this->component($user, $id, $projectId);
                    abort_if($component->settings['placeholder'] ?? false, 422, 'Komponent zatiaľ nemá definíciu.');
                    $used[$id] = ['id' => $id, 'name' => $component->name, 'revision' => $component->revision, 'project_id' => $component->project_id];

                    return $expand($component->document, [...$stack, $id], $level + 1);
                }
                $node['id'] = (string) Str::uuid();
                $children = [];
                foreach ($node['children'] as $child) {
                    array_push($children, ...$walk($child, $level + 1));
                }
                $node['children'] = $children;

                return [$node];
            };
            $nodes = [];
            foreach ($doc['nodes'] as $node) {
                array_push($nodes, ...$walk($node, $depth));
            }

            return $nodes;
        };
        $nodes = $expand($document);
        $resolved = DocumentData::validate(['schema_version' => 1, 'nodes' => $nodes, 'css' => implode("\n", array_unique($css)), 'js' => implode("\n", array_unique($js))]);

        return ['preview' => ['objects' => DocumentData::toLegacy($resolved), 'css' => $resolved['css'], 'js' => $resolved['js']], 'components' => array_values($used)];
    }

    public function page(User $user, Page $page): array
    {
        $page->load('project', 'domain');

        $composition = $this->resolve($user, $page->document, $page->project_id);
        $palette = app(BootstrapPalette::class)->css($page->domain?->settings['bootstrap']['colors'] ?? []);
        if ($palette !== '') {
            $composition['preview']['css'] = $palette."\n".$composition['preview']['css'];
        }

        return array_merge([
            'id' => $page->id, 'legacy_id' => $page->legacy_id, 'name' => $page->name, 'slug' => $page->slug,
            'revision' => $page->revision, 'project_id' => $page->project_id, 'project_name' => $page->project?->name,
            'project_legacy_id' => $page->project?->legacy_id, 'domain_legacy_id' => $page->domain?->legacy_id,
            'domain_id' => $page->domain_id, 'domain_name' => $page->domain?->name,
            'placements_count' => count(array_filter($page->document['nodes'], fn ($node) => is_array($node) && $node['type'] === 'cms-component')),
        ], $composition);
    }
}
